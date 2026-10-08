<?php

namespace App\Http\Resources\Api\V1\Platform\Referentiel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatiereResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'nom' => $this->nom,
            'nom_court' => $this->nom_court,
            'actif' => (bool) $this->actif,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
