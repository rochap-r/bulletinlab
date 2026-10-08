<?php

namespace App\Http\Requests\Api\V1\Platform\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait PreventsParentCycles
{
    /**
     * Rejette un parent_id qui créerait un cycle (soi-même ou l’un de ses descendants).
     */
    private function rejectParentCycle(string $modelClass, int $selfId, int $templateId, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($selfId >= 1 && $parentId === $selfId) {
            $this->validator->errors()->add(
                'parent_id',
                'Une entrée ne peut pas être son propre parent.',
            );

            return;
        }

        if ($selfId >= 1 && in_array($parentId, $this->descendantIds($modelClass, $selfId, $templateId), true)) {
            $this->validator->errors()->add(
                'parent_id',
                'Une entrée ne peut pas être déplacée sous l’un de ses descendants.',
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(string $modelClass, int $nodeId, int $templateId): array
    {
        /** @var Collection<int, Model> $models */
        $models = $modelClass::query()
            ->where('template_id', $templateId)
            ->get(['id', 'parent_id']);

        $childrenByParent = $models->reduce(function (array $carry, Model $model): array {
            $carry[(int) $model->parent_id][] = (int) $model->id;

            return $carry;
        }, []);

        $descendants = [];
        $queue = $childrenByParent[$nodeId] ?? [];

        while ($queue !== []) {
            $current = array_shift($queue);
            $descendants[] = $current;

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $descendants;
    }
}
