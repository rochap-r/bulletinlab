<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Contracts\BulletinCalculationServiceInterface;
use App\Domains\Bulletin\Contracts\PublishBulletinServiceInterface;
use App\Domains\Bulletin\Contracts\RankBulletinsServiceInterface;
use App\Domains\Bulletin\Contracts\ResolveMentionDecisionServiceInterface;
use App\Domains\Bulletin\DTOs\BulletinCalculationInput;
use App\Domains\Bulletin\DTOs\BulletinCalculationResult;
use App\Domains\Bulletin\DTOs\BulletinCalculationSlot;
use App\Domains\Bulletin\DTOs\PublishBulletinResult;
use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Models\AuditLog;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\Publication;
use App\Models\Rectification;
use App\Models\ResultatBulletin;
use App\Models\Utilisateur;
use App\Models\VerrouillagePeriode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;

final class PublishBulletinCalculationService implements PublishBulletinServiceInterface
{
    public function __construct(
        private BulletinCalculationServiceInterface $calculationService,
        private ResolveMentionDecisionServiceInterface $mentionService,
        private RankBulletinsServiceInterface $rankService,
    ) {}

    public function publish(
        Bulletin $bulletin,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
    ): PublishBulletinResult {
        return DB::transaction(
            function () use ($bulletin, $classe, $period, $actor): PublishBulletinResult {
                $context = $this->calculateContext($bulletin, $classe, $period);
                [$snapshot, $snapshotCreated] = $this->firstOrCreateSnapshot($context);
                [$publication, $publicationCreated] = $this->createPublication(
                    $context,
                    $snapshot,
                    $actor,
                );

                if ($publicationCreated) {
                    $this->audit(
                        $publication,
                        $snapshot,
                        $actor,
                        $context['result'],
                        $context['input'],
                        $context['resolved'],
                    );
                }

                return $this->result($context, $snapshot, $publication);
            },
            attempts: 3,
        );
    }

    public function rectify(
        Bulletin $bulletin,
        Classe $classe,
        ModelePeriode $period,
        Utilisateur $actor,
        string $motif,
    ): PublishBulletinResult {
        return DB::transaction(
            function () use ($bulletin, $classe, $period, $actor, $motif): PublishBulletinResult {
                $context = $this->calculateContext($bulletin, $classe, $period);

                $current = Publication::withoutGlobalScopes()
                    ->where('bulletin_id', $context['bulletin']->getKey())
                    ->where('periode_id', $context['period']->getKey())
                    ->where('tenant_id', $context['class']->tenant_id)
                    ->where('statut', 'PUBLIEE')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($current === null) {
                    throw new BulletinException(
                        'The report card has no published result to rectify.',
                    );
                }

                $snapshot = $this->forceSnapshot($context);
                $publication = $this->newPublication($snapshot, $context, $actor);

                $current->update(['statut' => 'RECTIFIEE']);

                Rectification::create([
                    'tenant_id' => $context['class']->tenant_id,
                    'publication_id' => $current->getKey(),
                    'nouvelle_publication_id' => $publication->getKey(),
                    'motif' => $motif,
                    'rectifiee_par' => $actor->getKey(),
                ]);

                $this->auditRectification(
                    $publication,
                    $current,
                    $motif,
                    $actor,
                );

                return $this->result($context, $snapshot, $publication);
            },
            attempts: 3,
        );
    }

