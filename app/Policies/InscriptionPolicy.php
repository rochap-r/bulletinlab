<?php

namespace App\Policies;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Utilisateur;

final class InscriptionPolicy
{
    public function __construct(
        private EtablissementPolicy $establishmentPolicy,
        private EstablishmentContext $establishmentContext,
    ) {}

    public function viewReportCard(
        Utilisateur $utilisateur,
        Inscription $inscription,
    ): bool {
        $class = $inscription->classe;
        $establishment = $this->resolveEstablishment(
            $utilisateur,
            $inscription,
        );

        if (! $establishment) {
            return false;
        }

        if ($class->titulaire_id === $utilisateur->getKey()) {
            return true;
        }

        return $this->establishmentPolicy->manageAcademicStructure(
            $utilisateur,
            $establishment,
        );
    }

    public function recordExit(
        Utilisateur $utilisateur,
        Inscription $inscription,
    ): bool {
        $class = $inscription->classe;
        $establishment = $this->resolveEstablishment(
            $utilisateur,
            $inscription,
        );

        if (! $establishment) {
            return false;
        }

        if ($this->establishmentPolicy->manageAcademicStructure(
            $utilisateur,
            $establishment,
        )) {
            return true;
        }

        return $class->active
            && $class->titulaire_id === $utilisateur->getKey();
    }

    private function resolveEstablishment(
        Utilisateur $utilisateur,
        Inscription $inscription,
    ): ?Etablissement {
        $class = $inscription->classe;

        if (! $utilisateur->actif
            || $utilisateur->tenant_id !== $inscription->tenant_id
            || $utilisateur->tenant_id !== $class->tenant_id) {
            return null;
        }

        if ($utilisateur->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT'])) {
            if (! $this->establishmentContext->has()
                || $this->establishmentContext->id() !== $class->etablissement_id) {
                return null;
            }
        } elseif ($utilisateur->etablissement_id !== $class->etablissement_id) {
            return null;
        }

        return Etablissement::withoutGlobalScopes()
            ->whereKey($class->etablissement_id)
            ->where('tenant_id', $class->tenant_id)
            ->where('actif', true)
            ->first();
    }
}
