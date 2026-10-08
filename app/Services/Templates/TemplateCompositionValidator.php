<?php

namespace App\Services\Templates;

use App\Models\SystemTemplateBulletin;
use App\Models\SystemTemplateMaximum;
use Illuminate\Support\Collection;

final class TemplateCompositionValidator
{
    /**
     * Retourne la liste des bloqueurs empêchant la validation d’une configuration.
     *
     * @return array<int, string>
     */
    public function blockers(SystemTemplateBulletin $template): array
    {
        $template->loadMissing(['nodes', 'periodes', 'maxima']);

        $blockers = [];

        if ($template->nodes->isEmpty()) {
            $blockers[] = 'La structure ne contient aucun nœud.';
        }

        $matiereNodes = $template->nodes->where('type_node', 'MATIERE');

        if ($matiereNodes->isEmpty()) {
            $blockers[] = 'Au moins une matière doit être présente dans la structure.';
        }

        $activeSaisissable = $template->periodes
            ->where('actif', true)
            ->where('saisissable', true);

        if ($template->periodes->isEmpty()) {
            $blockers[] = 'Aucune période n’est définie.';
        } elseif ($activeSaisissable->isEmpty()) {
            $blockers[] = 'Au moins une période saisissable et active est requise.';
        }

        $maximaByNodePeriode = $template->maxima->keyBy(
            fn ($maximum): string => $maximum->node_id.':'.$maximum->periode_id,
        );

        foreach ($matiereNodes as $node) {
            $missing = $activeSaisissable->filter(
                fn ($periode): bool => $this->isSlotUnsatisfied($node->id, $periode->id, $maximaByNodePeriode),
            );

            if ($missing->isNotEmpty()) {
                $blockers[] = sprintf(
                    'Barème incomplet pour la matière « %s » (périodes : %s).',
                    $node->libelle,
                    $missing->pluck('nom')->implode(', '),
                );
            }
        }

        return $blockers;
    }

    /**
     * @param  Collection<int, SystemTemplateMaximum>  $maximaByNodePeriode
     */
    private function isSlotUnsatisfied(int $nodeId, int $periodeId, $maximaByNodePeriode): bool
    {
        $slot = $maximaByNodePeriode->get($nodeId.':'.$periodeId);

        return $slot === null || (float) $slot->maxima <= 0 || (float) $slot->coefficient <= 0;
    }
}
