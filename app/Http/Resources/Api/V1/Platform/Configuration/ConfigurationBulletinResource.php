<?php

namespace App\Http\Resources\Api\V1\Platform\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConfigurationBulletinResource extends JsonResource
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
            'version' => $this->version,
            'type_decoupage' => $this->type_decoupage,
            'actif' => (bool) $this->actif,
            'statut' => $this->statut?->value,
            'publie_a' => $this->publie_a,
            'total_general_maxima' => $this->total_general_maxima === null
                ? null
                : (float) $this->total_general_maxima,
            'cycle' => $this->reference('cycle'),
            'degre' => $this->reference('degre'),
            'option' => $this->reference('option'),
            'niveau_min' => $this->reference('niveauMin'),
            'niveau_max' => $this->reference('niveauMax'),
            'layout' => $this->reference('layout'),
            'nodes_count' => $this->whenCounted('nodes'),
            'periodes_count' => $this->whenCounted('periodes'),
            'matieres_count' => $this->whenCounted('matieres_count'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function reference(string $relation): mixed
    {
        return $this->whenLoaded(
            $relation,
            fn (): mixed => $this->{$relation} === null
                ? null
                : ReferenceResource::make($this->{$relation}),
        );
    }
}
