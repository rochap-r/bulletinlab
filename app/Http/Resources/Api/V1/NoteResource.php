<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bulletin_id' => $this->bulletin_id,
            'node_id' => $this->node_id,
            'periode_id' => $this->periode_id,
            'lock_version' => $this->lock_version,
            'statut_resultat' => $this->statut_resultat->value,
            'valeur' => $this->valeur,
            'commentaire' => $this->commentaire,
            'date_saisie' => $this->date_saisie,
            'eleve' => $this->when(
                $this->hasLoadedStudent(),
                fn (): array => [
                    'id' => $this->bulletin->inscription->eleve->id,
                    'num_permanent' => $this->bulletin->inscription->eleve->num_permanent,
                    'nom_complet' => $this->bulletin->inscription->eleve->nom_complet,
                ],
            ),
            'cours' => $this->whenLoaded('node', fn (): array => [
                'id' => $this->node->id,
                'libelle' => $this->node->libelle,
            ]),
            'periode' => $this->whenLoaded('periode', fn (): array => [
                'id' => $this->periode->id,
                'code' => $this->periode->code,
                'nom' => $this->periode->nom,
            ]),
            'saisi_par' => $this->whenLoaded(
                'saisiPar',
                fn (): ?string => $this->saisiPar?->nom_complet,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function hasLoadedStudent(): bool
    {
        return $this->relationLoaded('bulletin')
            && $this->bulletin->relationLoaded('inscription')
            && $this->bulletin->inscription->relationLoaded('eleve');
    }
}
