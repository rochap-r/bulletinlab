<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MyCourseAssignmentResource extends JsonResource
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
            'classe' => $this->whenLoaded('classe', fn (): array => [
                'id' => $this->classe->id,
                'nom' => $this->classe->nom,
                'code' => $this->classe->code,
            ]),
            'annee_scolaire' => $this->whenLoaded(
                'classe',
                fn (): array => [
                    'id' => $this->classe->anneeScolaire->id,
                    'libelle' => $this->classe->anneeScolaire->libelle,
                ],
            ),
            'cours' => $this->whenLoaded('node', fn (): array => [
                'id' => $this->node->id,
                'matiere_id' => $this->node->matiere_id,
                'libelle' => $this->node->libelle,
                'ordre' => $this->node->ordre,
            ]),
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'actif' => $this->actif,
        ];
    }
}
