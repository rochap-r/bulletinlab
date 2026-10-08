<?php

namespace App\Http\Resources\Api\V1\Platform\Referentiel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NiveauResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cycle_id' => $this->cycle_id,
            'degre_id' => $this->degre_id,
            'code' => $this->code,
            'nom' => $this->nom,
            'numero' => $this->numero,
            'ordre' => $this->ordre,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
