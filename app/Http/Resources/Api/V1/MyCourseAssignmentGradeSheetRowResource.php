<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MyCourseAssignmentGradeSheetRowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $student = $this->resource->eleve;
        $note = $this->resource->bulletin->notes->first();

        return [
            'inscription' => [
                'id' => $this->resource->getKey(),
                'numero_inscription' => $this->resource->numero_inscription,
                'eleve' => [
                    'id' => $student->getKey(),
                    'num_permanent' => $student->num_permanent,
                    'nom' => $student->nom,
                    'postnom' => $student->postnom,
                    'prenom' => $student->prenom,
                    'sexe' => $student->sexe,
                ],
            ],
            'note' => $note instanceof Note
                ? [
                    'id' => $note->getKey(),
                    'lock_version' => $note->lock_version,
                    'statut_resultat' => $note->statut_resultat->value,
                    'valeur' => $note->valeur,
                    'origine' => $note->origine->value,
                    'commentaire' => $note->commentaire,
                    'date_saisie' => $note->date_saisie?->toISOString(),
                ]
                : null,
        ];
    }
}
