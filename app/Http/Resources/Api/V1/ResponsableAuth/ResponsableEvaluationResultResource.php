<?php

namespace App\Http\Resources\Api\V1\ResponsableAuth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ResponsableEvaluationResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'statut_resultat' => $this->statut_resultat?->value,
            'valeur' => $this->valeur,
            'commentaire_parent' => $this->commentaire_parent,
            'date_saisie' => $this->date_saisie?->toISOString(),
        ];
    }
}
