<?php

namespace App\Http\Resources\Api\V1\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendrierPeriodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calendrier_scolaire_id' => $this->calendrier_scolaire_id,
            'code' => $this->code,
            'nom' => $this->nom,
            'type_periode' => $this->type_periode,
            'cycle_scolaire' => $this->cycle_scolaire,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'ordre' => $this->ordre,
            'saisie_notes_ouverte' => (bool) $this->saisie_notes_ouverte,
            'periode_active' => (bool) $this->periode_active,
        ];
    }
}
