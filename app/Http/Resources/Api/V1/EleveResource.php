<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EleveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'num_permanent' => $this->num_permanent,
            'nom' => $this->nom,
            'postnom' => $this->postnom,
            'prenom' => $this->prenom,
            'sexe' => $this->sexe,
            'lieu_naissance' => $this->lieu_naissance,
            'date_naissance' => $this->date_naissance,
            'nom_pere' => $this->nom_pere,
            'nom_mere' => $this->nom_mere,
            'telephone_parent' => $this->telephone_parent,
            'adresse' => $this->adresse,
            'actif' => $this->actif,
            'classe' => $this->whenLoaded('activeInscription', function (): ?array {
                $classe = $this->activeInscription?->classe;

                return $classe ? [
                    'id' => $classe->id,
                    'nom' => $classe->nom,
                    'code' => $classe->code,
                ] : null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
