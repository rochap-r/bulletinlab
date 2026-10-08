<?php

namespace App\Domains\Bulletin\Contracts;

use App\Models\Classe;
use App\Models\ModelePeriode;

interface RankBulletinsServiceInterface
{
    /**
     * Compute and persist ranks for all bulletins of a class for a given period.
     * Returns an array mapping bulletin_id => rank.
     *
     * @return array<int, int>
     */
    public function rank(
        Classe $classe,
        ModelePeriode $period,
    ): array;
}
