<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'cycle_id' => $this->cycle_id,
            'degre_id' => $this->degre_id,
            'niveau_id' => $this->niveau_id,
            'option_id' => $this->option_id,
            'system_template_id' => $this->system_template_id,
            'parallele' => $this->parallele,
            'nom' => $this->nom,
            'code' => $this->code,
            'titulaire_id' => $this->titulaire_id,
            'active' => $this->active,
            'cycle' => $this->whenLoaded('cycle', fn () => [
                'id' => $this->cycle->getKey(),
                'code' => $this->cycle->code,
                'nom' => $this->cycle->nom,
            ]),
            'niveau' => $this->whenLoaded('niveau', fn () => [
                'id' => $this->niveau->getKey(),
                'code' => $this->niveau->code,
                'nom' => $this->niveau->nom,
            ]),
            'option' => $this->whenLoaded('option', fn () => $this->option === null ? null : [
                'id' => $this->option->getKey(),
                'code' => $this->option->code,
                'nom' => $this->option->nom,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
