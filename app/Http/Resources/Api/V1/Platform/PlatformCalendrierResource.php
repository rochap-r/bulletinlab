<?php

namespace App\Http\Resources\Api\V1\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformCalendrierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'pays' => $this->pays,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'officiel' => (bool) $this->officiel,
            'actif' => (bool) $this->actif,
            'annees_count' => $this->whenCounted('annees'),
            'utilise' => $this->when(isset($this->annees_count), fn (): bool => $this->annees_count > 0 || (bool) $this->mappings_exists),
            'periodes' => CalendrierPeriodeResource::collection($this->whenLoaded('periodes')),
        ];
    }
}
