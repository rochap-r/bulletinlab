<?php

namespace App\Domains\Student\Services;

use App\Domains\Student\DTOs\ArchiveStudentData;
use App\Domains\Student\Events\StudentArchived;
use App\Domains\Student\Exceptions\StudentArchiveConflictException;
use App\Models\AuditLog;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

final class ArchiveStudentService
{
    public function archive(
        Eleve $student,
        Utilisateur $actor,
        ArchiveStudentData $data,
    ): Eleve {
        return DB::transaction(function () use ($student, $actor, $data): Eleve {
            $lockedStudent = Eleve::query()
                ->whereKey($student->getKey())
                ->where('tenant_id', $student->tenant_id)
                ->where('etablissement_id', $student->etablissement_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedStudent->actif) {
                return $lockedStudent;
            }

            $activeEnrollment = Inscription::query()
                ->where('tenant_id', $lockedStudent->tenant_id)
                ->where('eleve_id', $lockedStudent->getKey())
                ->where('statut', 'ACTIF')
                ->lockForUpdate()
                ->first();

            if ($activeEnrollment) {
                throw new StudentArchiveConflictException(
                    'The student has an active enrollment and cannot be archived.',
                );
            }

            $lockedStudent->update(['actif' => false]);

            AuditLog::query()->create([
                'tenant_id' => $lockedStudent->tenant_id,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'ELEVE_ARCHIVE',
                'table_cible' => $lockedStudent->getTable(),
                'ligne_cible_id' => $lockedStudent->getKey(),
                'anciennes_valeurs' => ['actif' => true],
                'nouvelles_valeurs' => [
                    'actif' => false,
                    'motif' => $data->reason,
                ],
            ]);

            StudentArchived::dispatch(
                tenantId: $lockedStudent->tenant_id,
                establishmentId: $lockedStudent->etablissement_id,
                studentId: $lockedStudent->getKey(),
                actorId: $actor->getKey(),
            );

            return $lockedStudent->refresh();
        }, attempts: 3);
    }
}
