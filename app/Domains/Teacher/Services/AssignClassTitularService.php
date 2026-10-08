<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\DTOs\AssignClassTitularData;
use App\Domains\Teacher\Events\ClassTitularAssigned;
use App\Domains\Teacher\Exceptions\ClassTitularAssignmentConflictException;
use App\Models\AnneeScolaire;
use App\Models\AuditLog;
use App\Models\Classe;
use App\Models\TitulaireClasse;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class AssignClassTitularService
{
    public function assign(
        Classe $class,
        Utilisateur $actor,
        AssignClassTitularData $data,
    ): TitulaireClasse {
        return DB::transaction(function () use ($class, $actor, $data): TitulaireClasse {
            $lockedClass = Classe::query()
                ->whereKey($class->getKey())
                ->where('tenant_id', $class->tenant_id)
                ->where('etablissement_id', $class->etablissement_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedClass->active) {
                throw new ClassTitularAssignmentConflictException(
                    'A titular cannot be assigned to an archived class.',
                );
            }

            $schoolYear = AnneeScolaire::withoutGlobalScopes()
                ->whereKey($lockedClass->annee_scolaire_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $schoolYear->active || $schoolYear->cloturee) {
                throw new ClassTitularAssignmentConflictException(
                    'A titular cannot be assigned in a closed school year.',
                );
            }

            $titular = $this->resolveTitular($lockedClass, $data->titularId);
            [$startDate, $endDate] = $this->resolveAssignmentDates(
                $schoolYear,
                $data,
            );

            $assignments = TitulaireClasse::query()
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('classe_id', $lockedClass->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $activeAssignments = $assignments->where('actif', true);
            $activeTargetAssignment = $activeAssignments->firstWhere(
                'utilisateur_id',
                $titular->getKey(),
            );

            if ($lockedClass->titulaire_id === $titular->getKey()
                && $activeAssignments->count() === 1
                && $activeTargetAssignment
                && $activeTargetAssignment->date_debut?->toDateString() === $startDate
                && $activeTargetAssignment->date_fin?->toDateString() === $endDate) {
                return $activeTargetAssignment->load('utilisateur');
            }

            $previousTitularId = $lockedClass->titulaire_id
                ?? $activeAssignments->first()?->utilisateur_id;
            $previousAssignments = $activeAssignments
                ->map(fn (TitulaireClasse $assignment): array => [
                    'titulariat_id' => $assignment->getKey(),
                    'titulaire_id' => $assignment->utilisateur_id,
                    'date_debut' => $assignment->date_debut?->toDateString(),
                    'date_fin' => $assignment->date_fin?->toDateString(),
                    'actif' => $assignment->actif,
                ])
                ->values()
                ->all();
            $deactivatedAssignmentIds = [];

            foreach ($activeAssignments as $activeAssignment) {
                $previousStartDate = $activeAssignment->date_debut?->toDateString();
                $closingDate = $previousStartDate !== null
                    && $previousStartDate > $startDate
                    ? $previousStartDate
                    : $startDate;

                $activeAssignment->update([
                    'date_fin' => $closingDate,
                    'actif' => false,
                ]);
                $deactivatedAssignmentIds[] = $activeAssignment->getKey();
            }

            $assignment = TitulaireClasse::query()->create([
                'tenant_id' => $lockedClass->tenant_id,
                'classe_id' => $lockedClass->getKey(),
                'utilisateur_id' => $titular->getKey(),
                'date_debut' => $startDate,
                'date_fin' => $endDate,
                'actif' => true,
            ]);

            $lockedClass->update([
                'titulaire_id' => $titular->getKey(),
            ]);

            AuditLog::query()->create([
                'tenant_id' => $lockedClass->tenant_id,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'TITULARIAT_CLASSE_ENREGISTRE',
                'table_cible' => $assignment->getTable(),
                'ligne_cible_id' => $assignment->getKey(),
                'anciennes_valeurs' => [
                    'pointeur_titulaire_id' => $previousTitularId,
                    'titulariats_actifs' => $previousAssignments,
                    'titulariats_desactives' => $deactivatedAssignmentIds,
                ],
                'nouvelles_valeurs' => [
                    'classe_id' => $lockedClass->getKey(),
                    'titulaire_id' => $titular->getKey(),
                    'date_debut' => $startDate,
                    'date_fin' => $endDate,
                    'actif' => true,
                ],
            ]);

            ClassTitularAssigned::dispatch(
                tenantId: $lockedClass->tenant_id,
                establishmentId: $lockedClass->etablissement_id,
                schoolYearId: $schoolYear->getKey(),
                classId: $lockedClass->getKey(),
                assignmentId: $assignment->getKey(),
                titularId: $titular->getKey(),
                previousTitularId: $previousTitularId,
                actorId: $actor->getKey(),
            );

            return $assignment->load('utilisateur');
        }, attempts: 3);
    }

    private function resolveTitular(Classe $class, int $titularId): Utilisateur
    {
        $titular = Utilisateur::query()
            ->whereKey($titularId)
            ->where('tenant_id', $class->tenant_id)
            ->where('etablissement_id', $class->etablissement_id)
            ->where('actif', true)
            ->whereHas('roles', function (Builder $query) use ($class): void {
                $query
                    ->where('roles.code', 'TITULAIRE')
                    ->where(function (Builder $roleQuery) use ($class): void {
                        $roleQuery
                            ->whereNull('roles.tenant_id')
                            ->orWhere('roles.tenant_id', $class->tenant_id);
                    });
            })
            ->lockForUpdate()
            ->first();

        if (! $titular) {
            throw (new ModelNotFoundException)->setModel(
                Utilisateur::class,
                [$titularId],
            );
        }

        return $titular;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function resolveAssignmentDates(
        AnneeScolaire $schoolYear,
        AssignClassTitularData $data,
    ): array {
        $schoolYearStart = CarbonImmutable::parse(
            (string) $schoolYear->date_debut,
        )->startOfDay();
        $schoolYearEnd = CarbonImmutable::parse(
            (string) $schoolYear->date_fin,
        )->startOfDay();
        $startDate = $data->startDate !== null
            ? CarbonImmutable::parse($data->startDate)->startOfDay()
            : $schoolYearStart;
        $endDate = $data->endDate !== null
            ? CarbonImmutable::parse($data->endDate)->startOfDay()
            : null;

        if ($startDate->lt($schoolYearStart)
            || $startDate->gt($schoolYearEnd)
            || ($endDate && (
                $endDate->lt($startDate)
                || $endDate->gt($schoolYearEnd)
            ))) {
            throw new ClassTitularAssignmentConflictException(
                'Titular assignment dates must fall within the school year.',
            );
        }

        return [
            $startDate->toDateString(),
            $endDate?->toDateString(),
        ];
    }
}
