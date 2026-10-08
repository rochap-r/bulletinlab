<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Models\AuditLog;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\ModelePeriode;
use App\Models\PromotionAnnuelle;
use App\Models\PromotionEleve;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PromotionService
{
    public function generate(
        Etablissement $establishment,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
        ?int $anneeCibleId = null,
    ): PromotionAnnuelle {
        return DB::transaction(
            function () use ($establishment, $classe, $period, $actor, $anneeCibleId): PromotionAnnuelle {
                $existing = PromotionAnnuelle::query()
                    ->where('tenant_id', $establishment->tenant_id)
                    ->where('annee_source_id', $classe->annee_scolaire_id)
                    ->where('configuration->etablissement_id', $establishment->getKey())
                    ->where('configuration->classe_id', $classe->getKey())
                    ->where('configuration->periode_id', $period->getKey())
                    ->where('statut', '!=', 'ANNULE')
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $publications = $this->deliberatedPublications($establishment, $classe, $period);

                if ($publications->isEmpty()) {
                    throw new BulletinException(
                        'No deliberated publications found for this class and period.',
                    );
                }

                $promotion = PromotionAnnuelle::create([
                    'tenant_id' => $establishment->tenant_id,
                    'annee_source_id' => $classe->annee_scolaire_id,
                    'annee_cible_id' => $anneeCibleId ?? $classe->annee_scolaire_id,
                    'statut' => 'BROUILLON',
                    'configuration' => [
                        'engine_version' => '1.0.0',
                        'source' => 'publications',
                        'etablissement_id' => $establishment->getKey(),
                        'classe_id' => $classe->getKey(),
                        'periode_id' => $period->getKey(),
                        'generated_at' => now()->toIso8601String(),
                    ],
                    'publication_id' => $publications->first()->getKey(),
                ]);

                foreach ($publications as $publication) {
                    $bulletin = $publication->bulletin;

                    if ($bulletin === null || $bulletin->inscription === null) {
                        continue;
                    }

                    PromotionEleve::create([
                        'promotion_id' => $promotion->getKey(),
                        'inscription_source_id' => $bulletin->inscription_id,
                        'classe_cible_id' => null,
                        'decision' => $this->mapDecision(
                            $bulletin->decision_statut,
                        ),
                        'inscription_cible_id' => null,
                    ]);
                }

                $this->audit($promotion, $actor, $publications->count());

                return $promotion;
            },
            attempts: 3,
        );
    }

    /**
     * @return Collection<int, Publication>
     */
    private function deliberatedPublications(
        Etablissement $establishment,
        Classe $classe,
        ModelePeriode $period,
    ): Collection {
        return Publication::query()
            ->withoutGlobalScopes()
            ->with(['bulletin.inscription.eleve', 'resultatBulletin'])
            ->where('publications.tenant_id', $establishment->tenant_id)
            ->where('publications.periode_id', $period->getKey())
            ->where('publications.statut', 'PUBLIEE')
            ->whereHas(
                'bulletin',
                fn (Builder $query): Builder => $query
                    ->where('tenant_id', $establishment->tenant_id)
                    ->where('classe_id', $classe->getKey())
                    ->where('decision_statut', '!=', 'EN_COURS'),
            )
            ->orderByDesc('publications.id')
            ->lockForUpdate()
            ->get();
    }

    private function mapDecision(?string $decision): string
    {
        return match ($decision) {
            'DOUBLE', 'ECHOUE' => 'DOUBLE',
            'REORIENTE' => 'TRANSFERE',
            'ABANDON' => 'ABANDON',
            default => 'PASSE',
        };
    }

    private function audit(
        PromotionAnnuelle $promotion,
        Utilisateur $actor,
        int $publicationCount,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $promotion->tenant_id,
            'utilisateur_id' => $actor->getKey(),
            'action' => 'PROMOTION_GENEREE',
            'table_cible' => $promotion->getTable(),
            'ligne_cible_id' => $promotion->getKey(),
            'anciennes_valeurs' => null,
            'nouvelles_valeurs' => [
                'annee_source_id' => $promotion->annee_source_id,
                'annee_cible_id' => $promotion->annee_cible_id,
                'configuration' => $promotion->configuration,
                'nombre_eleves' => $publicationCount,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
