<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;

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
        return $this->managesOwnEstablishment($utilisateur);
    }

    public function viewAny(Utilisateur $utilisateur): bool
    {
        return $this->managesOwnEstablishment($utilisateur);
    }

    public function revoke(Utilisateur $utilisateur, Invitation $invitation): bool
    {
        if ($invitation->tenant_id !== $utilisateur->tenant_id
            || $invitation->etablissement_id !== $utilisateur->etablissement_id) {
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

        return $utilisateur->roles()
            ->whereIn('roles.code', self::MANAGEMENT_ROLE_CODES)
            ->where(function (Builder $query) use ($etablissement): void {
                $query
                    ->whereNull('roles.tenant_id')
                    ->orWhere('roles.tenant_id', $etablissement->tenant_id);
            })
            ->exists();
    }
}
