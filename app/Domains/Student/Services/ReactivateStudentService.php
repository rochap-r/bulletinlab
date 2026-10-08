<?php

namespace App\Domains\Student\Services;

use App\Domains\Student\DTOs\ReactivateStudentData;
use App\Domains\Student\Events\StudentReactivated;
use App\Models\AuditLog;
use App\Models\Eleve;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

final class ReactivateStudentService
{
    public function reactivate(
        Eleve $student,
        Utilisateur $actor,
        ReactivateStudentData $data,
    ): Eleve {
        return DB::transaction(function () use ($student, $actor, $data): Eleve {
            $lockedStudent = Eleve::query()
                ->whereKey($student->getKey())
                ->where('tenant_id', $student->tenant_id)
                ->where('etablissement_id', $student->etablissement_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedStudent->actif) {
                return $lockedStudent;
            }

            $lockedStudent->update(['actif' => true]);

            AuditLog::query()->create([
                'tenant_id' => $lockedStudent->tenant_id,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'ELEVE_REACTIVE',
                'table_cible' => $lockedStudent->getTable(),
                'ligne_cible_id' => $lockedStudent->getKey(),
                'anciennes_valeurs' => ['actif' => false],
                'nouvelles_valeurs' => [
                    'actif' => true,
                    'motif' => $data->reason,
                ],
            ]);

            StudentReactivated::dispatch(
                tenantId: $lockedStudent->tenant_id,
                establishmentId: $lockedStudent->etablissement_id,
                studentId: $lockedStudent->getKey(),
                actorId: $actor->getKey(),
            );

            return $lockedStudent->refresh();
        }, attempts: 3);
    }
}
