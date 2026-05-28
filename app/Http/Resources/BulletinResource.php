<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulletinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'eleve_id' => $this->eleve_id,
            'classe_id' => $this->classe_id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'modele_bulletin_id' => $this->modele_bulletin_id,
            'total_obtenu' => $this->total_obtenu,
            'total_maxima' => $this->total_maxima,
            'pourcentage' => $this->pourcentage,
            'mention' => $this->mention,
            'decision_proposee' => $this->decision_proposee,
            'decision_finale' => $this->decision_finale,
            'rang' => $this->rang,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
