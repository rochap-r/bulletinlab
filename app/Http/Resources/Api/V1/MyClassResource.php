<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MyClassResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'nom' => $this->resource->nom,
            'code' => $this->resource->code,
            'est_titulaire' => $this->resource->titulaire_id === $request->user()?->getKey(),
            'annee_scolaire' => [
                'id' => $this->resource->anneeScolaire->id,
                'libelle' => $this->resource->anneeScolaire->libelle,
            ],
            'cours' => $this->resource->attributionsCours
                ->map(fn ($attribution): array => [
                    'attribution_id' => $attribution->id,
                    'id' => $attribution->node->id,
                    'matiere_id' => $attribution->node->matiere_id,
                    'libelle' => $attribution->node->libelle,
                    'ordre' => $attribution->node->ordre,
                ])
                ->sortBy('ordre')
                ->values()
                ->all(),
        ];
    }
}
