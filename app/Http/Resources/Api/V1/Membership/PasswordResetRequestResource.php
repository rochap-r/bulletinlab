<?php

namespace App\Http\Resources\Api\V1\Membership;

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
            'utilisateur' => [
                'nom' => $this->utilisateur->nom,
                'postnom' => $this->utilisateur->postnom,
                'prenom' => $this->utilisateur->prenom,
                'telephone' => $this->utilisateur->telephone,
                'etablissement' => $this->utilisateur->etablissement?->nom,
            ],
        ];
    }
}
