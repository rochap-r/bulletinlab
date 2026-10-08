<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roots = DB::table('system_template_nodes')
            ->whereNull('parent_id')
            ->orderBy('template_id')
            ->orderBy('ordre')
            ->orderBy('id')
            ->get();

        $roots
            ->groupBy(fn (object $node): string => $node->template_id.'-'.$node->ordre)
            ->each(function (object $group): void {
                $duplicateIds = $group
                    ->reject(fn (object $node): bool => $node->id === $this->keepNodeId($group)->id)
                    ->pluck('id');

                foreach ($duplicateIds as $duplicateId) {
                    DB::table('system_template_nodes')->where('id', $duplicateId)->delete();
                }
            });
    }

    public function down(): void
    {
        // Les nœuds dupliqués sont supprimés en cascade avec leurs enfants et leurs
        // maxima. Leur restauration exigerait de rejouer le seeder, dont on ne peut pas
        // garantir l'état historique.
    }

    /**
     * @param  Collection<int, object>  $group
     */
    private function keepNodeId(object $group): object
    {
        foreach ($group as $node) {
            $ids = $this->subtreeIds($node->id);

            if (DB::table('modele_nodes')->whereIn('system_node_id', $ids)->exists()) {
                return $node;
            }
        }

        return $group->first();
    }

    /**
     * @return array<int, int>
     */
    private function subtreeIds(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $children = DB::table('system_template_nodes')
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            $frontier = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
};
