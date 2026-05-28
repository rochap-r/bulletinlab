<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EncodageProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'matiere' => $this->node->matiere?->libelle ?? $this->node->libelle,
            'enseignant' => $this->enseignant?->nom_complet,
            'total_eleves' => $this->nombre_eleves,
            'notes_encodees' => $this->nombre_notes_saisies,
            'pourcentage' => round($this->pourcentage_avancement, 2),
            'statut' => $this->statut,
            'date_debut' => $this->created_at,
            'date_fin' => $this->derniere_saisie_at,
        ];
    }
}
