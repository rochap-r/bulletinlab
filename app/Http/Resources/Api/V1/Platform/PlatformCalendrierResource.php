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
            'annees_scolaires' => $this->whenLoaded('annees', fn () => $this->annees->map(fn ($year): array => [
                'id' => $year->id,
                'libelle' => $year->libelle,
                'date_debut' => $year->date_debut,
                'date_fin' => $year->date_fin,
                'active' => (bool) $year->active,
                'cloturee' => (bool) $year->cloturee,
            ])->values()),
            'profils_cycles' => CalendrierCycleParametreResource::collection($this->whenLoaded('profilsCycles')),
            'periodes' => CalendrierPeriodeResource::collection($this->whenLoaded('periodes')),
            'evenements' => CalendrierEvenementResource::collection($this->whenLoaded('evenements')),
        ];
    }
}
