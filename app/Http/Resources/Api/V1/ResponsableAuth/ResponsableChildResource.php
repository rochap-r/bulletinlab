<?php

namespace App\Http\Resources\Api\V1\ResponsableAuth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ResponsableChildResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $student = $this->eleve;
        $school = $student?->etablissement;
        $enrollment = $student?->activeInscription;
        $classroom = $enrollment?->classe;

        return [
            'lien_public_id' => $this->public_id,
            'type_lien' => $this->type_lien?->value,
            'principal' => (bool) $this->principal,
            'permissions' => [
                'notes' => (bool) $this->peut_consulter_notes,
                'presences' => (bool) $this->peut_consulter_presences,
                'notifications' => (bool) $this->recevoir_notifications,
            ],
            'enfant' => [
                'public_id' => $student?->public_id,
                'nom' => $student?->nom,
                'postnom' => $student?->postnom,
                'prenom' => $student?->prenom,
                'sexe' => $student?->sexe,
            ],
            'ecole' => [
                'public_id' => $school?->public_id,
                'nom' => $school?->nom,
                'type' => $school?->type_etablissement,
                'espace' => $this->tenant?->nom_espace,
                'slug' => $this->tenant?->slug,
            ],
            'inscription' => $enrollment ? [
                'public_id' => $enrollment->public_id,
                'classe' => $classroom ? [
                    'public_id' => $classroom->public_id,
                    'nom' => $classroom->nom,
                    'code' => $classroom->code,
                ] : null,
            ] : null,
        ];
    }
}
