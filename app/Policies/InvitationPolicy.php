<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\Utilisateur;

final class InvitationPolicy
{
    /**
     * @var list<string>
     */
    private const MANAGEMENT_ROLE_CODES = [
        'ADMIN_ECOLE',
        'DIRECTEUR_ETUDES',
    ];

    public function create(Utilisateur $utilisateur): bool
    {
        return $this->managesOwnEstablishment($utilisateur)
            || $this->isTenantAdministrator($utilisateur);
    }

    public function viewAny(Utilisateur $utilisateur): bool
    {
        return $this->managesOwnEstablishment($utilisateur)
            || $this->isTenantAdministrator($utilisateur);
    }

    public function revoke(Utilisateur $utilisateur, Invitation $invitation): bool
    {
        if ($invitation->tenant_id !== $utilisateur->tenant_id) {
            return false;
        }

        if ($this->isTenantAdministrator($utilisateur)) {
            return true;
        }

        if ($invitation->etablissement_id !== $utilisateur->etablissement_id) {
            return false;
        }

        return $this->managesOwnEstablishment($utilisateur);
    }

    private function managesOwnEstablishment(Utilisateur $utilisateur): bool
    {
        if (! $utilisateur->actif || ! $utilisateur->etablissement_id) {
            return false;
        }

        $etablissement = $utilisateur->etablissement()->first();

        if (! $etablissement || ! $etablissement->actif
            || $etablissement->tenant_id !== $utilisateur->tenant_id) {
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
