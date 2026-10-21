<?php

namespace App\Http\Resources\Api\V1\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendrierEvenementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calendrier_scolaire_id' => $this->calendrier_scolaire_id,
            'code' => $this->code,
            'nom' => $this->nom,
            'type_evenement' => $this->type_evenement,
            'cycle_scolaire' => $this->cycle_scolaire,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'audiences' => $this->audiences,
            'niveau_alerte' => $this->niveau_alerte,
            'delai_alerte_jours' => $this->delai_alerte_jours,
            'description' => $this->description,
        ];
    }
}
