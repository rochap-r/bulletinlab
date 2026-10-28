<?php

namespace App\Http\Resources\Api\V1\Evaluation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ResultatEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resultat = $this->resource;
        $eleve = $resultat->eleve;

        return [
            'id' => $resultat->getKey(),
            'inscription_id' => $resultat->inscription_id,
            'eleve_id' => $resultat->eleve_id,
            'eleve' => $eleve ? [
                'id' => $eleve->getKey(),
                'nom' => $eleve->nom,
                'postnom' => $eleve->postnom,
                'prenom' => $eleve->prenom,
            ] : null,
            'statut_resultat' => $resultat->statut_resultat?->value,
            'valeur' => $resultat->valeur,
            'commentaire_parent' => $resultat->commentaire_parent,
            'commentaire_interne' => $resultat->commentaire_interne,
            'date_saisie' => $resultat->date_saisie?->toISOString(),
        ];
    }
}
