<?php

namespace App\Domains\Bulletin\Contracts;

use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ModeleBulletin;

interface ResolveMentionDecisionServiceInterface
{
    /**
     * Resolve mention and decision for a bulletin based on its percentage
     * and the baremes_mentions configured for its cycle/system template/tenant.
     *
     * @return array{mention: ?string, decision: ?string}
     */
    public function resolve(
        Classe $classe,
        ModeleBulletin $model,
        Bulletin $bulletin,
    ): array;
}
