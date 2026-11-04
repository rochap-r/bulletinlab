<?php

namespace App\Http\Resources\Api\V1\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PasswordResetRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'statut' => $this->statut,
            'demandee_at' => $this->created_at?->toISOString(),
            'traitee_at' => $this->traitee_at?->toISOString(),
            'administrateur' => [
                'nom' => $this->utilisateur->nom,
                'postnom' => $this->utilisateur->postnom,
                'prenom' => $this->utilisateur->prenom,
                'telephone' => $this->utilisateur->telephone,
                'email' => $this->utilisateur->email,
            ],
            'tenant' => [
                'id' => $this->tenant->getKey(),
                'nom_espace' => $this->tenant->nom_espace,
                'slug' => $this->tenant->slug,
            ],
            'traitee_par' => $this->when(
                $this->relationLoaded('traiteePar') && $this->traiteePar !== null,
                fn (): array => [
                    'nom' => $this->traiteePar->nom,
                    'postnom' => $this->traiteePar->postnom,
                    'prenom' => $this->traiteePar->prenom,
                    'email' => $this->traiteePar->email,
                ],
            ),
        ];
    }
}
