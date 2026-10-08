<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OuvertureAnnuelleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'etablissement_id' => $this->etablissement_id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'statut' => $this->statut,
            'activee_at' => $this->activee_at?->toISOString(),
            'activee_par' => $this->activee_par,
            'cloturee_at' => $this->cloturee_at?->toISOString(),
            'cloturee_par' => $this->cloturee_par,
            'annee_scolaire' => SchoolYearResource::make(
                $this->whenLoaded('anneeScolaire'),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
