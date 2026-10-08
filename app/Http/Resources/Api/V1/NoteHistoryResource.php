<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteHistoryResource extends JsonResource
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
            'ancien_statut_resultat' => $this->ancien_statut_resultat?->value,
            'nouveau_statut_resultat' => $this->nouveau_statut_resultat?->value,
            'ancienne_valeur' => $this->ancienne_valeur,
            'nouvelle_valeur' => $this->nouvelle_valeur,
            'modifie_par' => $this->modifiePar?->nom_complet,
            'modifie_le' => $this->created_at,
            'motif' => $this->motif,
        ];
    }
}