    /**
     * @return array{
     *     bulletin: Bulletin,
     *     class: Classe,
     *     period: ModelePeriode,
     *     model: ModeleBulletin,
     *     input: BulletinCalculationInput,
     *     result: BulletinCalculationResult,
     *     resolved: array{mention: ?string, decision: ?string},
     * }
     */
    private function calculateContext(
        Bulletin $bulletin,
        Classe $classe,
        ModelePeriode $period,
    ): array {
        $lockedBulletin = Bulletin::withoutGlobalScopes()
            ->whereKey($bulletin->getKey())
            ->where('tenant_id', $classe->tenant_id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedClass = Classe::withoutGlobalScopes()
            ->whereKey($classe->getKey())
            ->where('tenant_id', $classe->tenant_id)
            ->lockForUpdate()
            ->firstOrFail();

        $model = ModeleBulletin::query()
            ->lockForUpdate()
            ->findOrFail($lockedBulletin->modele_bulletin_id);

        $lockedPeriod = ModelePeriode::query()
            ->where('modele_bulletin_id', $model->getKey())
            ->lockForUpdate()
            ->findOrFail($period->getKey());

        $nodes = ModeleNode::query()
            ->where('modele_bulletin_id', $model->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $maxima = ModeleMaximum::query()
            ->where('modele_bulletin_id', $model->getKey())
            ->where('periode_id', $lockedPeriod->getKey())
            ->orderBy('node_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $periodState = VerrouillagePeriode::withoutGlobalScopes()
            ->where('classe_id', $lockedClass->getKey())
            ->where('periode_id', $lockedPeriod->getKey())
            ->lockForUpdate()
            ->first();

        $model->update(['verrouille' => true]);

        if ($periodState === null) {
            VerrouillagePeriode::query()->create([
                'tenant_id' => $lockedClass->tenant_id,
                'classe_id' => $lockedClass->getKey(),
                'periode_id' => $lockedPeriod->getKey(),
                'verrouille' => true,
            ]);
        } elseif (! $periodState->verrouille) {
            $periodState->update(['verrouille' => true]);
        }

        $notes = Note::withoutGlobalScopes()
            ->where('bulletin_id', $lockedBulletin->getKey())
            ->where('periode_id', $lockedPeriod->getKey())
            ->orderBy('node_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $input = $this->makeInput(
            $lockedBulletin,
            $model,
            $lockedPeriod,
            $nodes,
            $maxima,
            $notes,
            periodLocked: true,
        );

        $result = $this->calculationService->calculate($input);

        if (! $result->publicationReady) {
            throw new BulletinException(
                'The report card cannot be published because it has unresolved blockers.',
            );
        }

        $lockedBulletin->update([
            'total_obtenu' => $result->totalObtained,
            'total_maxima' => $result->totalMaximum,
            'pourcentage' => $result->percentage,
            'calcule_at' => now(),
            'verrouille' => true,
        ]);

        $resolved = $this->mentionService->resolve(
            $lockedClass,
            $model,
            $lockedBulletin->refresh(),
        );

        $lockedBulletin->update([
            'mention' => $resolved['mention'],
            'decision_proposee' => $resolved['decision'],
            'decision_statut' => 'EN_COURS',
        ]);

        $this->rankService->rank($lockedClass, $lockedPeriod);
        $lockedBulletin->refresh();

        return [
            'bulletin' => $lockedBulletin,
            'class' => $lockedClass,
            'period' => $lockedPeriod,
            'model' => $model,
            'input' => $input,
            'result' => $result,
            'resolved' => $resolved,
        ];
    }

    /**
     * @param  array{
     *     bulletin: Bulletin,
     *     class: Classe,
     *     period: ModelePeriode,
     *     model: ModeleBulletin,
     *     input: BulletinCalculationInput,
     *     result: BulletinCalculationResult,
     *     resolved: array{mention: ?string, decision: ?string},
     * }  $context
     * @return array{0: ResultatBulletin, 1: bool}
     */
    private function firstOrCreateSnapshot(array $context): array
    {
        $bulletin = $context['bulletin'];
        $existing = ResultatBulletin::withoutGlobalScopes()
            ->where('bulletin_id', $bulletin->getKey())
            ->where('periode_id', $context['period']->getKey())
            ->where('result_hash', $context['result']->resultHash)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        return [$this->forceSnapshot($context), true];
    }

    /**
     * @param  array{
     *     bulletin: Bulletin,
     *     class: Classe,
     *     period: ModelePeriode,
     *     model: ModeleBulletin,
     *     input: BulletinCalculationInput,
     *     result: BulletinCalculationResult,
     *     resolved: array{mention: ?string, decision: ?string},
     * }  $context
     */
    private function forceSnapshot(array $context): ResultatBulletin
    {
        $bulletin = $context['bulletin'];
        $result = $context['result'];
        $resolved = $context['resolved'];
        $latestVersion = (int) (ResultatBulletin::withoutGlobalScopes()
            ->where('bulletin_id', $bulletin->getKey())
            ->where('periode_id', $context['period']->getKey())
            ->max('version') ?? 0);

        return ResultatBulletin::create([
            'tenant_id' => $context['class']->tenant_id,
            'bulletin_id' => $bulletin->getKey(),
            'periode_id' => $context['period']->getKey(),
            'modele_bulletin_id' => $bulletin->modele_bulletin_id,
            'version' => $latestVersion + 1,
            'engine_version' => $result->engineVersion,
            'configuration_hash' => $result->configurationHash,
            'input_hash' => $result->inputHash,
            'result_hash' => $result->resultHash,
            'total_obtenu' => $bulletin->total_obtenu,
            'total_maxima' => $bulletin->total_maxima,
            'pourcentage' => $bulletin->pourcentage,
            'mention' => $resolved['mention'],
            'decision' => $resolved['decision'],
            'rang' => $bulletin->rang,
            'nombre_eleves_classe' => $bulletin->nombre_eleves_classe,
            'payload' => $this->snapshotPayload($context),
        ]);
    }

    /**
     * @param  array{
     *     bulletin: Bulletin,
     *     class: Classe,
     *     period: ModelePeriode,
     *     model: ModeleBulletin,
     *     input: BulletinCalculationInput,
     *     result: BulletinCalculationResult,
     *     resolved: array{mention: ?string, decision: ?string},
     * }  $context
     * @return array{0: Publication, 1: bool}
     */
    private function createPublication(
        array $context,
        ResultatBulletin $snapshot,
        Utilisateur $actor,
    ): array {
        $existing = Publication::withoutGlobalScopes()
            ->where('resultat_bulletin_id', $snapshot->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        return [$this->newPublication($snapshot, $context, $actor), true];
    }

    /**
     * @param  array{
     *     bulletin: Bulletin,
     *     class: Classe,
     *     period: ModelePeriode,
     *     model: ModeleBulletin,
     *     input: BulletinCalculationInput,
     *     result: BulletinCalculationResult,
     *     resolved: array{mention: ?string, decision: ?string},
     * }  $context
     */
    private function newPublication(
        ResultatBulletin $snapshot,
        array $context,
        Utilisateur $actor,
    ): Publication {
        return Publication::create([
            'tenant_id' => $context['class']->tenant_id,
            'bulletin_id' => $context['bulletin']->getKey(),
            'periode_id' => $context['period']->getKey(),
            'resultat_bulletin_id' => $snapshot->getKey(),
            'checksum' => $context['result']->resultHash,
            'engine_version' => $context['result']->engineVersion,
            'statut' => 'PUBLIEE',
            'publiee_par' => $actor->getKey(),
            'publiee_at' => now(),
        ]);
    }

    /**
     * @param  array{
     *     bulletin: Bulletin,
     *     class: Classe,
     *     period: ModelePeriode,
     *     model: ModeleBulletin,
     *     input: BulletinCalculationInput,
     *     result: BulletinCalculationResult,
     *     resolved: array{mention: ?string, decision: ?string},
     * }  $context
     * @return array<string, mixed>
     */
    private function snapshotPayload(array $context): array
    {
        $bulletin = $context['bulletin'];
        $input = $context['input'];
        $result = $context['result'];
        $resolved = $context['resolved'];

        return [
            'schema' => 'current-v1-snapshot',
            'engine_version' => $result->engineVersion,
            'configuration_hash' => $result->configurationHash,
            'input_hash' => $result->inputHash,
            'result_hash' => $result->resultHash,
            'input' => $input->canonicalCalculationPayload(),
            'result' => [
                'total_obtenu' => $bulletin->total_obtenu,
                'total_maxima' => $bulletin->total_maxima,
                'pourcentage' => $bulletin->pourcentage,
                'mention' => $resolved['mention'],
                'decision' => $resolved['decision'],
                'decision_statut' => 'EN_COURS',
                'rang' => $bulletin->rang,
                'nombre_eleves_classe' => $bulletin->nombre_eleves_classe,
            ],
        ];
    }

    /**
     * @param  array{
     *     bulletin: Bulletin,
     *     class: Classe,
     *     period: ModelePeriode,
     *     model: ModeleBulletin,
     *     input: BulletinCalculationInput,
     *     result: BulletinCalculationResult,
     *     resolved: array{mention: ?string, decision: ?string},
     * }  $context
     */
    private function result(
        array $context,
        ResultatBulletin $snapshot,
        Publication $publication,
    ): PublishBulletinResult {
        $bulletin = $context['bulletin'];

        return new PublishBulletinResult(
            configurationHash: $context['result']->configurationHash,
            inputHash: $context['result']->inputHash,
            resultHash: $context['result']->resultHash,
            totalObtained: $bulletin->total_obtenu,
            totalMaximum: $bulletin->total_maxima,
            percentage: $bulletin->pourcentage,
            mention: $bulletin->mention,
            decision: $bulletin->decision_proposee,
            rank: $bulletin->rang,
            classSize: (int) ($bulletin->nombre_eleves_classe ?? 0),
            publicationId: (int) $publication->getKey(),
            snapshotVersion: (int) $snapshot->version,
            publicationStatus: (string) $publication->statut,
        );
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
        $maximaByNode = $maxima->groupBy('node_id');
        $notesByNode = $notes->keyBy('node_id');

        $slots = $maxima
            ->map(function (ModeleMaximum $maximum) use ($nodesById, $notesByNode, $period): BulletinCalculationSlot {
                $node = $nodesById->get($maximum->node_id);
                $note = $notesByNode->get($maximum->node_id);

                if (! $node instanceof ModeleNode) {
                    throw new BulletinException(
                        'The report card calculation snapshot is inconsistent.',
                    );
                }

                return new BulletinCalculationSlot(
                    key: "node:{$node->getKey()}:period:{$period->getKey()}",
                    nodeId: $node->getKey(),
                    nodeType: $node->type_node,
                    maximumMinorUnits: $this->minorUnits((string) $maximum->maxima),
                    coefficientHundredths: $this->minorUnits((string) $maximum->coefficient),
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
            configurationHash: $this->configurationHash($model, $period, $nodesById, $maxima),
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
            ->map(function (ModeleMaximum $maximum) use ($nodesById): array {
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
                    'maximum_minor_units' => $this->minorUnits((string) $maximum->maxima),
                    'coefficient_hundredths' => $this->minorUnits((string) $maximum->coefficient),
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

    private function audit(
        Publication $publication,
        ResultatBulletin $snapshot,
        Utilisateur $actor,
        BulletinCalculationResult $result,
        BulletinCalculationInput $input,
        array $resolved,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $publication->tenant_id,
            'utilisateur_id' => $actor->getKey(),
            'action' => 'BULLETIN_PUBLIE',
            'table_cible' => $publication->getTable(),
            'ligne_cible_id' => $publication->getKey(),
            'anciennes_valeurs' => null,
            'nouvelles_valeurs' => [
                'bulletin_id' => $publication->bulletin_id,
                'periode_id' => $publication->periode_id,
                'publication_id' => $publication->getKey(),
                'snapshot_version' => $snapshot->version,
                'total_obtenu' => $result->totalObtained,
                'total_maxima' => $result->totalMaximum,
                'pourcentage' => $result->percentage,
                'mention' => $resolved['mention'],
                'decision_proposee' => $resolved['decision'],
                'configuration_hash' => $result->configurationHash,
                'input_hash' => $result->inputHash,
                'result_hash' => $result->resultHash,
                'engine_version' => $result->engineVersion,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function auditRectification(
        Publication $publication,
        Publication $original,
        string $motif,
        Utilisateur $actor,
    ): void {
        AuditLog::query()->create([
            'tenant_id' => $publication->tenant_id,
            'utilisateur_id' => $actor->getKey(),
            'action' => 'BULLETIN_RECTIFIE',
            'table_cible' => (new Rectification)->getTable(),
            'ligne_cible_id' => $publication->getKey(),
            'anciennes_valeurs' => [
                'publication_id' => $original->getKey(),
                'checksum' => $original->checksum,
            ],
            'nouvelles_valeurs' => [
                'nouvelle_publication_id' => $publication->getKey(),
                'checksum' => $publication->checksum,
                'motif' => $motif,
                'engine_version' => $publication->engine_version,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
