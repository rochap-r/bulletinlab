<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'eleve' => $this->inscription->eleve->nom_complet,
            'eleve_id' => $this->inscription->eleve->id,
            'date_presence' => $this->date_presence,
            'moment' => $this->moment,
            'statut' => $this->statut,
            'observation' => $this->observation,
            'saisi_par' => $this->saisiPar?->nom_complet,
            'saisi_le' => $this->created_at,
        ];
    }
}
