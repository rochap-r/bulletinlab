<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\ModelePeriode;
use App\Models\Publication;
use App\Models\Utilisateur;

final class PublishClassBulletinsService
{
    public function __construct(
        private PublishBulletinCalculationService $publishService,
    ) {}

    /**
     * @return array{
     *     classe_id: int,
     *     periode_id: int,
     *     published_count: int,
     *     already_published_count: int,
     *     failed_count: int,
     *     results: list<array<string, mixed>>,
     * }
     */
    public function publishAll(
        Etablissement $establishment,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
    ): array {
        $bulletins = Bulletin::withoutGlobalScopes()
            ->where('tenant_id', $establishment->tenant_id)
            ->where('classe_id', $classe->getKey())
            ->get();

        $alreadyPublishedIds = Publication::withoutGlobalScopes()
            ->where('tenant_id', $establishment->tenant_id)
            ->where('periode_id', $period->getKey())
            ->where('statut', 'PUBLIEE')
            ->pluck('bulletin_id')
            ->all();

        $published = 0;
        $alreadyPublished = 0;
        $failed = 0;
        $results = [];

        foreach ($bulletins as $bulletin) {
            if (in_array($bulletin->getKey(), $alreadyPublishedIds, true)) {
                $alreadyPublished++;
                $results[] = [
                    'bulletin_id' => $bulletin->getKey(),
                    'status' => 'already_published',
                ];

                continue;
            }

            try {
                $result = $this->publishService->publish($bulletin, $classe, $period, $actor);

                if ($result->publicationStatus === 'PUBLIEE') {
                    $published++;
                } else {
                    $alreadyPublished++;
                }

                $results[] = [
                    'bulletin_id' => $bulletin->getKey(),
                    'status' => 'published',
                    'snapshot_version' => $result->snapshotVersion,
                ];
            } catch (BulletinException $e) {
                $failed++;
                $results[] = [
                    'bulletin_id' => $bulletin->getKey(),
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return [
            'classe_id' => $classe->getKey(),
            'periode_id' => $period->getKey(),
            'published_count' => $published,
            'already_published_count' => $alreadyPublished,
            'failed_count' => $failed,
            'results' => $results,
        ];
    }
}
