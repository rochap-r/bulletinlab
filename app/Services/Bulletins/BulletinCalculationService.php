<?php

namespace App\Services\Bulletins;

use App\Models\Bulletin;
use App\Models\BaremeMention;

class BulletinCalculationService
{
    public function recalculate(Bulletin $bulletin): Bulletin
    {
        $bulletin->load(['modeleBulletin.nodes','modeleBulletin.periodes','modeleBulletin.maxima','notes']);

        // Version de base. À enrichir avec calculation_rules pour les sous-totaux par domaine/groupe.
        $totalObtenu = $bulletin->notes()->sum('valeur');
        $totalMaxima = $bulletin->modeleBulletin->total_general_maxima;

        $bulletin->total_obtenu = $totalObtenu;
        $bulletin->total_maxima = $totalMaxima;

        if ($totalMaxima && $totalMaxima > 0) {
            $bulletin->pourcentage = round(($totalObtenu / $totalMaxima) * 100, 2);
        }

        $mention = $this->resolveMention($bulletin);
        if ($mention) {
            $bulletin->mention = $mention->mention;
            $bulletin->decision_proposee = $mention->decision_defaut;
        }

        $bulletin->save();

        return $bulletin;
    }

    protected function resolveMention(Bulletin $bulletin): ?BaremeMention
    {
        if ($bulletin->pourcentage === null) return null;

        return BaremeMention::query()
            ->where('actif', true)
            ->where('min_pourcentage', '<=', $bulletin->pourcentage)
            ->where('max_pourcentage', '>=', $bulletin->pourcentage)
            ->orderByRaw('tenant_id IS NULL')
            ->first();
    }
}
