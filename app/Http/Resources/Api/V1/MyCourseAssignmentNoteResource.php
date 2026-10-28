<?php

namespace App\Http\Resources\Api\V1;

use App\Domains\Teacher\DTOs\UpsertedCourseAssignmentNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MyCourseAssignmentNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UpsertedCourseAssignmentNote $result */
        $result = $this->resource;
        $enrollment = $result->note->bulletin->inscription;
        $student = $enrollment->eleve;

        return [
            'inscription' => [
                'id' => $enrollment->getKey(),
                'numero_inscription' => $enrollment->numero_inscription,
                'eleve' => [
                    'id' => $student->getKey(),
                    'num_permanent' => $student->num_permanent,
                    'nom' => $student->nom,
                    'postnom' => $student->postnom,
                    'prenom' => $student->prenom,
                    'sexe' => $student->sexe,
                ],
            ],
            'creneau_id' => $result->entrySlotId,
            'note' => [
                'id' => $result->note->getKey(),
                'lock_version' => $result->note->lock_version,
                'statut_resultat' => $result->note->statut_resultat->value,
                'valeur' => $result->note->valeur,
                'origine' => $result->note->origine->value,
                'commentaire' => $result->note->commentaire,
                'date_saisie' => $result->note->date_saisie?->toISOString(),
            ],
        ];
    }
}
