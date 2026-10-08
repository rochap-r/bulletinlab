<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Contracts\BulletinCalculationServiceInterface;
use App\Domains\Bulletin\DTOs\BulletinCalculationInput;
use App\Domains\Bulletin\DTOs\BulletinCalculationResult;
use App\Domains\Bulletin\DTOs\BulletinCalculationSlot;
use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Models\AnneeScolaire;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\VerrouillagePeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;

final class PreviewBulletinCalculationService
{
    public function __construct(
        private BulletinCalculationServiceInterface $calculationService,
    ) {}

    public function findPeriodForBulletin(
        Etablissement $establishment,
        Bulletin $bulletin,
        int $periodId,
    ): ModelePeriode {
        if ($bulletin->tenant_id !== $establishment->tenant_id) {
            throw (new ModelNotFoundException)->setModel(
                ModelePeriode::class,
            );
        }

        return ModelePeriode::query()
            ->where('tenant_id', $establishment->tenant_id)
            ->where('modele_bulletin_id', $bulletin->modele_bulletin_id)
            ->findOrFail($periodId);
    }

    public function preview(
        Etablissement $establishment,
        int $bulletinId,
        int $periodId,
    ): BulletinCalculationResult {
        return DB::transaction(
            function () use (
                $establishment,
                $bulletinId,
                $periodId,
            ): BulletinCalculationResult {
                $candidate = $this->findBulletinCandidate(
                    $establishment,
                    $bulletinId,
                );
                $classe = $this->lockClass(
                    $establishment,
                    $candidate->classe_id,
                );
                $schoolYear = AnneeScolaire::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($classe->annee_scolaire_id);
                $model = ModeleBulletin::query()
                    ->lockForUpdate()
                    ->findOrFail($candidate->modele_bulletin_id);
                $period = ModelePeriode::query()
                    ->where('modele_bulletin_id', $model->getKey())
                    ->lockForUpdate()
                    ->findOrFail($periodId);
                $nodes = ModeleNode::query()
                    ->where('modele_bulletin_id', $model->getKey())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $maxima = ModeleMaximum::query()
                    ->where('modele_bulletin_id', $model->getKey())
                    ->where('periode_id', $period->getKey())
                    ->orderBy('node_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $periodState = VerrouillagePeriode::withoutGlobalScopes()
                    ->where('classe_id', $classe->getKey())
                    ->where('periode_id', $period->getKey())
                    ->lockForUpdate()
                    ->first();
                $enrollment = Inscription::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($candidate->inscription_id);
                $bulletin = Bulletin::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($candidate->getKey());
                $notes = Note::withoutGlobalScopes()
                    ->where('bulletin_id', $bulletin->getKey())
                    ->where('periode_id', $period->getKey())
                    ->orderBy('node_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $this->assertSnapshotConsistency(
                    $establishment,
                    $candidate,
                    $classe,
                    $schoolYear,
                    $model,
                    $period,
                    $nodes,
                    $maxima,
                    $periodState,
                    $enrollment,
                    $bulletin,
                    $notes,
                );

                return $this->calculationService->calculate(
                    $this->makeInput(
                        $bulletin,
                        $model,
                        $period,
                        $nodes,
                        $maxima,
                        $notes,
                        (bool) $periodState?->verrouille,
                    ),
                );
            },
            attempts: 3,
        );
    }

    private function findBulletinCandidate(
        Etablissement $establishment,
        int $bulletinId,
    ): Bulletin {
        return Bulletin::withoutGlobalScopes()
            ->select([
                'id',
                'tenant_id',
                'inscription_id',
                'classe_id',
                'modele_bulletin_id',
            ])
            ->where('tenant_id', $establishment->tenant_id)
            ->whereHas(
                'classe',
                function (Builder $classes) use ($establishment): void {
                    $classes
                        ->withoutGlobalScopes()
                        ->where(
                            'classes.tenant_id',
                            $establishment->tenant_id,
                        )
                        ->where(
                            'classes.etablissement_id',
                            $establishment->getKey(),
                        );
                },
            )
            ->findOrFail($bulletinId);
    }

    private function lockClass(
        Etablissement $establishment,
        int $classId,
    ): Classe {
        return Classe::withoutGlobalScopes()
            ->where('tenant_id', $establishment->tenant_id)
            ->where('etablissement_id', $establishment->getKey())
            ->lockForUpdate()
            ->findOrFail($classId);
    }

    /**
     * @param  Collection<int, ModeleNode>  $nodes
     * @param  Collection<int, ModeleMaximum>  $maxima
     * @param  Collection<int, Note>  $notes
     */
    private function assertSnapshotConsistency(
        Etablissement $establishment,
        Bulletin $candidate,
        Classe $classe,
        AnneeScolaire $schoolYear,
        ModeleBulletin $model,
        ModelePeriode $period,
        Collection $nodes,
        Collection $maxima,
        ?VerrouillagePeriode $periodState,
        Inscription $enrollment,
        Bulletin $bulletin,
        Collection $notes,
    ): void {
        $nodesById = $nodes->keyBy('id');
        $maximaByNode = $maxima->groupBy('node_id');

        $inconsistentMaximum = $maxima->contains(
            fn (ModeleMaximum $maximum): bool => $maximum->tenant_id
                    !== $establishment->tenant_id
                || $maximum->modele_bulletin_id !== $model->getKey()
                || $maximum->periode_id !== $period->getKey()
                || ! $nodesById->has($maximum->node_id),
        );
        $inconsistentNode = $nodes->contains(
            fn (ModeleNode $node): bool => $node->tenant_id
                    !== $establishment->tenant_id
                || $node->modele_bulletin_id !== $model->getKey(),
        );
        $inconsistentNote = $notes->contains(
            fn (Note $note): bool => $note->tenant_id
                    !== $establishment->tenant_id
                || $note->bulletin_id !== $bulletin->getKey()
                || $note->periode_id !== $period->getKey()
                || ! $maximaByNode->has($note->node_id),
        );
        $hasDuplicateNotes = $notes
            ->groupBy('node_id')
            ->contains(
                fn (Collection $nodeNotes): bool => $nodeNotes->count() > 1,
            );
        $inconsistentPeriodState = $periodState !== null
            && ($periodState->tenant_id !== $establishment->tenant_id
                || $periodState->classe_id !== $classe->getKey()
                || $periodState->periode_id !== $period->getKey());

        if ($candidate->tenant_id !== $establishment->tenant_id
            || $classe->tenant_id !== $establishment->tenant_id
            || $classe->etablissement_id !== $establishment->getKey()
            || $classe->annee_scolaire_id !== $schoolYear->getKey()
            || $model->tenant_id !== $establishment->tenant_id
            || $model->classe_id !== $classe->getKey()
            || $model->annee_scolaire_id !== $schoolYear->getKey()
            || $period->tenant_id !== $establishment->tenant_id
            || $period->modele_bulletin_id !== $model->getKey()
            || $enrollment->tenant_id !== $establishment->tenant_id
            || $enrollment->classe_id !== $classe->getKey()
            || $enrollment->annee_scolaire_id !== $schoolYear->getKey()
            || $bulletin->tenant_id !== $establishment->tenant_id
            || $bulletin->inscription_id !== $enrollment->getKey()
            || $bulletin->classe_id !== $classe->getKey()
            || $bulletin->modele_bulletin_id !== $model->getKey()
            || $candidate->getKey() !== $bulletin->getKey()
            || $candidate->inscription_id !== $bulletin->inscription_id
            || $candidate->classe_id !== $bulletin->classe_id
            || $candidate->modele_bulletin_id
                !== $bulletin->modele_bulletin_id
            || $inconsistentMaximum
            || $inconsistentNode
            || $inconsistentNote
            || $inconsistentPeriodState
            || $hasDuplicateNotes) {
            throw new BulletinException(
                'The report card calculation snapshot is inconsistent.',
            );
        }
    }

    /**
     * @param  Collection<int, ModeleNode>  $nodes
     * @param  Collection<int, ModeleMaximum>  $maxima
     * @param  Collection<int, Note>  $notes
     */
    private function makeInput(
        Bulletin $bulletin,
        ModeleBulletin $model,
        ModelePeriode $period,
        Collection $nodes,
        Collection $maxima,
        Collection $notes,
        bool $periodLocked,
    ): BulletinCalculationInput {
        $nodesById = $nodes->keyBy('id');
        $notesByNode = $notes->keyBy('node_id');
        $slots = $maxima
            ->map(function (ModeleMaximum $maximum) use (
                $nodesById,
                $notesByNode,
                $period,
            ): BulletinCalculationSlot {
                $node = $nodesById->get($maximum->node_id);
                $note = $notesByNode->get($maximum->node_id);

                if (! $node instanceof ModeleNode
                    || ($note !== null && ! $note instanceof Note)) {
                    throw new BulletinException(
                        'The report card calculation snapshot is inconsistent.',
                    );
                }

                return new BulletinCalculationSlot(
                    key: "node:{$node->getKey()}:period:{$period->getKey()}",
                    nodeId: $node->getKey(),
                    nodeType: $node->type_node,
                    maximumMinorUnits: $this->minorUnits(
                        (string) $maximum->maxima,
                    ),
                    coefficientHundredths: $this->minorUnits(
                        (string) $maximum->coefficient,
                    ),
                    status: $note?->statut_resultat,
                    valueMinorUnits: $note?->valeur !== null
                        ? $this->minorUnits((string) $note->valeur)
                        : null,
                );
            })
            ->values()
            ->all();

        return new BulletinCalculationInput(
            bulletinId: $bulletin->getKey(),
            periodId: $period->getKey(),
            configurationHash: $this->configurationHash(
                $model,
                $period,
                $nodesById,
                $maxima,
            ),
            periodActive: $period->actif,
            periodCalculable: $period->calculable,
            periodLocked: $periodLocked,
            configurationLocked: $model->verrouille,
            slots: $slots,
        );
    }

    /**
     * @param  Collection<int, ModeleNode>  $nodesById
     * @param  Collection<int, ModeleMaximum>  $maxima
     */
    private function configurationHash(
        ModeleBulletin $model,
        ModelePeriode $period,
        Collection $nodesById,
        Collection $maxima,
    ): string {
        $slots = $maxima
            ->map(function (ModeleMaximum $maximum) use (
                $nodesById,
            ): array {
                $node = $nodesById->get($maximum->node_id);

                if (! $node instanceof ModeleNode) {
                    throw new BulletinException(
                        'The report card calculation snapshot is inconsistent.',
                    );
                }

                return [
                    'node_id' => $node->getKey(),
                    'node_type' => $node->type_node,
                    'required' => $node->obligatoire,
                    'maximum_minor_units' => $this->minorUnits(
                        (string) $maximum->maxima,
                    ),
                    'coefficient_hundredths' => $this->minorUnits(
                        (string) $maximum->coefficient,
                    ),
                ];
            })
            ->sortBy('node_id')
            ->values()
            ->all();

        try {
            $payload = json_encode([
                'schema' => 'current-v1',
                'system_template_id' => $model->system_template_id,
                'periode' => [
                    'code' => $period->code,
                    'type' => $period->type_periode,
                    'active' => $period->actif,
                    'calculable' => $period->calculable,
                ],
                'slots' => $slots,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new BulletinException(
                'The report card calculation configuration is invalid.',
                previous: $exception,
            );
        }

        return hash('sha256', $payload);
    }

    private function minorUnits(string $value): int
    {
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new BulletinException(
                'The report card calculation contains an invalid decimal.',
            );
        }

        $negative = str_starts_with($value, '-');
        $absoluteValue = $negative ? substr($value, 1) : $value;
        [$integer, $fraction] = array_pad(
            explode('.', $absoluteValue, 2),
            2,
            '',
        );
        $minorUnits = ((int) $integer * 100)
            + (int) str_pad($fraction, 2, '0');

        return $negative ? -$minorUnits : $minorUnits;
    }
}
