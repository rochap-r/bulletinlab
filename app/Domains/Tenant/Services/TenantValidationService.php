<?php

namespace App\Domains\Tenant\Services;

use App\Domains\Tenant\Contracts\TenantValidationServiceInterface;
use App\Domains\Tenant\Exceptions\TenantValidationConflictException;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\UtilisateurPlateforme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TenantValidationService implements TenantValidationServiceInterface
{
    public function activate(Tenant $tenant, UtilisateurPlateforme $validePar): Tenant
    {
        return DB::transaction(function () use ($tenant, $validePar): Tenant {
            $locked = $this->lockPendingTenant($tenant);

            $locked->update([
                'statut' => 'ACTIF',
                'valide_at' => now(),
                'valide_par_id' => $validePar->getKey(),
                'motif_refus' => null,
            ]);

            $this->record($locked, $validePar, 'TENANT_VALIDE');

            Log::channel('security')->info('tenant.activated', [
                'tenant_id' => $locked->getKey(),
                'by_platform_user_id' => $validePar->getKey(),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reject(Tenant $tenant, UtilisateurPlateforme $validePar, string $motif): Tenant
    {
        return DB::transaction(function () use ($tenant, $validePar, $motif): Tenant {
            $locked = $this->lockPendingTenant($tenant);

            $locked->update([
                'statut' => 'REFUSE',
                'valide_at' => now(),
                'valide_par_id' => $validePar->getKey(),
                'motif_refus' => $motif,
            ]);

            $this->record($locked, $validePar, 'TENANT_REFUSE');

            Log::channel('security')->info('tenant.rejected', [
                'tenant_id' => $locked->getKey(),
                'by_platform_user_id' => $validePar->getKey(),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reexamine(Tenant $tenant, UtilisateurPlateforme $validePar): Tenant
    {
        return DB::transaction(function () use ($tenant, $validePar): Tenant {
            $locked = Tenant::query()->lockForUpdate()->find($tenant->getKey());

            if ($locked === null || $locked->statut !== 'REFUSE') {
                throw new TenantValidationConflictException(
                    'Only a tenant with status REFUSE can be sent back for review.'
                );
            }

            $locked->update([
                'statut' => 'EN_ATTENTE',
                'valide_at' => null,
                'valide_par_id' => null,
                'motif_refus' => null,
            ]);

            $this->record($locked, $validePar, 'TENANT_REEXAMEN');

            Log::channel('security')->info('tenant.reexamined', [
                'tenant_id' => $locked->getKey(),
                'by_platform_user_id' => $validePar->getKey(),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }

    private function lockPendingTenant(Tenant $tenant): Tenant
    {
        $locked = Tenant::query()->lockForUpdate()->find($tenant->getKey());

        if ($locked === null || ! $locked->isPendingValidation()) {
            throw new TenantValidationConflictException(
                'Only a tenant with status EN_ATTENTE can be validated.'
            );
        }

        return $locked;
    }

    private function record(Tenant $tenant, UtilisateurPlateforme $validePar, string $action): void
    {
        AuditLog::query()->create([
            'tenant_id' => $tenant->getKey(),
            'utilisateur_id' => null,
            'action' => $action,
            'table_cible' => $tenant->getTable(),
            'ligne_cible_id' => $tenant->getKey(),
            'nouvelles_valeurs' => [
                'statut' => $tenant->statut,
                'valide_par_id' => $validePar->getKey(),
                'valide_at' => $tenant->valide_at?->toIso8601String(),
                'motif_refus' => $tenant->motif_refus,
            ],
        ]);
    }
}
