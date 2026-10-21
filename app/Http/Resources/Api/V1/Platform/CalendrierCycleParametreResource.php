<?php

namespace App\Http\Resources\Api\V1\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendrierCycleParametreResource extends JsonResource
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
            'calendrier_scolaire_id' => $this->calendrier_scolaire_id,
            'cycle_scolaire' => $this->cycle_scolaire,
            'mode_decoupage' => $this->mode_decoupage,
            'jours_ouvrables_attendus' => $this->jours_ouvrables_attendus,
            'heures_minimales' => $this->heures_minimales,
            'jours_classe' => $this->jours_classe,
        ];
    }
}
