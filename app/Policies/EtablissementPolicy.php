<?php

namespace App\Policies;

use App\Models\Etablissement;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;

final class EtablissementPolicy
{
    /**
     * @var list<string>
     */
    private const MANAGEMENT_ROLE_CODES = [
        'ADMIN_ECOLE',
        'DIRECTEUR_ETUDES',
    ];

    public function manageAcademicStructure(
        Utilisateur $utilisateur,
        Etablissement $etablissement,
    ): bool {
        if (! $utilisateur->actif
            || ! $etablissement->actif
            || $utilisateur->tenant_id !== $etablissement->tenant_id
            || $utilisateur->etablissement_id !== $etablissement->getKey()) {
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
