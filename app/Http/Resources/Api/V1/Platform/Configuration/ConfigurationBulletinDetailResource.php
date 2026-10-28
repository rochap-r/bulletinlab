<?php

namespace App\Http\Resources\Api\V1\Platform\Configuration;

use App\Domains\Bulletin\Services\ApercuEleveService;
use App\Models\SystemTemplateBulletin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConfigurationBulletinDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SystemTemplateBulletin $template */
        $template = $this->resource;

        return [
            'id' => $template->id,
            'code' => $template->code,
            'nom' => $template->nom,
            'version' => $template->version,
            'type_decoupage' => $template->type_decoupage,
            'actif' => (bool) $template->actif,
            'statut' => $template->statut?->value,
            'publie_a' => $template->publie_a,
            'total_general_maxima' => $template->total_general_maxima === null
                ? null
                : (float) $template->total_general_maxima,
            'cycle' => $this->reference('cycle'),
            'degre' => $this->reference('degre'),
            'option' => $this->reference('option'),
            'niveau_min' => $this->reference('niveauMin'),
            'niveau_max' => $this->reference('niveauMax'),
            'layout' => $this->reference('layout'),
            'nodes' => $template->nodes->map(
                fn ($node): array => [
                    'id' => $node->id,
                    'parent_id' => $node->parent_id,
                    'type_node' => $node->type_node,
                    'matiere_id' => $node->matiere_id,
                    'libelle' => $node->libelle,
                    'ordre' => $node->ordre,
                    'afficher_ligne' => (bool) $node->afficher_ligne,
                    'calculer_sous_total' => (bool) $node->calculer_sous_total,
                    'obligatoire' => (bool) $node->obligatoire,
                    'matiere' => $node->relationLoaded('matiere') && $node->matiere !== null
                        ? [
                            'id' => $node->matiere->id,
                            'code' => $node->matiere->code,
                            'nom' => $node->matiere->nom,
                        ]
                        : null,
                ],
            )->values(),
            'periodes' => $template->periodes->map(
                fn ($period): array => [
                    'id' => $period->id,
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
            'slots' => $template->maxima->map(
                fn ($maximum): array => [
                    'node_id' => $maximum->node_id,
                    'periode_id' => $maximum->periode_id,
                    'maxima' => $maximum->maxima,
                    'coefficient' => $maximum->coefficient,
                ],
            )->values(),
            'blocs' => $template->blocs->map(
                fn ($templateBloc): array => [
                    'id' => $templateBloc->id,
                    'ordre' => $templateBloc->ordre,
                    'actif' => (bool) $templateBloc->actif,
                    'configuration' => $templateBloc->configuration,
                    'bloc' => $templateBloc->relationLoaded('bloc') && $templateBloc->bloc !== null
                        ? [
                            'id' => $templateBloc->bloc->id,
                            'code' => $templateBloc->bloc->code,
                            'nom' => $templateBloc->bloc->nom,
                            'description' => $templateBloc->bloc->description,
                        ]
                        : null,
                ],
            )->values(),
            'nodes_count' => $template->nodes->count(),
            'periodes_count' => $template->periodes->count(),
            'matieres_count' => $template->nodes
                ->whereNotNull('matiere_id')
                ->unique('matiere_id')
                ->count(),
            'eleve_exemple' => app(ApercuEleveService::class)->forTemplate($template),
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }

    private function reference(string $relation): mixed
    {
        return $this->whenLoaded(
            $relation,
            fn (): mixed => $this->{$relation} === null
                ? null
                : ReferenceResource::make($this->{$relation}),
        );
    }
}
