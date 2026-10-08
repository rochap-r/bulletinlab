<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eleve_id' => $this->eleve_id,
            'classe_id' => $this->classe_id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'numero_inscription' => $this->numero_inscription,
            'statut' => $this->statut,
            'date_inscription' => $this->date_inscription,
            'eleve' => EleveResource::make($this->whenLoaded('eleve')),
            'bulletin' => BulletinResource::make($this->whenLoaded('bulletin')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
