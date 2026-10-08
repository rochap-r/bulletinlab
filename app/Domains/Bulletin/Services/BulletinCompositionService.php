<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Models\Bulletin;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\Note;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class BulletinCompositionService
{
    public function compose(Inscription $enrollment): Bulletin
    {
        $reportCards = Bulletin::query()
            ->where('tenant_id', $enrollment->tenant_id)
            ->where('inscription_id', $enrollment->getKey())
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($reportCards->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(Bulletin::class);
        }

        if ($reportCards->count() > 1) {
            throw new BulletinException(
                'Multiple report cards are attached to this enrollment.',
            );
        }

        $reportCard = $reportCards->firstOrFail();

        if ($reportCard->classe_id !== $enrollment->classe_id) {
            throw new BulletinException(
                'The report card is inconsistent with its enrollment.',
            );
        }

        $model = ModeleBulletin::query()
            ->whereKey($reportCard->modele_bulletin_id)
            ->where('tenant_id', $enrollment->tenant_id)
            ->where('classe_id', $enrollment->classe_id)
            ->where('annee_scolaire_id', $enrollment->annee_scolaire_id)
            ->first();

        if (! $model) {
            throw new BulletinException(
                'The report card configuration is inconsistent.',
            );
        }

        $enrollment->loadMissing('eleve');
        $model->load([
            'nodes' => fn ($query) => $query
                ->where('tenant_id', $enrollment->tenant_id)
                ->orderBy('ordre')
                ->orderBy('id'),
            'periodes' => fn ($query) => $query
                ->where('tenant_id', $enrollment->tenant_id)
                ->orderBy('ordre')
                ->orderBy('id'),
            'maxima' => fn ($query) => $query
                ->where('tenant_id', $enrollment->tenant_id)
                ->orderBy('node_id')
                ->orderBy('periode_id')
                ->orderBy('id'),
        ]);
        $reportCard->load([
            'notes' => fn ($query) => $query
                ->where('tenant_id', $enrollment->tenant_id)
                ->orderBy('node_id')
                ->orderBy('periode_id')
                ->orderBy('id'),
        ]);

        $nodeIds = $model->nodes->modelKeys();
        $periodIds = $model->periodes->modelKeys();
        $maximaBySlot = $model->maxima->keyBy(
            fn (ModeleMaximum $maximum): string => $this->slotKey(
                $maximum->node_id,
                $maximum->periode_id,
            ),
        );
        $hasInconsistentSlots = $model->maxima->contains(
            fn ($maximum): bool => ! in_array($maximum->node_id, $nodeIds, true)
                || ! in_array($maximum->periode_id, $periodIds, true),
        );
        $hasInconsistentNotes = $reportCard->notes->contains(
            function (Note $note) use (
                $nodeIds,
                $periodIds,
                $maximaBySlot,
            ): bool {
                $maximum = $maximaBySlot->get(
                    $this->slotKey($note->node_id, $note->periode_id),
                );

                return ! in_array($note->node_id, $nodeIds, true)
                    || ! in_array($note->periode_id, $periodIds, true)
                    || ! $maximum instanceof ModeleMaximum
                    || ! $this->isConsistentResult($note, $maximum);
            },
        );

        if ($hasInconsistentSlots || $hasInconsistentNotes) {
            throw new BulletinException(
                'The report card composition is inconsistent.',
            );
        }

        $enrollment->setRelation('classe', $enrollment->classe);
        $reportCard->setRelation('inscription', $enrollment);
        $reportCard->setRelation('classe', $enrollment->classe);
        $reportCard->setRelation('modeleBulletin', $model);

        return $reportCard;
    }

    private function isConsistentResult(
        Note $note,
        ModeleMaximum $maximum,
    ): bool {
        if ($note->statut_resultat === NoteResultStatus::Saisie) {
            return $note->valeur !== null
                && $this->minorUnits($note->valeur) >= 0
                && $this->minorUnits($note->valeur)
                    <= $this->minorUnits((string) $maximum->maxima);
        }

        return $note->valeur === null;
    }

    private function minorUnits(string $value): int
    {
        [$integer, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '',
        );

        return ((int) $integer * 100)
            + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function slotKey(int $nodeId, int $periodId): string
    {
        return "{$nodeId}:{$periodId}";
    }
}
