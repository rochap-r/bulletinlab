<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnneeScolaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calendrier_scolaire_id' => $this->calendrier_scolaire_id,
            'libelle' => $this->libelle,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'active' => $this->active,
            'cloturee' => $this->cloturee,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
