<?php

namespace App\Http\Resources\Api\V1\Evaluation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TypeEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'code' => $this->code,
            'nom' => $this->nom,
            'description' => $this->description,
            'actif' => (bool) $this->actif,
        ];
    }
}
