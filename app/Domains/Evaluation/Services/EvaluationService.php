<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Evaluation\Exceptions\EvaluationException;
use App\Domains\Evaluation\Exceptions\PeriodLockedException;
use App\Domains\SchoolYear\Services\PeriodeLockService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Enums\StatutEvaluation;
use App\Models\AttributionCours;
use App\Models\AuditLog;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\ResultatEvaluation;
use App\Models\TypeEvaluation;
use App\Models\Utilisateur;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EvaluationService
{
    public function __construct(
        private EstablishmentContext $establishmentContext,
        private ProjectEvaluationNotesService $projectEvaluationNotesService,
        private PeriodeLockService $periodeLockService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Evaluation>
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $tenantId = $this->establishmentContext->id();

        $query = Evaluation::query()
            ->where('tenant_id', $tenantId)
            ->with(['typeEvaluation', 'periode', 'node', 'classe', 'resultats']);

        if (isset($filters['classe_id'])) {
            $query->where('classe_id', $filters['classe_id']);
        }
        if (isset($filters['periode_id'])) {
            $query->where('periode_id', $filters['periode_id']);
        }
        if (isset($filters['node_id'])) {
            $query->where('node_id', $filters['node_id']);
        }
        if (isset($filters['type_evaluation_id'])) {
            $query->where('type_evaluation_id', $filters['type_evaluation_id']);
        }
        if (isset($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        return $query
            ->latest('evaluations.id')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();
    }

    public function resolveClasse(int $classId): Classe
    {
        return Classe::query()
            ->where('etablissement_id', $this->establishmentContext->id())
            ->findOrFail($classId);
    }

    public function resolvePeriode(Classe $classe, int $periodId): ModelePeriode
    {
        return ModelePeriode::query()
            ->whereHas(
                'modeleBulletin',
                fn (Builder $query): Builder => $query->where('classe_id', $classe->getKey()),
            )
            ->findOrFail($periodId);
    }

    public function resolveNode(Classe $classe, int $nodeId): ModeleNode
    {
        return ModeleNode::query()
            ->whereHas(
                'modeleBulletin',
                fn (Builder $query): Builder => $query->where('classe_id', $classe->getKey()),
            )
            ->findOrFail($nodeId);
    }

    public function resolveTypeEvaluation(int $typeId): TypeEvaluation
    {
        return TypeEvaluation::query()->findOrFail($typeId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Utilisateur $teacher): Evaluation
    {
        $classe = $this->resolveClasse((int) $data['classe_id']);
        $periode = $this->resolvePeriode($classe, (int) $data['periode_id']);
        $node = $this->resolveNode($classe, (int) $data['node_id']);
        $type = $this->resolveTypeEvaluation((int) $data['type_evaluation_id']);

        $attribution = null;
        if (isset($data['attribution_cours_id'])) {
            $attribution = AttributionCours::query()
                ->where('id', $data['attribution_cours_id'])
                ->where('classe_id', $classe->getKey())
                ->where('node_id', $node->getKey())
                ->where('enseignant_id', $teacher->getKey())
                ->first();

            if (! $attribution instanceof AttributionCours) {
                throw new ModelNotFoundException(
                    'Aucune attribution de cours trouvée pour ce professeur, cette classe et cette matière.',
                );
            }
        }

        return DB::transaction(function () use ($classe, $periode, $node, $type, $attribution, $data, $teacher): Evaluation {
            $evaluation = Evaluation::query()->create([
                'tenant_id' => $classe->tenant_id,
                'etablissement_id' => $classe->etablissement_id,
                'annee_scolaire_id' => $classe->annee_scolaire_id,
                'classe_id' => $classe->getKey(),
                'attribution_cours_id' => $attribution?->getKey(),
                'node_id' => $node->getKey(),
                'periode_id' => $periode->getKey(),
                'type_evaluation_id' => $type->getKey(),
                'libelle' => $data['libelle'],
                'maxima' => $data['maxima'],
                'coefficient' => $data['coefficient'] ?? 1.00,
                'date_evaluation' => $data['date_evaluation'],
                'statut' => $data['statut'] ?? StatutEvaluation::Brouillon->value,
                'visible_parents_at' => $data['visible_parents_at'] ?? null,
                'cree_par' => $teacher->getKey(),
                'lock_version' => 1,
            ]);

            AuditLog::query()->create([
                'tenant_id' => $evaluation->tenant_id,
                'utilisateur_id' => $teacher->getKey(),
                'action' => 'EVALUATION_CREEE',
                'table_cible' => $evaluation->getTable(),
                'ligne_cible_id' => $evaluation->getKey(),
                'nouvelles_valeurs' => [
                    'classe_id' => $evaluation->classe_id,
                    'node_id' => $evaluation->node_id,
                    'periode_id' => $evaluation->periode_id,
                    'type_evaluation_id' => $evaluation->type_evaluation_id,
                    'libelle' => $evaluation->libelle,
                    'maxima' => $evaluation->maxima,
                    'coefficient' => $evaluation->coefficient,
                    'date_evaluation' => $evaluation->date_evaluation?->toDateString(),
                    'statut' => $evaluation->statut?->value,
                    'visible_parents_at' => $evaluation->visible_parents_at?->toISOString(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $evaluation;
        });
    }

    public function publish(Evaluation $evaluation, Utilisateur $teacher): Evaluation
    {
        if ((int) $evaluation->tenant_id !== $this->establishmentContext->id()) {
            throw (new ModelNotFoundException)->setModel(Evaluation::class, [$evaluation->getKey()]);
        }

        if ($evaluation->statut !== StatutEvaluation::Brouillon) {
            throw new EvaluationException(
                'Seule une évaluation en brouillon peut être publiée.',
            );
        }

        if ($this->periodeLockService->isLocked(
            (int) $evaluation->periode_id,
            (int) $evaluation->classe_id,
        )) {
            throw new PeriodLockedException(
                'Cette période est verrouillée. Impossible de publier cette évaluation.',
            );
        }

        return DB::transaction(function () use ($evaluation, $teacher): Evaluation {
            $evaluation->forceFill([
                'statut' => StatutEvaluation::Publiee,
                'visible_parents_at' => $evaluation->visible_parents_at ?? now(),
            ])->save();

            AuditLog::query()->create([
                'tenant_id' => $evaluation->tenant_id,
                'utilisateur_id' => $teacher->getKey(),
                'action' => 'EVALUATION_PUBLIEE',
                'table_cible' => $evaluation->getTable(),
                'ligne_cible_id' => $evaluation->getKey(),
                'anciennes_valeurs' => ['statut' => StatutEvaluation::Brouillon->value],
                'nouvelles_valeurs' => [
                    'statut' => StatutEvaluation::Publiee->value,
                    'visible_parents_at' => $evaluation->visible_parents_at?->toISOString(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $evaluation;
        });
    }

    /**
     * @param  array<string, mixed>  $resultats
     */
    public function storeResultats(Evaluation $evaluation, array $resultats, Utilisateur $teacher): int
    {
        if ($evaluation->statut !== StatutEvaluation::Publiee) {
            throw new EvaluationException(
                'Results may only be recorded for a published evaluation.',
            );
        }

        if ($this->periodeLockService->isLocked(
            (int) $evaluation->periode_id,
            (int) $evaluation->classe_id,
        )) {
            throw new PeriodLockedException(
                'Cette période est verrouillée. Impossible de modifier les évaluations.',
            );
        }

        return DB::transaction(function () use ($evaluation, $resultats, $teacher): int {
            $inscriptionIds = collect($resultats)
                ->pluck('inscription_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();
            /** @var Collection<int, Inscription> $inscriptions */
            $inscriptions = Inscription::query()
                ->where('tenant_id', $evaluation->tenant_id)
                ->where('classe_id', $evaluation->classe_id)
                ->where('statut', 'ACTIF')
                ->whereIn('id', $inscriptionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($inscriptions->count() !== $inscriptionIds->count()) {
                throw (new ModelNotFoundException)->setModel(
                    Inscription::class,
                    $inscriptionIds->all(),
                );
            }

            $count = 0;

            foreach ($resultats as $resultat) {
                $inscriptionId = (int) $resultat['inscription_id'];
                $statut = $resultat['statut_resultat'] ?? NoteResultStatus::Saisie->value;
                $valeur = $resultat['valeur'] ?? null;

                if ($statut === NoteResultStatus::Saisie->value
                    && ($valeur === null || (float) $valeur > (float) $evaluation->maxima)) {
                    throw new EvaluationException(
                        'A recorded evaluation result requires a value within the evaluation maximum.',
                    );
                }

                if ($statut !== NoteResultStatus::Saisie->value && $valeur !== null) {
                    throw new EvaluationException(
                        'Only a recorded evaluation result may contain a value.',
                    );
                }

                /** @var Inscription $inscription */
                $inscription = $inscriptions->get($inscriptionId);

                $existing = ResultatEvaluation::query()
                    ->where('evaluation_id', $evaluation->getKey())
                    ->where('inscription_id', $inscriptionId)
                    ->first();

                $resultatEvaluation = ResultatEvaluation::query()->updateOrCreate(
                    [
                        'evaluation_id' => $evaluation->getKey(),
                        'inscription_id' => $inscriptionId,
                    ],
                    [
                        'tenant_id' => $evaluation->tenant_id,
                        'eleve_id' => $inscription->eleve_id,
                        'statut_resultat' => $statut,
                        'valeur' => $valeur,
                        'commentaire_parent' => $resultat['commentaire_parent'] ?? null,
                        'commentaire_interne' => $resultat['commentaire_interne'] ?? null,
                        'saisi_par' => $teacher->getKey(),
                        'date_saisie' => now(),
                        'lock_version' => 1,
                    ],
                );

                AuditLog::query()->create([
                    'tenant_id' => $evaluation->tenant_id,
                    'utilisateur_id' => $teacher->getKey(),
                    'action' => $existing ? 'EVALUATION_RESULTAT_MODIFIE' : 'EVALUATION_RESULTAT_CREE',
                    'table_cible' => $resultatEvaluation->getTable(),
                    'ligne_cible_id' => $resultatEvaluation->getKey(),
                    'anciennes_valeurs' => $existing ? [
                        'statut_resultat' => $existing->statut_resultat?->value,
                        'valeur' => $existing->valeur,
                        'commentaire_parent' => $existing->commentaire_parent,
                        'commentaire_interne' => $existing->commentaire_interne,
                    ] : null,
                    'nouvelles_valeurs' => [
                        'inscription_id' => $inscriptionId,
                        'eleve_id' => $resultatEvaluation->eleve_id,
                        'statut_resultat' => $statut,
                        'valeur' => $valeur,
                        'commentaire_parent' => $resultatEvaluation->commentaire_parent,
                        'commentaire_interne' => $resultatEvaluation->commentaire_interne,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $count++;
            }

            $this->projectEvaluationNotesService->project(
                $evaluation,
                $inscriptions->values(),
                $teacher,
            );

            return $count;
        });
    }
}
