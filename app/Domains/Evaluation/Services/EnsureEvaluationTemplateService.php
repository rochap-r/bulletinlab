<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Exceptions\PeriodLockedException;
use App\Domains\SchoolYear\Services\PeriodeLockService;
use App\Domains\Teacher\Exceptions\TeacherNoteEntryConflictException;
use App\Enums\StatutEvaluation;
use App\Models\AttributionCours;
use App\Models\AuditLog;
use App\Models\Evaluation;
use App\Models\ModeleMaximum;
use App\Models\TypeEvaluation;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EnsureEvaluationTemplateService
{
    public const ExerciceDevoir = 'EXERCICE_DEVOIR';

    public const Cahier = 'CAHIER';

    public const Interrogation = 'INTERROGATION';

    public function __construct(
        private PeriodeLockService $periodeLockService,
    ) {}

    /**
     * Active le canevas secondaire 1/4 + 1/4 + 1/2 pour un cours et une période.
     *
     * @return Collection<int, Evaluation>
     */
    public function ensure(
        AttributionCours $assignment,
        ModeleMaximum $slot,
        Utilisateur $teacher,
    ): Collection {
        $assignment->loadMissing('classe.etablissement');
        $schoolType = $assignment->classe?->etablissement?->type_etablissement;

        if (! in_array($schoolType, ['SECONDAIRE', 'MIXTE'], true)) {
            throw new TeacherNoteEntryConflictException(
                'Ce canevas d’évaluations est réservé au secondaire. Le barème du primaire sera configuré séparément.',
            );
        }

        if ($this->periodeLockService->isLocked(
            (int) $slot->periode_id,
            (int) $assignment->classe_id,
        )) {
            throw new PeriodLockedException(
                'Cette période est verrouillée. Impossible d’activer la saisie par évaluations.',
            );
        }

        return DB::transaction(function () use ($assignment, $slot, $teacher): Collection {
            $lockedSlot = ModeleMaximum::query()
                ->whereKey($slot->getKey())
                ->where('tenant_id', $assignment->tenant_id)
                ->where('modele_bulletin_id', $assignment->modele_bulletin_id)
                ->where('node_id', $assignment->node_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSlot->loadMissing('periode');

            if (! $lockedSlot->periode?->saisissable) {
                throw new TeacherNoteEntryConflictException(
                    'Seule une période saisissable peut utiliser le canevas d’évaluations.',
                );
            }

            $types = $this->canonicalTypes();
            $bulletinMaxima = $this->componentBulletinMaxima(
                (float) $lockedSlot->maxima,
            );
            $correctionMaxima = $this->componentCorrectionMaxima(
                $bulletinMaxima,
            );
            $schoolYearId = $assignment->classe()->value('annee_scolaire_id');
            $today = CarbonImmutable::today(
                (string) config('app.business_timezone'),
            );

            foreach ($this->components() as $component => $definition) {
                /** @var TypeEvaluation $type */
                $type = $types->get($definition['type']);
                $evaluation = Evaluation::query()->firstOrCreate(
                    [
                        'tenant_id' => $assignment->tenant_id,
                        'classe_id' => $assignment->classe_id,
                        'node_id' => $assignment->node_id,
                        'periode_id' => $lockedSlot->periode_id,
                        'composante_modele' => $component,
                    ],
                    [
                        'etablissement_id' => $teacher->etablissement_id,
                        'annee_scolaire_id' => $schoolYearId,
                        'attribution_cours_id' => $assignment->getKey(),
                        'type_evaluation_id' => $type->getKey(),
                        'libelle' => $definition['label'],
                        'maxima' => $correctionMaxima[$component],
                        'maxima_bulletin' => $bulletinMaxima[$component],
                        'coefficient' => 1,
                        'date_evaluation' => $today,
                        'statut' => StatutEvaluation::Publiee,
                        'visible_parents_at' => now(),
                        'cree_par' => $teacher->getKey(),
                        'lock_version' => 1,
                    ],
                );

                if ($evaluation->wasRecentlyCreated) {
                    $this->auditCreation($evaluation, $teacher);
                } elseif ((float) $evaluation->maxima_bulletin !== $bulletinMaxima[$component]
                    || (float) $evaluation->maxima !== $correctionMaxima[$component]
                    || (int) $evaluation->attribution_cours_id !== $assignment->getKey()) {
                    $previousAssignmentId = (int) $evaluation->attribution_cours_id;

                    if (((float) $evaluation->maxima_bulletin !== $bulletinMaxima[$component]
                            || (float) $evaluation->maxima !== $correctionMaxima[$component])
                        && $evaluation->resultats()->exists()) {
                        throw new TeacherNoteEntryConflictException(
                            'Le maximum officiel a changé après la saisie des évaluations. Une correction administrative est requise.',
                        );
                    }

                    $evaluation->forceFill([
                        'attribution_cours_id' => $assignment->getKey(),
                        'type_evaluation_id' => $type->getKey(),
                        'libelle' => $definition['label'],
                        'maxima' => $correctionMaxima[$component],
                        'maxima_bulletin' => $bulletinMaxima[$component],
                    ])->save();

                    if ($previousAssignmentId !== $assignment->getKey()) {
                        $this->auditReassignment(
                            $evaluation,
                            $teacher,
                            $previousAssignmentId,
                        );
                    }
                }
            }

            $componentOrder = array_flip(array_keys($this->components()));

            return Evaluation::query()
                ->where('tenant_id', $assignment->tenant_id)
                ->where('classe_id', $assignment->classe_id)
                ->where('node_id', $assignment->node_id)
                ->where('periode_id', $lockedSlot->periode_id)
                ->whereNotNull('composante_modele')
                ->with(['typeEvaluation', 'periode', 'node', 'classe', 'resultats.eleve'])
                ->get()
                ->sortBy(fn (Evaluation $evaluation): int => $componentOrder[$evaluation->composante_modele]
                    ?? PHP_INT_MAX)
                ->values();
        });
    }

    /**
     * @return array<string, array{label: string, type: string}>
     */
    private function components(): array
    {
        return [
            self::ExerciceDevoir => ['label' => 'Exercice / Devoir', 'type' => 'EXERCICE_DEVOIR'],
            self::Cahier => ['label' => 'Cotation du cahier', 'type' => 'CAHIER'],
            self::Interrogation => ['label' => 'Interrogation', 'type' => 'INTERROGATION'],
        ];
    }

    /**
     * Les centimes qui ne peuvent pas être partagés exactement sont affectés
     * à l'interrogation afin que la somme reste égale au maximum officiel.
     *
     * @return array<string, float>
     */
    private function componentBulletinMaxima(float $officialMaximum): array
    {
        $maximumInCents = (int) round($officialMaximum * 100);
        $quarterInCents = intdiv($maximumInCents, 4);
        $halfWithRemainderInCents = $maximumInCents - (2 * $quarterInCents);

        return [
            self::ExerciceDevoir => $quarterInCents / 100,
            self::Cahier => $quarterInCents / 100,
            self::Interrogation => $halfWithRemainderInCents / 100,
        ];
    }

    /**
     * Le barème visible de l'interrogation est toujours le double du quart
     * arrondi. Les poids officiels restent ceux de componentBulletinMaxima().
     *
     * @param  array<string, float>  $bulletinMaxima
     * @return array<string, float>
     */
    private function componentCorrectionMaxima(array $bulletinMaxima): array
    {
        $quarterMaximum = $this->correctionMaximum(
            $bulletinMaxima[self::ExerciceDevoir],
        );

        return [
            self::ExerciceDevoir => $quarterMaximum,
            self::Cahier => $quarterMaximum,
            self::Interrogation => $quarterMaximum * 2,
        ];
    }

    /**
     * Choisit un barème de correction immédiatement lisible par les familles.
     * Les valeurs supérieures à 10 utilisent la dizaine supérieure et évitent
     * ainsi les barèmes moins familiers sur 15 ou 25.
     */
    private function correctionMaximum(float $bulletinMaximum): float
    {
        if ($bulletinMaximum <= 5) {
            return 5;
        }

        if ($bulletinMaximum <= 10) {
            return 10;
        }

        return ceil($bulletinMaximum / 10) * 10;
    }

    /**
     * @return Collection<string, TypeEvaluation>
     */
    private function canonicalTypes(): Collection
    {
        $definitions = [
            'EXERCICE_DEVOIR' => ['nom' => 'Exercice / Devoir', 'description' => 'Exercice en classe ou devoir à domicile'],
            'CAHIER' => ['nom' => 'Cahier', 'description' => 'Cotation du cahier'],
            'INTERROGATION' => ['nom' => 'Interrogation', 'description' => 'Interrogation orale ou écrite'],
        ];

        foreach ($definitions as $code => $definition) {
            TypeEvaluation::query()->firstOrCreate(
                ['code' => $code],
                [...$definition, 'actif' => true],
            );
        }

        return TypeEvaluation::query()
            ->whereIn('code', array_keys($definitions))
            ->get()
            ->keyBy('code');
    }

    private function auditCreation(Evaluation $evaluation, Utilisateur $teacher): void
    {
        AuditLog::query()->create([
            'tenant_id' => $evaluation->tenant_id,
            'utilisateur_id' => $teacher->getKey(),
            'action' => 'MODELE_EVALUATION_ACTIVE',
            'table_cible' => $evaluation->getTable(),
            'ligne_cible_id' => $evaluation->getKey(),
            'nouvelles_valeurs' => [
                'attribution_cours_id' => $evaluation->attribution_cours_id,
                'periode_id' => $evaluation->periode_id,
                'composante_modele' => $evaluation->composante_modele,
                'maxima' => $evaluation->maxima,
                'maxima_bulletin' => $evaluation->maxima_bulletin,
                'statut' => $evaluation->statut?->value,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function auditReassignment(
        Evaluation $evaluation,
        Utilisateur $teacher,
        int $previousAssignmentId,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $evaluation->tenant_id,
            'utilisateur_id' => $teacher->getKey(),
            'action' => 'MODELE_EVALUATION_REATTRIBUE',
            'table_cible' => $evaluation->getTable(),
            'ligne_cible_id' => $evaluation->getKey(),
            'anciennes_valeurs' => [
                'attribution_cours_id' => $previousAssignmentId,
            ],
            'nouvelles_valeurs' => [
                'attribution_cours_id' => $evaluation->attribution_cours_id,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
