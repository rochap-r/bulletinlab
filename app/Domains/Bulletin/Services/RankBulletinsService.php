<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Contracts\RankBulletinsServiceInterface;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ModelePeriode;

final class RankBulletinsService implements RankBulletinsServiceInterface
{
    public function rank(
        Classe $classe,
        ModelePeriode $period,
    ): array {
        $bulletins = Bulletin::query()
            ->where('classe_id', $classe->getKey())
            ->where('tenant_id', $classe->tenant_id)
            ->whereNotNull('pourcentage')
            ->orderByDesc('pourcentage')
            ->orderBy('id')
            ->get();

        if ($bulletins->isEmpty()) {
            return [];
        }

        $totalStudents = $bulletins->count();
        $rankings = [];
        $currentRank = 1;

        foreach ($bulletins->groupBy(fn (Bulletin $b): string => (string) $b->pourcentage) as $group) {
            foreach ($group as $bulletin) {
                $rankings[$bulletin->getKey()] = $currentRank;
            }

            $currentRank += $group->count();
        }

        foreach ($rankings as $bulletinId => $rank) {
            Bulletin::query()
                ->whereKey($bulletinId)
                ->where('tenant_id', $classe->tenant_id)
                ->lockForUpdate()
                ->update([
                    'rang' => $rank,
                    'nombre_eleves_classe' => $totalStudents,
                ]);
        }

        return $rankings;
    }
}
