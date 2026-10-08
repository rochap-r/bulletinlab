<?php

namespace App\Http\Resources\Api\V1\Platform\Referentiel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CycleResource extends JsonResource
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
            'ordre' => $this->ordre,
            'actif' => (bool) $this->actif,
            'degres' => DegreResource::collection($this->whenLoaded('degres')),
            'niveaux' => NiveauResource::collection($this->whenLoaded('niveaux')),
            'options' => OptionResource::collection($this->whenLoaded('options')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
