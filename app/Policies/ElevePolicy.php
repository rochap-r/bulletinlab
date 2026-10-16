<?php

namespace App\Policies;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Utilisateur;

final class ElevePolicy
{
    public function __construct(
        private EtablissementPolicy $establishmentPolicy,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function archive(Utilisateur $utilisateur, Eleve $eleve): bool
    {
        return $this->canManageLifecycle($utilisateur, $eleve);
    }

    public function reactivate(Utilisateur $utilisateur, Eleve $eleve): bool
    {
        return $this->canManageLifecycle($utilisateur, $eleve);
    }

    private function canManageLifecycle(
        Utilisateur $utilisateur,
        Eleve $eleve,
    ): bool {
        if (! $utilisateur->actif
            || $utilisateur->tenant_id !== $eleve->tenant_id) {
            return false;
        }

        if ($utilisateur->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT'])) {
            if (! $this->establishmentContext->has()
                || $this->establishmentContext->id() !== $eleve->etablissement_id) {
                return false;
            }
        } elseif ($utilisateur->etablissement_id !== $eleve->etablissement_id) {
            return false;
        }

        $establishment = Etablissement::withoutGlobalScopes()
            ->whereKey($eleve->etablissement_id)
            ->where('tenant_id', $eleve->tenant_id)
            ->where('actif', true)
            ->first();

        if (! $establishment) {
            return false;
        }

        // Le secrétariat gère le dossier élève de son école (pas le
        // pilotage académique : classes, affectations, bulletins).
        if ($utilisateur->hasTenantRole('SECRETARIAT')) {
            return true;
        }

        return $this->establishmentPolicy->manageAcademicStructure(
            $utilisateur,
            $establishment,
        );
    }
}
