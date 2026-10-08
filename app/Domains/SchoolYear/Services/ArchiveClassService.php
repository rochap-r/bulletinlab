<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\SchoolYear\DTOs\ArchiveClassData;
use App\Domains\SchoolYear\Events\ClassArchived;
use App\Domains\SchoolYear\Exceptions\ClassArchiveConflictException;
use App\Models\AuditLog;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

final class ArchiveClassService
{
    public function archive(
        Classe $class,
        Utilisateur $actor,
        ArchiveClassData $data,
    ): Classe {
        return DB::transaction(function () use ($class, $actor, $data): Classe {
            $lockedClass = Classe::query()
                ->whereKey($class->getKey())
                ->where('tenant_id', $class->tenant_id)
                ->where('etablissement_id', $class->etablissement_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedClass->active) {
                return $lockedClass;
            }

            $activeEnrollment = Inscription::query()
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('classe_id', $lockedClass->getKey())
                ->where('statut', 'ACTIF')
                ->lockForUpdate()
                ->first();

            if ($activeEnrollment) {
                throw new ClassArchiveConflictException(
                    'The class has active enrollments and cannot be archived.',
                );
            }

            $lockedClass->update(['active' => false]);

            AuditLog::query()->create([
                'tenant_id' => $lockedClass->tenant_id,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'CLASSE_ARCHIVEE',
                'table_cible' => $lockedClass->getTable(),
                'ligne_cible_id' => $lockedClass->getKey(),
                'anciennes_valeurs' => ['active' => true],
                'nouvelles_valeurs' => [
                    'active' => false,
                    'motif' => $data->reason,
                ],
            ]);

            ClassArchived::dispatch(
                tenantId: $lockedClass->tenant_id,
                establishmentId: $lockedClass->etablissement_id,
                schoolYearId: $lockedClass->annee_scolaire_id,
                classId: $lockedClass->getKey(),
                actorId: $actor->getKey(),
            );

            return $lockedClass->refresh();
        }, attempts: 3);
    }
}
