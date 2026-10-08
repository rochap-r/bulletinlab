<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Utilisateur;

final class ClassePolicy
{
    public function __construct(
        private EtablissementPolicy $establishmentPolicy,
    ) {}

    public function createEnrollment(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageStudents($utilisateur, $classe);
    }

    public function recordAttendance(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageStudents($utilisateur, $classe);
    }

    public function update(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageLifecycle($utilisateur, $classe);
    }

    public function archive(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageLifecycle($utilisateur, $classe);
    }

    public function assignCourseTeacher(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageLifecycle($utilisateur, $classe);
    }

    public function assignClassTitular(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageLifecycle($utilisateur, $classe);
    }

    public function generateProclamation(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageLifecycle($utilisateur, $classe);
    }

    public function deliberateClass(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageLifecycle($utilisateur, $classe);
    }

    public function publishClass(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageLifecycle($utilisateur, $classe);
    }

    public function generatePromotion(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->canManageLifecycle($utilisateur, $classe);
    }

    public function viewCourses(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        $establishment = $this->resolveEstablishment($utilisateur, $classe);

        if (! $establishment) {
            return false;
        }

        if ($classe->titulaire_id === $utilisateur->getKey()) {
            return true;
        }

        return $this->establishmentPolicy->manageAcademicStructure(
            $utilisateur,
            $establishment,
        );
    }

    private function canManageStudents(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        if (! $classe->active) {
            return false;
        }

        $establishment = $this->resolveEstablishment($utilisateur, $classe);

        if (! $establishment) {
            return false;
        }

        if ($classe->titulaire_id === $utilisateur->getKey()) {
            return true;
        }

        return $this->establishmentPolicy->manageAcademicStructure(
            $utilisateur,
            $establishment,
        );
    }

    private function canManageLifecycle(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        $establishment = $this->resolveEstablishment($utilisateur, $classe);

        return $establishment
            && $this->establishmentPolicy->manageAcademicStructure(
                $utilisateur,
                $establishment,
            );
    }

    private function resolveEstablishment(
        Utilisateur $utilisateur,
        Classe $classe,
    ): ?Etablissement {
        if (! $utilisateur->actif
            || $utilisateur->tenant_id !== $classe->tenant_id
            || $utilisateur->etablissement_id !== $classe->etablissement_id) {
            return null;
        }

        return Etablissement::withoutGlobalScopes()
            ->whereKey($classe->etablissement_id)
            ->where('tenant_id', $classe->tenant_id)
            ->where('actif', true)
            ->first();
    }
}
