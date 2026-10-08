<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Bulletin;
use App\Models\ModeleMaximum;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

final class BulletinCompositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Bulletin $reportCard */
        $reportCard = $this->resource;
        $enrollment = $reportCard->inscription;
        $student = $enrollment->eleve;
        $class = $reportCard->classe;
        $model = $reportCard->modeleBulletin;
        $notes = $reportCard->notes->keyBy(
            fn (Note $note): string => $this->slotKey(
                $note->node_id,
                $note->periode_id,
            ),
        );

        return [
            'id' => $reportCard->getKey(),
            'decision_statut' => $reportCard->decision_statut,
            'verrouille' => $reportCard->verrouille,
            'total_obtenu' => $reportCard->total_obtenu,
            'total_maxima' => $reportCard->total_maxima,
            'pourcentage' => $reportCard->pourcentage,
            'mention' => $reportCard->mention,
            'rang' => $reportCard->rang,
            'inscription' => [
                'id' => $enrollment->getKey(),
                'numero_inscription' => $enrollment->numero_inscription,
                'statut' => $enrollment->statut,
                'date_inscription' => $enrollment->date_inscription,
                'eleve' => [
                    'id' => $student->getKey(),
                    'num_permanent' => $student->num_permanent,
                    'nom' => $student->nom,
                    'postnom' => $student->postnom,
                    'prenom' => $student->prenom,
                    'sexe' => $student->sexe,
                ],
            ],
            'classe' => [
                'id' => $class->getKey(),
                'nom' => $class->nom,
                'code' => $class->code,
                'annee_scolaire_id' => $class->annee_scolaire_id,
                'active' => $class->active,
            ],
            'modele' => [
                'id' => $model->getKey(),
                'nom' => $model->nom,
                'type_decoupage' => $model->type_decoupage,
                'total_general_maxima' => $model->total_general_maxima,
            ],
            'nodes' => $model->nodes->map(
                fn ($node): array => [
                    'id' => $node->getKey(),
                    'parent_id' => $node->parent_id,
                    'type_node' => $node->type_node,
                    'matiere_id' => $node->matiere_id,
                    'libelle' => $node->libelle,
                    'ordre' => $node->ordre,
                    'afficher_ligne' => (bool) $node->afficher_ligne,
                    'calculer_sous_total' => (bool) $node->calculer_sous_total,
                    'obligatoire' => (bool) $node->obligatoire,
                ],
            )->values(),
            'periodes' => $model->periodes->map(
                fn ($period): array => [
                    'id' => $period->getKey(),
                    'parent_id' => $period->parent_id,
                    'code' => $period->code,
                    'nom' => $period->nom,
                    'type_periode' => $period->type_periode,
                    'ordre' => $period->ordre,
                    'saisissable' => (bool) $period->saisissable,
                    'calculable' => (bool) $period->calculable,
                    'actif' => (bool) $period->actif,
                ],
            )->values(),
            'slots' => $model->maxima->map(
                fn (ModeleMaximum $maximum): array => $this->slot(
                    $maximum,
                    $notes,
                ),
            )->values(),
        ];
    }

    /**
     * @param  Collection<string, Note>  $notes
     * @return array<string, mixed>
     */
    private function slot(
        ModeleMaximum $maximum,
        Collection $notes,
    ): array {
        $note = $notes->get(
            $this->slotKey($maximum->node_id, $maximum->periode_id),
        );

        return [
            'node_id' => $maximum->node_id,
            'periode_id' => $maximum->periode_id,
            'maxima' => $maximum->maxima,
            'coefficient' => $maximum->coefficient,
            'note' => $note instanceof Note
                ? [
                    'id' => $note->getKey(),
                    'lock_version' => $note->lock_version,
                    'statut_resultat' => $note->statut_resultat->value,
                    'valeur' => $note->valeur,
                    'commentaire' => $note->commentaire,
                    'date_saisie' => $note->date_saisie?->toISOString(),
                ]
                : null,
        ];
    }

    private function slotKey(int $nodeId, int $periodId): string
    {
        return "{$nodeId}:{$periodId}";
    }
}
