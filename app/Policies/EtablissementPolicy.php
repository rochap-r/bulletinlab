<?php

namespace App\Policies;

use App\Models\Etablissement;
use App\Models\Utilisateur;

final class EtablissementPolicy
{
    /**
     * @var list<string>
     */
    private const MANAGEMENT_ROLE_CODES = [
        'ADMIN_ECOLE',
        'DIRECTEUR_ETUDES',
        'GESTIONNAIRE_ECOLE',
    ];

    public function viewAny(Utilisateur $utilisateur): bool
    {
        return $this->isTenantAdministrator($utilisateur);
    }

    public function view(Utilisateur $utilisateur, Etablissement $etablissement): bool
    {
        if (! $utilisateur->actif
            || $utilisateur->tenant_id !== $etablissement->tenant_id) {
            return false;
        }

        if ($this->isTenantAdministrator($utilisateur)) {
            return true;
        }

        return $utilisateur->etablissement_id === $etablissement->getKey();
    }

    /**
     * @var list<string>
     */
    private const TEAM_MANAGEMENT_ROLE_CODES = [
        'ADMIN_ECOLE',
        'ADMIN_TENANT',
        'GESTIONNAIRE_ECOLE',
        'DIRECTEUR_ETUDES',
    ];

    public function viewMembers(Utilisateur $utilisateur, Etablissement $etablissement): bool
    {
        if (! $utilisateur->actif
            || $utilisateur->tenant_id !== $etablissement->tenant_id) {
            return false;
        }

        if ($this->isTenantAdministrator($utilisateur)) {
            return true;
        }

        return $utilisateur->etablissement_id === $etablissement->getKey()
            && $utilisateur->hasTenantRole(self::TEAM_MANAGEMENT_ROLE_CODES);
    }

    public function create(Utilisateur $utilisateur): bool
    {
        return $this->isTenantAdministrator($utilisateur);
    }

    public function manageAcademicStructure(
        Utilisateur $utilisateur,
        Etablissement $etablissement,
    ): bool {
        if (! $utilisateur->actif
            || ! $etablissement->actif
            || $utilisateur->tenant_id !== $etablissement->tenant_id) {
            return false;
        }

        if ($this->isTenantAdministrator($utilisateur)) {
            return true;
        }

        if ($utilisateur->etablissement_id !== $etablissement->getKey()) {
            return false;
        }

        return $utilisateur->hasTenantRole(self::MANAGEMENT_ROLE_CODES, $etablissement->tenant_id);
    }

    private function isTenantAdministrator(Utilisateur $utilisateur): bool
    {
        if (! $utilisateur->actif) {
            return false;
        }

        return $utilisateur->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT']);
    }
}
