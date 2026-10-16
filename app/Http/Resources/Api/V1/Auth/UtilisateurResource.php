<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class UtilisateurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nom' => $this->nom,
            'postnom' => $this->postnom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'actif' => (bool) $this->actif,
            'doit_changer_mot_de_passe' => (bool) $this->doit_changer_mot_de_passe,
            'roles' => $this->whenLoaded('roles', fn (): array => $this->scopedRoles()->pluck('name')->unique()->values()->all()),
            'permissions' => $this->whenLoaded('roles', fn (): array => $this->scopedRoles()
                ->flatMap(fn (Role $role): array => $role->relationLoaded('permissions')
                    ? $role->permissions->pluck('name')->all()
                    : [])
                ->unique()->values()->all()),
            'tenant' => $this->whenLoaded('tenant', fn (): array => [
                'slug' => $this->tenant->slug,
                'nom_espace' => $this->tenant->nom_espace,
                'statut' => $this->tenant->statut,
                'type_tenant' => $this->tenant->type_tenant,
            ]),
            'etablissement' => $this->whenLoaded('etablissement', fn (): ?array => $this->etablissement === null ? null : [
                'id' => $this->etablissement->id,
                'public_id' => $this->etablissement->public_id,
                'nom' => $this->etablissement->nom,
                'type_etablissement' => $this->etablissement->type_etablissement,
            ]),
        ];
    }

    /** @return Collection<int, Role> */
    private function scopedRoles(): Collection
    {
        return $this->roles
            ->filter(fn (Role $role): bool => $role->tenant_id === null || (int) $role->tenant_id === (int) $this->tenant_id);
    }
}
