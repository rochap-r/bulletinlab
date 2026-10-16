<?php

namespace App\Http\Resources\Api\V1\Establishment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MembreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'postnom' => $this->postnom,
            'prenom' => $this->prenom,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance?->format('Y-m-d'),
            'lieu_naissance' => $this->lieu_naissance,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'adresse' => $this->adresse,
            'matricule' => $this->matricule,
            'qualification' => $this->qualification,
            'specialite' => $this->specialite,
            'date_embauche' => $this->date_embauche?->format('Y-m-d'),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->tenantRoleCodes()),
            'actif' => (bool) $this->actif,
        ];
    }
}
