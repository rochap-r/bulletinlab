<?php

namespace App\Policies;

use App\Models\Utilisateur;

final class UtilisateurPolicy
{
    private const MANAGEMENT_ROLES = ['ADMIN_ECOLE', 'ADMIN_TENANT', 'GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES'];

    public function create(Utilisateur $actor): bool
    {
        if (! $actor->actif) {
            return false;
        }

        return $actor->hasTenantRole(self::MANAGEMENT_ROLES);
    }

    public function update(Utilisateur $actor, Utilisateur $cible): bool
    {
        if (! $actor->actif) {
            return false;
        }

        if (! $actor->hasTenantRole(self::MANAGEMENT_ROLES)) {
            return false;
        }

        if ((int) $actor->tenant_id !== (int) $cible->tenant_id) {
            return false;
        }

        if ($actor->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT'])) {
            return true;
        }

        return $actor->etablissement_id !== null
            && (int) $actor->etablissement_id === (int) $cible->etablissement_id;
    }
}
