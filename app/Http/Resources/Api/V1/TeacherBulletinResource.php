<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Bulletin;
use App\Models\ModeleMaximum;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

final class TeacherBulletinResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Bulletin $bulletin */
        $bulletin = $this->resource;
        $model = $bulletin->modeleBulletin;
        $template = $model->systemTemplate;
        $enrollment = $bulletin->inscription;
        $student = $enrollment->eleve;
        $class = $bulletin->classe;
        $establishment = $class->etablissement;
        $notes = $bulletin->notes->keyBy(
            fn (Note $note): string => $this->slotKey(
                $note->node_id,
                $note->periode_id,
            ),
        );

        return [
            'bulletin' => [
                'id' => $bulletin->getKey(),
                'inscription_id' => $enrollment->getKey(),
                'decision_statut' => $bulletin->decision_statut,
                'verrouille' => (bool) $bulletin->verrouille,
                'total_obtenu' => $bulletin->total_obtenu,
                'total_maxima' => $bulletin->total_maxima,
                'pourcentage' => $bulletin->pourcentage,
                'mention' => $bulletin->mention,
                'rang' => $bulletin->rang,
            ],
            'configuration' => [
                'id' => $model->getKey(),
                'code' => $template?->code ?? 'BULLETIN_CLASSE',
                'nom' => $model->nom,
                'version' => $template?->version,
                'type_decoupage' => $model->type_decoupage,
                'actif' => (bool) $model->actif,
                'statut' => $template?->statut?->value,
                'publie_a' => $template?->publie_a,
                'total_general_maxima' => $model->total_general_maxima,
                'cycle' => $this->reference($class->cycle),
                'degre' => null,
                'option' => $this->reference($class->option),
                'niveau_min' => $this->reference($class->niveau),
                'niveau_max' => $this->reference($class->niveau),
                'layout' => $this->reference($model->layout),
                'nodes' => $model->nodes->map(fn ($node): array => [
                    'id' => $node->getKey(),
                    'parent_id' => $node->parent_id,
                    'type_node' => $node->type_node,
                    'matiere_id' => $node->matiere_id,
                    'libelle' => $node->libelle,
                    'ordre' => $node->ordre,
                    'afficher_ligne' => (bool) $node->afficher_ligne,
                    'calculer_sous_total' => (bool) $node->calculer_sous_total,
                    'obligatoire' => (bool) $node->obligatoire,
                    'matiere' => $this->reference($node->matiere),
                ])->values(),
                'periodes' => $model->periodes->map(fn ($period): array => [
                    'id' => $period->getKey(),
                    'parent_id' => $period->parent_id,
                    'code' => $period->code,
                    'nom' => $period->nom,
                    'type_periode' => $period->type_periode,
                    'ordre' => $period->ordre,
                    'saisissable' => (bool) $period->saisissable,
                    'calculable' => (bool) $period->calculable,
                    'actif' => (bool) $period->actif,
                ])->values(),
                'slots' => $model->maxima->map(
                    fn (ModeleMaximum $maximum): array => [
                        'node_id' => $maximum->node_id,
                        'periode_id' => $maximum->periode_id,
                        'maxima' => $maximum->maxima,
                        'coefficient' => $maximum->coefficient,
                    ],
                )->values(),
                'blocs' => $model->blocs->map(fn ($modelBlock): array => [
                    'id' => $modelBlock->getKey(),
                    'ordre' => $modelBlock->ordre,
                    'actif' => (bool) $modelBlock->actif,
                    'configuration' => $modelBlock->configuration,
                    'bloc' => $modelBlock->bloc === null ? null : [
                        'id' => $modelBlock->bloc->getKey(),
                        'code' => $modelBlock->bloc->code,
                        'nom' => $modelBlock->bloc->nom,
                        'description' => $modelBlock->bloc->description,
                    ],
                ])->values(),
                'nodes_count' => $model->nodes->count(),
                'periodes_count' => $model->periodes->count(),
                'matieres_count' => $model->nodes
                    ->whereNotNull('matiere_id')
                    ->unique('matiere_id')
                    ->count(),
                'eleve_exemple' => [
                    'eleve' => [
                        'nom' => $student->nom,
                        'postnom' => $student->postnom,
                        'prenom' => $student->prenom,
                        'sexe' => $student->sexe,
                        'lieu_naissance' => $student->lieu_naissance,
                        'date_naissance' => $student->date_naissance,
                        'num_permanent' => $student->num_permanent,
                    ],
                    'classe' => $class->nom,
                    'annee_scolaire' => $enrollment->anneeScolaire?->libelle,
                    'ecole' => $establishment?->nom,
                    'code_ecole' => $establishment?->code_ministere,
                    'province' => $establishment?->province_educationnelle,
                    'ville' => $establishment?->ville,
                    'commune' => $establishment?->commune_territoire,
                    'notes' => $this->studentNotes($model->maxima, $notes),
                ],
                'created_at' => $model->created_at,
                'updated_at' => $model->updated_at,
            ],
        ];
    }

    /**
     * @param  Collection<int, ModeleMaximum>  $maxima
     * @param  Collection<string, Note>  $notes
     * @return list<array<string, mixed>>
     */
    private function studentNotes(Collection $maxima, Collection $notes): array
    {
        return $maxima->map(function (ModeleMaximum $maximum) use ($notes): ?array {
            $note = $notes->get($this->slotKey(
                $maximum->node_id,
                $maximum->periode_id,
            ));

            if (! $note instanceof Note) {
                return null;
            }

            return [
                'node_id' => $note->node_id,
                'periode_id' => $note->periode_id,
                'valeur' => $note->valeur,
                'statut' => $note->statut_resultat->value,
            ];
        })->filter()->values()->all();
    }

    /** @return array{id: int, code: string|null, nom: string}|null */
    private function reference(mixed $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return [
            'id' => (int) $model->getKey(),
            'code' => $model->code ?? null,
            'nom' => (string) ($model->nom ?? $model->code ?? ''),
        ];
    }

    private function slotKey(int $nodeId, int $periodId): string
    {
        return "{$nodeId}:{$periodId}";
    }
}
