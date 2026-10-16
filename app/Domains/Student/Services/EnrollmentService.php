<?php

namespace App\Domains\Student\Services;

use App\Domains\Student\DTOs\EnrollmentData;
use App\Domains\Student\Events\StudentEnrolled;
use App\Domains\Student\Exceptions\EnrollmentConflictException;
use App\Models\AnneeScolaire;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Services\Templates\CloneSystemTemplateService;
use Illuminate\Support\Facades\DB;

final class EnrollmentService
{
    public function __construct(private CloneSystemTemplateService $cloneSystemTemplateService) {}

    public function enroll(
        Classe $classe,
        EnrollmentData $data,
    ): Inscription {
        return DB::transaction(function () use ($classe, $data): Inscription {
            $lockedClass = Classe::query()
                ->whereKey($classe->getKey())
                ->where('tenant_id', $classe->tenant_id)
                ->where('etablissement_id', $classe->etablissement_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedClass?->active) {
                throw new EnrollmentConflictException(
                    'The class is not open for enrollment.',
                );
            }

            $schoolYear = AnneeScolaire::withoutGlobalScopes()
                ->whereKey($lockedClass->annee_scolaire_id)
                ->lockForUpdate()
                ->first();

            if (! $schoolYear?->active || $schoolYear->cloturee) {
                throw new EnrollmentConflictException(
                    'The school year is not open for enrollment.',
                );
            }

            $student = Eleve::query()
                ->whereKey($data->studentId)
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('etablissement_id', $lockedClass->etablissement_id)
                ->lockForUpdate()
                ->first();

            if (! $student?->actif) {
                throw new EnrollmentConflictException(
                    'The student is not available for enrollment.',
                );
            }

            $existingEnrollment = Inscription::query()
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('eleve_id', $student->getKey())
                ->where('annee_scolaire_id', $schoolYear->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingEnrollment) {
                throw new EnrollmentConflictException(
                    'The student is already enrolled for this school year. A transfer is required.',
                );
            }

            $enrollment = Inscription::query()->create([
                'tenant_id' => $lockedClass->tenant_id,
                'eleve_id' => $student->getKey(),
                'classe_id' => $lockedClass->getKey(),
                'annee_scolaire_id' => $schoolYear->getKey(),
                'numero_inscription' => $data->registrationNumber,
                'statut' => 'ACTIF',
                'date_inscription' => $data->enrollmentDate,
            ]);

            $reportCardModel = ModeleBulletin::query()
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('classe_id', $lockedClass->getKey())
                ->where('annee_scolaire_id', $schoolYear->getKey())
                ->lockForUpdate()
                ->first();

            if ($reportCardModel === null) {
                if (! $lockedClass->systemTemplate()->where('actif', true)->exists()) {
                    throw new EnrollmentConflictException(
                        'Le modèle global de bulletin de cette classe est indisponible ou désactivé. Contactez la direction pour rétablir sa configuration, puis réessayez l’inscription.',
                    );
                }
                $reportCardModel = $this->cloneSystemTemplateService->cloneForClasse($lockedClass);
            }

            if (! $reportCardModel->actif) {
                throw new EnrollmentConflictException(
                    'La configuration du bulletin de cette classe est désactivée. Contactez la direction pour la réactiver, puis réessayez l’inscription.',
                );
            }

            $reportCard = Bulletin::query()->create([
                'tenant_id' => $lockedClass->tenant_id,
                'inscription_id' => $enrollment->getKey(),
                'classe_id' => $lockedClass->getKey(),
                'modele_bulletin_id' => $reportCardModel->getKey(),
                'decision_statut' => 'EN_COURS',
                'verrouille' => false,
            ]);

            StudentEnrolled::dispatch(
                tenantId: $lockedClass->tenant_id,
                establishmentId: $lockedClass->etablissement_id,
                schoolYearId: $schoolYear->getKey(),
                classId: $lockedClass->getKey(),
                studentId: $student->getKey(),
                enrollmentId: $enrollment->getKey(),
                reportCardId: $reportCard->getKey(),
            );

            return $enrollment->load(['eleve', 'bulletin']);
        }, attempts: 3);
    }
}
