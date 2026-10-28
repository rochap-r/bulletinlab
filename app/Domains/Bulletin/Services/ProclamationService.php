<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Contracts\ProclamationServiceInterface;
use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Models\AuditLog;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\ModelePeriode;
use App\Models\Proclamation;
use App\Models\ProclamationLigne;
use App\Models\Publication;
use App\Models\ResultatBulletin;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ProclamationService implements ProclamationServiceInterface
{
    public function generate(
        Etablissement $establishment,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
    ): Proclamation {
        return DB::transaction(
            function () use ($establishment, $classe, $period, $actor): Proclamation {
                $existing = Proclamation::query()
                    ->where('tenant_id', $establishment->tenant_id)
                    ->where('etablissement_id', $establishment->getKey())
                    ->where('classe_id', $classe->getKey())
                    ->where('periode_id', $period->getKey())
                    ->where('statut', '!=', 'ANNULEE')
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $publications = $this->publishedForClass($establishment, $classe, $period);

                if ($publications->isEmpty()) {
                    throw new BulletinException(
                        'No published report cards with decisions found for this class and period.',
                    );
                }

                $proclamation = Proclamation::create([
                    'tenant_id' => $establishment->tenant_id,
                    'etablissement_id' => $establishment->getKey(),
                    'annee_scolaire_id' => $classe->annee_scolaire_id,
                    'classe_id' => $classe->getKey(),
                    'periode_id' => $period->getKey(),
                    'publication_id' => $publications->first()->getKey(),
                    'type' => $this->resolveType($period),
                    'statut' => 'GENEREE',
                    'date_generation' => now()->toDateString(),
                    'generee_par' => $actor->getKey(),
                    'metadata' => [
                        'engine_version' => '1.0.0',
                        'source' => 'publications',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ]);

                foreach ($publications as $publication) {
                    $bulletin = $publication->bulletin;
                    $snapshot = $publication->resultatBulletin;

                    $student = $bulletin?->inscription?->eleve;

                    if ($bulletin === null || $student === null) {
                        continue;
                    }

                    $this->createLine($proclamation, $publication, $snapshot, $bulletin, $student);
                }

                $this->audit($proclamation, $actor, $publications->count());

                return $proclamation;
            },
            attempts: 3,
        );
    }

    public function findPublishedForClass(
        Etablissement $establishment,
        Classe $classe,
    ): ?Proclamation {
        return Proclamation::query()
            ->where('tenant_id', $establishment->tenant_id)
            ->where('etablissement_id', $establishment->getKey())
            ->where('classe_id', $classe->getKey())
            ->whereIn('statut', ['GENEREE', 'VALIDEE'])
            ->latest()
            ->first();
    }

    /**
     * @return Collection<int, Publication>
     */
    private function publishedForClass(
        Etablissement $establishment,
        Classe $classe,
        ModelePeriode $period,
    ): Collection {
        return Publication::query()
            ->withoutGlobalScopes()
            ->select('publications.*')
            ->with(['bulletin.inscription.eleve', 'resultatBulletin'])
            ->join(
                'resultats_bulletins',
                'resultats_bulletins.id',
                '=',
                'publications.resultat_bulletin_id',
            )
            ->where('publications.tenant_id', $establishment->tenant_id)
            ->where('publications.periode_id', $period->getKey())
            ->where('publications.statut', 'PUBLIEE')
            ->whereHas(
                'bulletin',
                fn (Builder $query): Builder => $query
                    ->where('tenant_id', $establishment->tenant_id)
                    ->where('classe_id', $classe->getKey()),
            )
            ->orderByDesc('resultats_bulletins.pourcentage')
            ->orderByDesc('publications.id')
            ->lockForUpdate()
            ->get();
    }

    private function createLine(
        Proclamation $proclamation,
        Publication $publication,
        ?ResultatBulletin $snapshot,
        Bulletin $bulletin,
        Eleve $student,
    ): void {
        ProclamationLigne::create([
            'proclamation_id' => $proclamation->getKey(),
            'publication_id' => $publication->getKey(),
            'bulletin_id' => $bulletin->getKey(),
            'eleve_id' => $student->getKey(),
            'rang' => $snapshot?->rang ?? $bulletin->rang,
            'total_obtenu' => $snapshot?->total_obtenu ?? $bulletin->total_obtenu,
            'total_maxima' => $snapshot?->total_maxima ?? $bulletin->total_maxima,
            'pourcentage' => $snapshot?->pourcentage ?? $bulletin->pourcentage,
            'mention' => $snapshot?->mention ?? $bulletin->mention,
            'decision' => $this->mapDecision(
                $snapshot?->decision ?? $bulletin->decision_proposee,
            ),
            'details' => [
                'decision_proposee' => $snapshot?->decision ?? $bulletin->decision_proposee,
                'decision_statut' => $bulletin->decision_statut,
                'publication_id' => $publication->getKey(),
                'snapshot_version' => $snapshot?->version,
            ],
        ]);
    }

    private function resolveType(ModelePeriode $period): string
    {
        return match ($period->type_periode) {
            'TRIMESTRE' => 'TRIMESTRE',
            'SEMESTRE' => 'SEMESTRE',
            'ANNUEL' => 'ANNUELLE',
            default => 'PERIODE',
        };
    }

    private function mapDecision(?string $decision): string
    {
        return match ($decision) {
            'PASSE' => 'PASSE',
            'DOUBLE' => 'DOUBLE',
            'ECHOUE' => 'ECHOUE',
            'REPECHAGE' => 'REPECHAGE',
            'REORIENTE' => 'REORIENTE',
            'DIPLOME' => 'DIPLOME',
            default => 'EN_COURS',
        };
    }

    private function audit(
        Proclamation $proclamation,
        Utilisateur $actor,
        int $publicationCount,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $proclamation->tenant_id,
            'utilisateur_id' => $actor->getKey(),
            'action' => 'PROCLAMATION_GENEREE',
            'table_cible' => $proclamation->getTable(),
            'ligne_cible_id' => $proclamation->getKey(),
            'anciennes_valeurs' => null,
            'nouvelles_valeurs' => [
                'classe_id' => $proclamation->classe_id,
                'periode_id' => $proclamation->periode_id,
                'type' => $proclamation->type,
                'nombre_lignes' => $publicationCount,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
