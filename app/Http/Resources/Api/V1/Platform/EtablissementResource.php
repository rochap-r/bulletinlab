<?php

namespace App\Http\Resources\Api\V1\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EtablissementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'tenant_id' => $this->tenant_id,
            'nom' => $this->nom,
            'code_ministere' => $this->code_ministere,
            'province_educationnelle' => $this->province_educationnelle,
            'province' => $this->province,
            'ville' => $this->ville,
            'commune_territoire' => $this->commune_territoire,
            'adresse' => $this->adresse,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'actif' => (bool) $this->actif,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
