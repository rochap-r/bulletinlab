<?php

namespace App\Http\Resources\Api\V1\ResponsableAuth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ResponsableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'nom' => $this->nom,
            'postnom' => $this->postnom,
            'prenom' => $this->prenom,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'doit_changer_mot_de_passe' => (bool) $this->doit_changer_mot_de_passe,
        ];
    }
}
