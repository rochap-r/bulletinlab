<?php

namespace App\Policies;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Utilisateur;

final class BulletinPolicy
{
    public function __construct(
        private EstablishmentContext $establishmentContext,
        private ClassePolicy $classPolicy,
        private EtablissementPolicy $establishmentPolicy,
    ) {}

    public function viewClass(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->establishmentContext->has()
            && $classe->etablissement_id
                === $this->establishmentContext->id()
            && $this->classPolicy->viewCourses($utilisateur, $classe);
    }

    public function view(
        Utilisateur $utilisateur,
        Bulletin $bulletin,
    ): bool {
        $classe = $this->resolveClass($bulletin);
        $enrollment = $this->resolveEnrollment($bulletin);

        if (! $classe
            || ! $enrollment
            || $bulletin->tenant_id !== $utilisateur->tenant_id
            || $classe->tenant_id !== $bulletin->tenant_id
            || $enrollment->tenant_id !== $bulletin->tenant_id
            || $bulletin->classe_id !== $classe->getKey()
            || $enrollment->classe_id !== $classe->getKey()) {
            return false;
        }

        return $this->viewClass($utilisateur, $classe);
    }

    public function previewCalculation(
        Utilisateur $utilisateur,
        Bulletin $bulletin,
    ): bool {
        if (! $this->view($utilisateur, $bulletin)
            || ! $this->establishmentContext->has()) {
            return false;
        }

        return $this->establishmentPolicy->manageAcademicStructure(
            $utilisateur,
            $this->establishmentContext->current(),
        );
    }

    public function publishCalculation(
        Utilisateur $utilisateur,
        Bulletin $bulletin,
    ): bool {
        return $this->previewCalculation($utilisateur, $bulletin);
    }

    public function rectifyPublication(
        Utilisateur $utilisateur,
        Bulletin $bulletin,
    ): bool {
        return $this->publishCalculation($utilisateur, $bulletin);
    }

    private function resolveClass(Bulletin $bulletin): ?Classe
    {
        if ($bulletin->relationLoaded('classe')) {
            $classe = $bulletin->getRelation('classe');

            return $classe instanceof Classe ? $classe : null;
        }

        return Classe::withoutGlobalScopes()
            ->select([
                'id',
                'tenant_id',
                'etablissement_id',
                'titulaire_id',
                'active',
            ])
            ->find($bulletin->classe_id);
    }

    private function resolveEnrollment(Bulletin $bulletin): ?Inscription
    {
        if ($bulletin->relationLoaded('inscription')) {
            $enrollment = $bulletin->getRelation('inscription');

            return $enrollment instanceof Inscription ? $enrollment : null;
        }

        return Inscription::withoutGlobalScopes()
            ->select([
                'id',
                'tenant_id',
                'classe_id',
            ])
            ->find($bulletin->inscription_id);
    }
}
