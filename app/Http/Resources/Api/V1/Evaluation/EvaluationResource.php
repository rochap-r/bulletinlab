<?php

namespace App\Http\Resources\Api\V1\Evaluation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $periode = $this->whenLoaded('periode');
        $node = $this->whenLoaded('node');
        $classe = $this->whenLoaded('classe');

        return [
            'id' => $this->getKey(),
            'libelle' => $this->libelle,
            'maxima' => $this->maxima,
            'coefficient' => $this->coefficient,
            'date_evaluation' => $this->date_evaluation?->toISOString(),
            'statut' => $this->statut?->value,
            'visible_parents_at' => $this->visible_parents_at?->toISOString(),
            'cree_par' => $this->cree_par,
            'type_evaluation' => TypeEvaluationResource::make($this->whenLoaded('typeEvaluation')),
            'periode' => $periode ? [
                'id' => $periode->getKey(),
                'code' => $periode->code,
                'nom' => $periode->nom,
                'type_periode' => $periode->type_periode,
            ] : null,
            'node' => $node ? [
                'id' => $node->getKey(),
                'libelle' => $node->libelle,
                'type_node' => $node->type_node,
                'matiere_id' => $node->matiere_id,
            ] : null,
            'classe' => $classe ? [
                'id' => $classe->getKey(),
                'nom' => $classe->nom,
                'code' => $classe->code,
            ] : null,
            'resultats' => ResultatEvaluationResource::collection($this->whenLoaded('resultats')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
