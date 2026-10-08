<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Models\AuditLog;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\ModelePeriode;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ValidateClassDeliberationService
{
    /**
     * @return array{
     *     classe_id: int,
     *     periode_id: int,
     *     validated_count: int,
     *     skipped_count: int,
     *     details: list<array<string, mixed>>,
     * }
     */
    public function deliberate(
        Etablissement $establishment,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
        string $lieuDeliberation,
        string $dateDeliberation,
        array $decisions = [],
    ): array {
        return DB::transaction(
            function () use ($establishment, $classe, $period, $actor, $lieuDeliberation, $dateDeliberation, $decisions): array {
                $publications = $this->publishedForClass($establishment, $classe, $period);

                if ($publications->isEmpty()) {
                    throw new BulletinException(
                        'No published report cards found for this class and period. Publish first before deliberating.',
                    );
                }

                $validated = 0;
                $skipped = 0;
                $details = [];

                foreach ($publications as $publication) {
                    $bulletin = $publication->bulletin;
                    $snapshot = $publication->resultatBulletin;

                    if ($bulletin === null) {
                        $skipped++;
                        $details[] = ['bulletin_id' => null, 'status' => 'skipped', 'reason' => 'missing_bulletin'];

                        continue;
                    }

                    if ($bulletin->decision_statut !== 'EN_COURS') {
                        $skipped++;
                        $details[] = [
                            'bulletin_id' => $bulletin->getKey(),
                            'status' => 'skipped',
                            'reason' => 'already_deliberated',
                            'decision_statut' => $bulletin->decision_statut,
                        ];

                        continue;
                    }

                    $decision = $decisions[$bulletin->getKey()]
                        ?? $snapshot?->decision
                        ?? $bulletin->decision_proposee;

                    if ($decision === null || $decision === 'EN_COURS') {
                        $skipped++;
                        $details[] = ['bulletin_id' => $bulletin->getKey(), 'status' => 'skipped', 'reason' => 'no_decision_available'];

                        continue;
                    }

                    $previous = $bulletin->decision_statut;

                    $bulletin->update([
                        'decision_statut' => $decision,
                        'lieu_deliberation' => $lieuDeliberation,
                        'date_deliberation' => $dateDeliberation,
                    ]);

                    $this->audit($bulletin, $actor, $previous, $decision);

                    $validated++;
                    $details[] = [
                        'bulletin_id' => $bulletin->getKey(),
                        'status' => 'validated',
                        'decision' => $decision,
                    ];
                }

                return [
                    'classe_id' => $classe->getKey(),
                    'periode_id' => $period->getKey(),
                    'validated_count' => $validated,
                    'skipped_count' => $skipped,
                    'details' => $details,
                ];
            },
            attempts: 3,
        );
    }

    /**
     * @return Collection<int, Publication>
     */
    private function publishedForClass(
        Etablissement $establishment,
        Classe $classe,
        ModelePeriode $period,
    ) {
        return Publication::query()
            ->withoutGlobalScopes()
            ->with(['bulletin', 'resultatBulletin'])
            ->where('publications.tenant_id', $establishment->tenant_id)
            ->where('publications.periode_id', $period->getKey())
            ->where('publications.statut', 'PUBLIEE')
            ->whereHas(
                'bulletin',
                fn (Builder $query): Builder => $query
                    ->where('tenant_id', $establishment->tenant_id)
                    ->where('classe_id', $classe->getKey()),
            )
            ->orderByDesc('publications.id')
            ->lockForUpdate()
            ->get();
    }

    private function audit(
        Bulletin $bulletin,
        Utilisateur $actor,
        string $previous,
        string $decision,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $bulletin->tenant_id,
            'utilisateur_id' => $actor->getKey(),
            'action' => 'BULLETIN_DELIBERE',
            'table_cible' => $bulletin->getTable(),
            'ligne_cible_id' => $bulletin->getKey(),
            'anciennes_valeurs' => ['decision_statut' => $previous],
            'nouvelles_valeurs' => ['decision_statut' => $decision],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
