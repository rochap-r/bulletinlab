<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Teacher\Exceptions\TeacherNoteEntryConflictException;
use App\Enums\NoteOrigine;
use App\Enums\StatutEvaluation;
use App\Models\Bulletin;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\ModeleMaximum;
use App\Models\Note;
use App\Models\ResultatEvaluation;
use App\Models\Utilisateur;
use Illuminate\Support\Collection;

final class ProjectEvaluationNotesService
{
    public const CALCULATION_VERSION = '1.0.0';

    public function __construct(
        private EvaluationCalculationService $calculationService,
    ) {}

    /**
     * Recalcule et persiste la projection officielle des évaluations.
     *
     * Les données sont chargées par lots : une requête pour les évaluations,
     * une pour les résultats et une pour les bulletins, quel que soit le nombre
     * d'élèves transmis.
     *
     * @param  Collection<int, Inscription>  $inscriptions
     */
    public function project(
        Evaluation $sourceEvaluation,
        Collection $inscriptions,
        Utilisateur $teacher,
    ): void {
        if ($inscriptions->isEmpty()) {
            return;
        }

        $slot = ModeleMaximum::query()
            ->where('tenant_id', $sourceEvaluation->tenant_id)
            ->where('node_id', $sourceEvaluation->node_id)
            ->where('periode_id', $sourceEvaluation->periode_id)
            ->whereHas(
                'modeleBulletin',
                fn ($query) => $query->where('classe_id', $sourceEvaluation->classe_id),
            )
            ->first();

        if (! $slot instanceof ModeleMaximum) {
            throw new TeacherNoteEntryConflictException(
                'No official grading slot matches this evaluation.',
            );
        }

        $inscriptionIds = $inscriptions->modelKeys();
        $bulletins = Bulletin::query()
            ->where('tenant_id', $sourceEvaluation->tenant_id)
            ->where('classe_id', $sourceEvaluation->classe_id)
            ->where('modele_bulletin_id', $slot->modele_bulletin_id)
            ->whereIn('inscription_id', $inscriptionIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('inscription_id');

        if ($bulletins->count() !== count($inscriptionIds)) {
            throw new TeacherNoteEntryConflictException(
                'Every evaluation result must target an enrollment with one report card.',
            );
        }

        $evaluations = Evaluation::query()
            ->where('tenant_id', $sourceEvaluation->tenant_id)
            ->where('classe_id', $sourceEvaluation->classe_id)
            ->where('node_id', $sourceEvaluation->node_id)
            ->where('periode_id', $sourceEvaluation->periode_id)
            ->whereIn('statut', [
                StatutEvaluation::Publiee->value,
                StatutEvaluation::Verrouillee->value,
            ])
            ->with('typeEvaluation:id,code')
            ->orderBy('id')
            ->get();

        $resultsByEnrollment = ResultatEvaluation::query()
            ->whereIn('evaluation_id', $evaluations->modelKeys())
            ->whereIn('inscription_id', $inscriptionIds)
            ->get()
            ->groupBy('inscription_id')
            ->map(fn (Collection $results): Collection => $results->keyBy('evaluation_id'));

        foreach ($inscriptions as $inscription) {
            /** @var Collection<int, ResultatEvaluation> $results */
            $results = $resultsByEnrollment->get(
                $inscription->getKey(),
                collect(),
            );
            $components = $evaluations->map(function (Evaluation $evaluation) use ($results): array {
                $result = $results->get($evaluation->getKey());

                return [
                    'evaluation_id' => $evaluation->getKey(),
                    'libelle' => $evaluation->libelle,
                    'type_evaluation' => $evaluation->typeEvaluation?->code,
                    'maxima' => (float) $evaluation->maxima,
                    'coefficient' => (float) $evaluation->coefficient,
                    'statut_resultat' => $result?->statut_resultat?->value,
                    'valeur' => $result?->valeur !== null
                        ? (float) $result->valeur
                        : null,
                ];
            })->all();

            $aggregate = $this->calculationService->compute($components);
            $officialMaximum = (float) $slot->maxima;
            $officialValue = $this->calculationService->extrapolate(
                $aggregate,
                $officialMaximum,
            );
            $status = $officialValue !== null
                ? NoteResultStatus::Saisie
                : $this->emptyStatus($results);
            $calculationHash = hash('sha256', json_encode([
                'version' => self::CALCULATION_VERSION,
                'official_maximum' => $officialMaximum,
                'components' => $components,
            ], JSON_THROW_ON_ERROR));
            /** @var Bulletin $bulletin */
            $bulletin = $bulletins->get($inscription->getKey());
            $note = Note::withoutGlobalScopes()
                ->where('tenant_id', $sourceEvaluation->tenant_id)
                ->where('bulletin_id', $bulletin->getKey())
                ->where('node_id', $sourceEvaluation->node_id)
                ->where('periode_id', $sourceEvaluation->periode_id)
                ->lockForUpdate()
                ->first();
            $previousStatus = $note?->statut_resultat;
            $previousValue = $note?->valeur;

            if (! $note instanceof Note) {
                $note = new Note;
                $note->tenant_id = $sourceEvaluation->tenant_id;
                $note->bulletin_id = $bulletin->getKey();
                $note->node_id = $sourceEvaluation->node_id;
                $note->periode_id = $sourceEvaluation->periode_id;
            } else {
                $note->lock_version++;
            }

            $note->forceFill([
                'statut_resultat' => $status,
                'valeur' => $officialValue,
                'saisie_par' => $teacher->getKey(),
                'date_saisie' => now(),
                'origine' => NoteOrigine::Evaluations,
                'calcul_version' => self::CALCULATION_VERSION,
                'calcul_hash' => $calculationHash,
                'calculee_at' => now(),
            ])->save();

            if ($previousStatus instanceof NoteResultStatus
                && ($previousStatus !== $status || $previousValue !== $note->valeur)) {
                $note->historique()->create([
                    'ancien_statut_resultat' => $previousStatus,
                    'nouveau_statut_resultat' => $status,
                    'ancienne_valeur' => $previousValue,
                    'nouvelle_valeur' => $note->valeur,
                    'modifie_par' => $teacher->getKey(),
                    'motif' => 'Recalcul automatique depuis les évaluations.',
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, ResultatEvaluation>  $results
     */
    private function emptyStatus(Collection $results): NoteResultStatus
    {
        if ($results->contains(
            fn (ResultatEvaluation $result): bool => $result->statut_resultat === NoteResultStatus::Absente,
        )) {
            return NoteResultStatus::Absente;
        }

        return NoteResultStatus::Dispensee;
    }
}
