<?php

namespace App\Services\Templates;

use App\Models\Classe;
use App\Models\ModeleBulletin;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\ModeleMaximum;
use App\Models\ModeleBloc;
use Illuminate\Support\Facades\DB;

class CloneSystemTemplateService
{
    public function cloneForClasse(Classe $classe): ModeleBulletin
    {
        return DB::transaction(function () use ($classe) {
            $template = $classe->systemTemplate()->with(['nodes','periodes','maxima','blocs'])->firstOrFail();

            $modele = ModeleBulletin::create([
                'tenant_id' => $classe->tenant_id,
                'classe_id' => $classe->id,
                'annee_scolaire_id' => $classe->annee_scolaire_id,
                'system_template_id' => $template->id,
                'layout_id' => $template->layout_id,
                'nom' => $template->nom.' - '.$classe->nom,
                'type_decoupage' => $template->type_decoupage,
                'total_general_maxima' => $template->total_general_maxima,
                'verrouille' => false,
                'actif' => true,
            ]);

            $nodeMap = [];
            foreach ($template->nodes as $node) {
                $modeleNode = ModeleNode::create([
                    'tenant_id' => $classe->tenant_id,
                    'modele_bulletin_id' => $modele->id,
                    'system_node_id' => $node->id,
                    'parent_id' => null,
                    'type_node' => $node->type_node,
                    'matiere_id' => $node->matiere_id,
                    'libelle' => $node->libelle,
                    'ordre' => $node->ordre,
                    'afficher_ligne' => $node->afficher_ligne,
                    'calculer_sous_total' => $node->calculer_sous_total,
                    'obligatoire' => $node->obligatoire,
                    'metadata' => $node->metadata,
                ]);
                $nodeMap[$node->id] = $modeleNode->id;
            }

            foreach ($template->nodes as $node) {
                if ($node->parent_id && isset($nodeMap[$node->parent_id])) {
                    ModeleNode::where('id', $nodeMap[$node->id])->update([
                        'parent_id' => $nodeMap[$node->parent_id],
                    ]);
                }
            }

            $periodeMap = [];
            foreach ($template->periodes as $periode) {
                $modelePeriode = ModelePeriode::create([
                    'tenant_id' => $classe->tenant_id,
                    'modele_bulletin_id' => $modele->id,
                    'system_periode_id' => $periode->id,
                    'parent_id' => null,
                    'code' => $periode->code,
                    'nom' => $periode->nom,
                    'type_periode' => $periode->type_periode,
                    'ordre' => $periode->ordre,
                    'saisissable' => $periode->saisissable,
                    'calculable' => $periode->calculable,
                    'actif' => $periode->actif,
                ]);
                $periodeMap[$periode->id] = $modelePeriode->id;
            }

            foreach ($template->periodes as $periode) {
                if ($periode->parent_id && isset($periodeMap[$periode->parent_id])) {
                    ModelePeriode::where('id', $periodeMap[$periode->id])->update([
                        'parent_id' => $periodeMap[$periode->parent_id],
                    ]);
                }
            }

            foreach ($template->maxima as $maximum) {
                if (isset($nodeMap[$maximum->node_id]) && isset($periodeMap[$maximum->periode_id])) {
                    ModeleMaximum::create([
                        'tenant_id' => $classe->tenant_id,
                        'modele_bulletin_id' => $modele->id,
                        'node_id' => $nodeMap[$maximum->node_id],
                        'periode_id' => $periodeMap[$maximum->periode_id],
                        'maxima' => $maximum->maxima,
                        'coefficient' => $maximum->coefficient,
                    ]);
                }
            }

            foreach ($template->blocs as $bloc) {
                ModeleBloc::create([
                    'tenant_id' => $classe->tenant_id,
                    'modele_bulletin_id' => $modele->id,
                    'bloc_id' => $bloc->bloc_id,
                    'ordre' => $bloc->ordre,
                    'actif' => $bloc->actif,
                    'configuration' => $bloc->configuration,
                ]);
            }

            return $modele;
        });
    }
}
