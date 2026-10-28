<?php

namespace App\Http\Controllers\Api\V1\ResponsableAuth;

use App\Domains\Evaluation\Services\EvaluationCalculationService;
use App\Enums\NoteOrigine;
use App\Enums\StatutEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ResponsableAuth\ResponsableEvaluationResultResource;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\LienResponsableEleve;
use App\Models\ModeleMaximum;
use App\Models\Note;
use App\Models\Responsable;
use App\Models\ResultatEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ResponsableEnfantEvaluationsController extends Controller
{
    public function __construct(
        private EvaluationCalculationService $calculationService,
    ) {}

    public function __invoke(Request $request, string $lienPublicId): JsonResponse
    {
        $responsable = $request->user();

        abort_unless($responsable instanceof Responsable, JsonResponse::HTTP_UNAUTHORIZED);

        // Le 404 est volontairement générique : on ne doit pas fuiter l'existence d'un lien.
        $lien = LienResponsableEleve::query()
            ->verified()
            ->whereHas('tenant', fn ($query) => $query
                ->whereIn('statut', ['ESSAI', 'ACTIF'])
                ->where('espace_parents_actif', true))
            ->where('responsable_id', $responsable->getKey())
            ->where('public_id', $lienPublicId)
            ->where('peut_consulter_notes', true)
            ->firstOrFail();

        $eleve = $lien->eleve;
        if (! $eleve instanceof Eleve) {
            return $this->reponseVide(null, null, $request);
        }

        // Aucun 404 ici : un élève sans inscription active cette année n'a simplement
        // pas d'évaluation à afficher. On renvoie un état vide plutôt qu'une erreur
        // qui bloquerait l'affichage de la fiche enfant.
        $inscription = $eleve->activeInscription;
        $classe = $inscription instanceof Inscription ? $inscription->classe : null;

        if (! $inscription instanceof Inscription || ! $classe instanceof Classe) {
            Log::warning('ResponsableEnfantEvaluations : aucune inscription/classe active', [
                'responsable_id' => $responsable->getKey(),
                'lien_public_id' => $lienPublicId,
                'eleve_id' => $eleve->getKey(),
            ]);

            return $this->reponseVide($eleve, $classe, $request);
        }

        return response()->json($this->construireReponse($lien, $eleve, $inscription, $classe));
    }

    /**
     * @return array<string, mixed>
     */
    private function reponseVide(?Eleve $eleve, ?Classe $classe, Request $request): JsonResponse
    {
        return response()->json([
            'enfant' => $eleve instanceof Eleve ? [
                'public_id' => $eleve->public_id,
                'nom' => $eleve->nom,
                'postnom' => $eleve->postnom,
                'prenom' => $eleve->prenom,
            ] : null,
            'classe' => $classe instanceof Classe ? [
                'public_id' => $classe->public_id,
                'nom' => $classe->nom,
                'code' => $classe->code,
            ] : null,
            'inscription_public_id' => null,
            'evaluations' => [],
            'calculs_par_periode' => [],
            'notes_par_periode' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function construireReponse(
        LienResponsableEleve $lien,
        Eleve $eleve,
        Inscription $inscription,
        Classe $classe,
    ): array {
        $bulletin = $inscription->bulletin()
            ->where('tenant_id', $lien->tenant_id)
            ->where('classe_id', $classe->getKey())
            ->first();

        if ($bulletin === null) {
            return [
                'enfant' => [
                    'public_id' => $eleve->public_id,
                    'nom' => $eleve->nom,
                    'postnom' => $eleve->postnom,
                    'prenom' => $eleve->prenom,
                ],
                'classe' => [
                    'public_id' => $classe->public_id,
                    'nom' => $classe->nom,
                    'code' => $classe->code,
                ],
                'inscription_public_id' => $inscription->public_id,
                'evaluations' => [],
                'calculs_par_periode' => [],
                'notes_par_periode' => [],
            ];
        }

        $notes = Note::query()
            ->where('tenant_id', $lien->tenant_id)
            ->where('bulletin_id', $bulletin->getKey())
            ->with(['node', 'periode'])
            ->orderBy('periode_id')
            ->orderBy('node_id')
            ->get();
        $slots = ModeleMaximum::query()
            ->where('tenant_id', $lien->tenant_id)
            ->where('modele_bulletin_id', $bulletin->modele_bulletin_id)
            ->get()
            ->keyBy(fn (ModeleMaximum $slot): string => $slot->node_id.'|'.$slot->periode_id);
        $evaluationNotes = $notes->filter(
            fn (Note $note): bool => $note->origine === NoteOrigine::Evaluations,
        );

        $evaluations = Evaluation::query()
            ->where('tenant_id', $lien->tenant_id)
            ->where('classe_id', $classe->getKey())
            ->whereIn('node_id', $evaluationNotes->pluck('node_id'))
            ->whereIn('periode_id', $evaluationNotes->pluck('periode_id'))
            ->whereIn('statut', [
                StatutEvaluation::Publiee->value,
                StatutEvaluation::Verrouillee->value,
            ])
            ->with(['typeEvaluation', 'node', 'periode'])
            ->orderBy('periode_id')
            ->orderBy('node_id')
            ->orderBy('libelle')
            ->get()
            ->filter(fn (Evaluation $evaluation): bool => $evaluationNotes->contains(
                fn (Note $note): bool => $note->node_id === $evaluation->node_id
                    && $note->periode_id === $evaluation->periode_id,
            ));

        $evaluationIds = $evaluations->pluck('id')->all();

        /** @var Collection<int, ResultatEvaluation> $enfantResultats */
        $enfantResultats = ResultatEvaluation::query()
            ->whereIn('evaluation_id', $evaluationIds)
            ->where('inscription_id', $inscription->getKey())
            ->get()
            ->keyBy('evaluation_id');

        $groups = $evaluations->groupBy(
            fn (Evaluation $evaluation): string => $evaluation->node_id.'|'.$evaluation->periode_id,
        );
        $items = [];
        $calculs = [];
        $notesParPeriode = [];

        foreach ($notes as $note) {
            $key = $note->node_id.'|'.$note->periode_id;
            /** @var ModeleMaximum|null $slot */
            $slot = $slots->get($key);
            $officialMaximum = $slot instanceof ModeleMaximum
                ? (float) $slot->maxima
                : 0.0;
            $group = $note->origine === NoteOrigine::Evaluations
                ? $groups->get($key, collect())
                : collect();
            $components = $group->map(
                fn (Evaluation $evaluation): array => [
                    'evaluation_id' => $evaluation->getKey(),
                    'libelle' => $evaluation->libelle,
                    'type_evaluation' => $evaluation->typeEvaluation?->code,
                    'maxima' => (float) $evaluation->maxima,
                    'coefficient' => (float) $evaluation->coefficient,
                    'statut_resultat' => $enfantResultats->get($evaluation->getKey())?->statut_resultat?->value,
                    'valeur' => $enfantResultats->get($evaluation->getKey())?->valeur !== null
                        ? (float) $enfantResultats->get($evaluation->getKey())?->valeur
                        : null,
                ],
            )->all();
            $rawAggregate = $this->calculationService->compute($components)->toArray();
            $calculation = [
                ...$rawAggregate,
                'origine' => $note->origine->value,
                'total_obtenu' => $note->valeur !== null ? (float) $note->valeur : 0.0,
                'total_maxima' => $officialMaximum,
                'pourcentage' => $officialMaximum > 0 && $note->valeur !== null
                    ? round((float) $note->valeur / $officialMaximum * 100, 2)
                    : 0.0,
                'total_composantes_obtenu' => $rawAggregate['total_obtenu'],
                'total_composantes_maxima' => $rawAggregate['total_maxima'],
                'valeur_directe' => $note->origine === NoteOrigine::Directe && $note->valeur !== null
                    ? (float) $note->valeur
                    : null,
                'maxima_reference' => $officialMaximum,
                'saisie_directe' => $note->origine === NoteOrigine::Directe,
            ];
            $publicIds = $group->mapWithKeys(
                fn (Evaluation $evaluation): array => [$evaluation->getKey() => $evaluation->public_id],
            );
            $calculation['composantes'] = collect($calculation['composantes'])
                ->map(function (array $component) use ($publicIds): array {
                    $publicId = $publicIds->get($component['evaluation_id']);
                    unset($component['evaluation_id']);

                    return ['evaluation_public_id' => $publicId, ...$component];
                })
                ->all();
            $calculs[] = $calculation;
            $noteEvaluations = [];

            foreach ($group as $evaluation) {
                $resultat = $enfantResultats->get($evaluation->getKey());
                $item = [
                    'evaluation' => [
                        'public_id' => $evaluation->public_id,
                        'libelle' => $evaluation->libelle,
                        'maxima' => $evaluation->maxima,
                        'coefficient' => $evaluation->coefficient,
                        'date_evaluation' => $evaluation->date_evaluation?->toISOString(),
                        'statut' => $evaluation->statut?->value,
                        'type_evaluation' => $evaluation->typeEvaluation?->code,
                        'periode' => [
                            'code' => $evaluation->periode?->code,
                            'nom' => $evaluation->periode?->nom,
                        ],
                        'node' => [
                            'libelle' => $evaluation->node?->libelle,
                        ],
                    ],
                    'resultat' => $resultat ? ResponsableEvaluationResultResource::make($resultat) : null,
                    'periode_calcul' => $calculation,
                ];
                $items[] = $item;
                $noteEvaluations[] = $item;
            }

            $notesParPeriode[] = [
                'note_public_id' => $note->public_id,
                'origine' => $note->origine->value,
                'statut_resultat' => $note->statut_resultat->value,
                'valeur' => $note->valeur,
                'maxima' => $officialMaximum,
                'pourcentage' => $calculation['pourcentage'],
                'commentaire' => $note->commentaire,
                'calculee_at' => $note->calculee_at?->toISOString(),
                'node' => [
                    'libelle' => $note->node?->libelle,
                ],
                'periode' => [
                    'code' => $note->periode?->code,
                    'nom' => $note->periode?->nom,
                ],
                'evaluations' => $noteEvaluations,
            ];
        }

        return [
            'enfant' => [
                'public_id' => $eleve->public_id,
                'nom' => $eleve->nom,
                'postnom' => $eleve->postnom,
                'prenom' => $eleve->prenom,
            ],
            'classe' => [
                'public_id' => $classe->public_id,
                'nom' => $classe->nom,
                'code' => $classe->code,
            ],
            'inscription_public_id' => $inscription->public_id,
            'evaluations' => $items,
            'calculs_par_periode' => $calculs,
            'notes_par_periode' => $notesParPeriode,
        ];
    }
}
