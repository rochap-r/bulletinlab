<?php

namespace App\Http\Resources\Api\V1\Membership;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UtilisateurMotDePasseReinitialiseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'postnom' => $this->postnom,
            'prenom' => $this->prenom,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'mot_de_passe_temporaire' => '123456',
            'doit_changer_mot_de_passe' => (bool) $this->doit_changer_mot_de_passe,
            'actif' => (bool) $this->actif,
            'roles' => $this->whenLoaded('roles', fn (): array => $this->tenantRoleCodes()),
            'tenant' => $this->whenLoaded('tenant', fn (): array => [
                'slug' => $this->tenant->slug,
                'nom_espace' => $this->tenant->nom_espace,
                'statut' => $this->tenant->statut,
            ]),
        ];
    }
}
