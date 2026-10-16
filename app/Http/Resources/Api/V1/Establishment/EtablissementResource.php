<?php

namespace App\Http\Resources\Api\V1\Establishment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EtablissementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'nom' => $this->nom,
            'type_etablissement' => $this->type_etablissement,
            'code_interne' => $this->code_interne,
            'actif' => (bool) $this->actif,
        ];
    }
}
