<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ClassCourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeAssignment = $this->attributionsCours->first();

        return [
            'id' => $this->id,
            'matiere_id' => $this->matiere_id,
            'libelle' => $this->libelle,
            'ordre' => $this->ordre,
            'obligatoire' => $this->obligatoire,
            'attribuable' => (bool) $this->attribuable,
            'attribution_active' => $activeAssignment
                ? [
                    'id' => $activeAssignment->id,
                    'enseignant' => [
                        'id' => $activeAssignment->enseignant->id,
                        'nom_complet' => $activeAssignment->enseignant->nom_complet,
                    ],
                    'date_debut' => $activeAssignment->date_debut?->toDateString(),
                    'date_fin' => $activeAssignment->date_fin?->toDateString(),
                ]
                : null,
        ];
    }
}
