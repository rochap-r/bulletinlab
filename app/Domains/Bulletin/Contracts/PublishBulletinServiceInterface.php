<?php

namespace App\Domains\Bulletin\Contracts;

use App\Domains\Bulletin\DTOs\PublishBulletinResult;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ModelePeriode;
use App\Models\Utilisateur;

interface PublishBulletinServiceInterface
{
    /**
     * Publish calculation results for a single bulletin + period.
     * Persists an immutable calculation snapshot and an official publication.
     * Idempotent: re-publishing unchanged results reuses the same snapshot and
     * publication and writes no new audit entry.
     */
    public function publish(
        Bulletin $bulletin,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
    ): PublishBulletinResult;

    /**
     * Rectify an already published result: forces a new immutable snapshot and
     * a new publication, marks the current publication as RECTIFIEE and records
     * the correction with its mandatory motif.
     */
    public function rectify(
        Bulletin $bulletin,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
        string $motif,
    ): PublishBulletinResult;
}
