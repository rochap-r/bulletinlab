<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Contracts\ResolveMentionDecisionServiceInterface;
use App\Models\BaremeMention;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ModeleBulletin;

final class ResolveMentionDecisionService implements ResolveMentionDecisionServiceInterface
{
    public function resolve(
        Classe $classe,
        ModeleBulletin $model,
        Bulletin $bulletin,
    ): array {
        if ($bulletin->pourcentage === null) {
            return ['mention' => null, 'decision' => null];
        }

        $percentage = (float) $bulletin->pourcentage;

        $bareme = BaremeMention::query()
            ->where('tenant_id', $classe->tenant_id)
            ->where('cycle_id', $classe->cycle_id)
            ->where('system_template_id', $model->system_template_id)
            ->where('actif', true)
            ->where('min_pourcentage', '<=', $percentage)
            ->where('max_pourcentage', '>=', $percentage)
            ->orderBy('ordre')
            ->first();

        if ($bareme === null) {
            return ['mention' => null, 'decision' => null];
        }

        return [
            'mention' => $bareme->mention,
            'decision' => $bareme->decision_defaut,
        ];
    }
}
