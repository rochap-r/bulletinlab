<?php

namespace App\Http\Resources\Api\V1\PlatformAuth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformUtilisateurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'nom' => $this->nom,
            'postnom' => $this->postnom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'roles' => $this->roles->pluck('code'),
            'actif' => (bool) $this->actif,
        ];
    }
}
