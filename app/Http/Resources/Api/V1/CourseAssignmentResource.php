<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CourseAssignmentResource extends JsonResource
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
            'classe_id' => $this->classe_id,
            'cours' => $this->whenLoaded('node', fn (): array => [
                'id' => $this->node->id,
                'libelle' => $this->node->libelle,
            ]),
            'enseignant' => $this->whenLoaded('enseignant', fn (): array => [
                'id' => $this->enseignant->id,
                'nom_complet' => $this->enseignant->nom_complet,
            ]),
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'actif' => $this->actif,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
