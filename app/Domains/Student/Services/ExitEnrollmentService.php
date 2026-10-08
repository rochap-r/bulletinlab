<?php

namespace App\Domains\Student\Services;

use App\Domains\Evaluation\Services\EncodageProgressService;
use App\Domains\Student\DTOs\EnrollmentExitData;
use App\Domains\Student\Events\EnrollmentExited;
use App\Domains\Student\Exceptions\EnrollmentConflictException;
use App\Models\AnneeScolaire;
use App\Models\AuditLog;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\SuiviEncodageNote;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

final class ExitEnrollmentService
{
    public function __construct(
        private EncodageProgressService $progressService,
    ) {}

    public function recordExit(
        Inscription $enrollment,
        Utilisateur $actor,
        EnrollmentExitData $data,
    ): Inscription {
        return DB::transaction(function () use ($enrollment, $actor, $data): Inscription {
            $lockedClass = Classe::query()
                ->whereKey($enrollment->classe_id)
                ->where('tenant_id', $enrollment->tenant_id)
                ->where('etablissement_id', $actor->etablissement_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedEnrollment = Inscription::query()
                ->whereKey($enrollment->getKey())
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('classe_id', $lockedClass->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedEnrollment->statut !== 'ACTIF') {
                if ($lockedEnrollment->statut === $data->status) {
                    return $lockedEnrollment->load(['eleve', 'bulletin']);
                }

                throw new EnrollmentConflictException(
                    'The enrollment has already reached another final state.',
                );
            }

            $schoolYear = AnneeScolaire::withoutGlobalScopes()
                ->whereKey($lockedEnrollment->annee_scolaire_id)
                ->lockForUpdate()
                ->first();

            if (! $schoolYear) {
                throw new EnrollmentConflictException(
                    'The enrollment school year is unavailable.',
                );
            }

            $minimumExitDate = max(array_filter([
                (string) $schoolYear->date_debut,
                $lockedEnrollment->date_inscription
                    ? (string) $lockedEnrollment->date_inscription
                    : null,
            ]));

            if ($data->exitDate < $minimumExitDate
                || $data->exitDate > (string) $schoolYear->date_fin) {
                throw new EnrollmentConflictException(
                    'The exit date must belong to the enrollment school year and follow the enrollment date.',
                );
            }

            $lockedEnrollment->update(['statut' => $data->status]);

            AuditLog::query()->create([
                'tenant_id' => $lockedEnrollment->tenant_id,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'INSCRIPTION_SORTIE',
                'table_cible' => $lockedEnrollment->getTable(),
                'ligne_cible_id' => $lockedEnrollment->getKey(),
                'anciennes_valeurs' => ['statut' => 'ACTIF'],
                'nouvelles_valeurs' => [
                    'statut' => $data->status,
                    'date_sortie' => $data->exitDate,
                    'motif' => $data->reason,
                ],
            ]);

            EnrollmentExited::dispatch(
                tenantId: $lockedEnrollment->tenant_id,
                establishmentId: $lockedClass->etablissement_id,
                schoolYearId: $lockedEnrollment->annee_scolaire_id,
                classId: $lockedEnrollment->classe_id,
                studentId: $lockedEnrollment->eleve_id,
                enrollmentId: $lockedEnrollment->getKey(),
                actorId: $actor->getKey(),
                status: $data->status,
                exitDate: $data->exitDate,
            );

            SuiviEncodageNote::query()
                ->where('classe_id', $lockedClass->getKey())
                ->where('statut', '!=', 'VERROUILLE')
                ->lockForUpdate()
                ->get(['node_id', 'periode_id'])
                ->each(fn (SuiviEncodageNote $progress): mixed => $this->progressService->updateProgress(
                    $lockedClass->getKey(),
                    $progress->node_id,
                    $progress->periode_id,
                ));

            return $lockedEnrollment->load(['eleve', 'bulletin']);
        }, attempts: 3);
    }
}
