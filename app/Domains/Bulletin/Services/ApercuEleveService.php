<?php

namespace App\Domains\Bulletin\Services;

use App\Models\Bulletin;
use App\Models\ModeleBulletin;
use App\Models\SystemTemplateBulletin;

/**
 * Résout l'élève d'exemple affiché dans l'aperçu d'une configuration de bulletin.
 *
 * La configuration est un modèle system ; les notes vivent sur le modèle de
 * classe qui en est cloné. Ce service retrouve ce clone, choisit un bulletin et
 * remappe les identifiants `modele_*` vers ceux du modèle system (`system_node_id`
 * / `system_periode_id`) afin que l'aperçu puisse les afficher.
 */
final class ApercuEleveService
{
    /**
     * @return array<string, mixed>|null
     */
    public function forTemplate(SystemTemplateBulletin $template): ?array
    {
        $modele = ModeleBulletin::query()
            ->where('system_template_id', $template->id)
            ->whereHas('bulletins')
            ->with(['nodes', 'periodes'])
            ->first();

        if ($modele === null) {
            return null;
        }

        $bulletin = Bulletin::query()
            ->where('modele_bulletin_id', $modele->id)
            ->with(['inscription.eleve', 'inscription.classe.etablissement', 'inscription.anneeScolaire', 'notes'])
            ->orderBy('id')
            ->first();

        if ($bulletin === null) {
            return null;
        }

        $nodeIds = $modele->nodes
            ->filter(fn ($node): bool => $node->system_node_id !== null)
            ->pluck('system_node_id', 'id');

        $periodeIds = $modele->periodes
            ->filter(fn ($periode): bool => $periode->system_periode_id !== null)
            ->pluck('system_periode_id', 'id');

        $notes = [];

        foreach ($bulletin->notes as $note) {
            $nodeId = $nodeIds->get($note->node_id);
            $periodeId = $periodeIds->get($note->periode_id);

            if ($nodeId === null || $periodeId === null) {
                continue;
            }

            $notes[] = [
                'node_id' => (int) $nodeId,
                'periode_id' => (int) $periodeId,
                'valeur' => $note->valeur,
                'statut' => $note->statut_resultat?->value,
            ];
        }

        $inscription = $bulletin->inscription;
        $eleve = $inscription?->eleve;
        $classe = $inscription?->classe;
        $etablissement = $classe?->etablissement;

        return [
            'eleve' => [
                'nom' => $eleve?->nom,
                'postnom' => $eleve?->postnom,
                'prenom' => $eleve?->prenom,
                'sexe' => $eleve?->sexe,
                'lieu_naissance' => $eleve?->lieu_naissance,
                'date_naissance' => $eleve?->date_naissance,
                'num_permanent' => $eleve?->num_permanent,
            ],
            'classe' => $classe?->nom,
            'annee_scolaire' => $inscription?->anneeScolaire?->libelle,
            'ecole' => $etablissement?->nom,
            'code_ecole' => $etablissement?->code_ministere,
            'province' => $etablissement?->province_educationnelle,
            'ville' => $etablissement?->ville,
            'commune' => $etablissement?->commune_territoire,
            'notes' => $notes,
        ];
    }
}
