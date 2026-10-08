<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulletinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inscription_id' => $this->inscription_id,
            'classe_id' => $this->classe_id,
            'modele_bulletin_id' => $this->modele_bulletin_id,
            'total_obtenu' => $this->total_obtenu,
            'total_maxima' => $this->total_maxima,
            'pourcentage' => $this->pourcentage,
            'mention' => $this->mention,
            'decision_proposee' => $this->decision_proposee,
            'decision_statut' => $this->decision_statut,
            'rang' => $this->rang,
            'verrouille' => $this->verrouille,
            'eleve' => $this->when(
                $this->hasLoadedStudent(),
                fn (): array => [
                    'id' => $this->inscription->eleve->id,
                    'num_permanent' => $this->inscription->eleve->num_permanent,
                    'nom_complet' => $this->inscription->eleve->nom_complet,
                ],
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function hasLoadedStudent(): bool
    {
        return $this->relationLoaded('inscription')
            && $this->inscription->relationLoaded('eleve');
    }
}
