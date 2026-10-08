<?php

namespace App\Policies;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\AttributionCours;
use App\Models\Classe;
use App\Models\ModeleMaximum;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class AttributionCoursPolicy
{
    public function __construct(
        private EstablishmentContext $establishmentContext,
    ) {}

    public function viewAny(Utilisateur $utilisateur): bool
    {
        if (! $utilisateur->actif
            || ! $this->establishmentContext->has()
            || $utilisateur->etablissement_id !== $this->establishmentContext->id()) {
            return false;
        }

        return $utilisateur->roles()
            ->where('roles.code', 'ENSEIGNANT')
            ->where(function (Builder $query) use ($utilisateur): void {
                $query
                    ->whereNull('roles.tenant_id')
                    ->orWhere('roles.tenant_id', $utilisateur->tenant_id);
            })
            ->exists();
    }

    public function viewSlots(
        Utilisateur $utilisateur,
        AttributionCours $attribution,
    ): bool {
        return $this->canAccessCurrentAssignment(
            $utilisateur,
            $attribution,
        );
    }

    public function viewGradeSheet(
        Utilisateur $utilisateur,
        AttributionCours $attribution,
        ModeleMaximum $creneau,
    ): bool {
        return $this->canAccessCurrentSlot(
            $utilisateur,
            $attribution,
            $creneau,
        );
    }

    public function upsertNote(
        Utilisateur $utilisateur,
        AttributionCours $attribution,
        ModeleMaximum $creneau,
    ): bool {
        return $this->canAccessCurrentSlot(
            $utilisateur,
            $attribution,
            $creneau,
        );
    }

    public function importNotes(
        Utilisateur $utilisateur,
        AttributionCours $attribution,
        ModeleMaximum $creneau,
    ): bool {
        return $this->canAccessCurrentSlot(
            $utilisateur,
            $attribution,
            $creneau,
        );
    }

    private function canAccessCurrentAssignment(
        Utilisateur $utilisateur,
        AttributionCours $attribution,
    ): bool {
        if (! $this->viewAny($utilisateur)
            || ! $attribution->exists
            || ! $attribution->actif
            || $attribution->tenant_id !== $utilisateur->tenant_id
            || $attribution->enseignant_id !== $utilisateur->getKey()) {
            return false;
        }

        $today = CarbonImmutable::today(
            (string) config('app.business_timezone'),
        );

        if ($attribution->date_debut?->isAfter($today)
            || $attribution->date_fin?->isBefore($today)) {
            return false;
        }

        return Classe::withoutGlobalScopes()
            ->whereKey($attribution->classe_id)
            ->where('tenant_id', $attribution->tenant_id)
            ->where(
                'etablissement_id',
                $this->establishmentContext->id(),
            )
            ->where('active', true)
            ->exists();
    }

    private function canAccessCurrentSlot(
        Utilisateur $utilisateur,
        AttributionCours $attribution,
        ModeleMaximum $creneau,
    ): bool {
        return $this->canAccessCurrentAssignment(
            $utilisateur,
            $attribution,
        )
            && $creneau->exists
            && $creneau->tenant_id === $attribution->tenant_id
            && $creneau->modele_bulletin_id
                === $attribution->modele_bulletin_id
            && $creneau->node_id === $attribution->node_id
            && (float) $creneau->maxima > 0;
    }
}
