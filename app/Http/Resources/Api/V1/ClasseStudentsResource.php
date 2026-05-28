<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClasseStudentsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'classe_id' => $this->id,
            'classe_nom' => $this->nom_complet,
            'students' => $this->inscriptions->map(function ($inscription) {
                return [
                    'eleve_id' => $inscription->eleve->id,
                    'nom_complet' => $inscription->eleve->nom_complet,
                    'matricule' => $inscription->eleve->matricule,
                    'note' => $inscription->eleve->notes->first()?->note,
                    'note_id' => $inscription->eleve->notes->first()?->id,
                    'maximum' => $inscription->eleve->notes->first()?->maximum,
                    'encoded_at' => $inscription->eleve->notes->first()?->updated_at,
                ];
            }),
        ];
    }
}
