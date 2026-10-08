<?php

namespace App\Http\Resources\Api\V1\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformSchoolYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calendrier_scolaire_id' => $this->calendrier_scolaire_id,
            'libelle' => $this->libelle,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'active' => (bool) $this->active,
            'cloturee' => (bool) $this->cloturee,
            'calendrier' => PlatformCalendrierResource::make($this->whenLoaded('calendrierScolaire')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
