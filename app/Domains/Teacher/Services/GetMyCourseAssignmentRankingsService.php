<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Models\AttributionCours;
use App\Models\Inscription;
use App\Models\ModeleMaximum;
use App\Models\Note;
use Illuminate\Support\Collection;

final class GetMyCourseAssignmentRankingsService
{
    /** @return array<string, mixed> */
    public function get(AttributionCours $assignment, ?int $periodId): array
    {
        $slots = ModeleMaximum::query()
            ->where('tenant_id', $assignment->tenant_id)
            ->where('modele_bulletin_id', $assignment->modele_bulletin_id)
            ->where('node_id', $assignment->node_id)
            ->whereHas('periode', fn ($query) => $query->where('actif', true)->where('saisissable', true))
            ->with('periode')->get()->sortBy('periode.ordre')->values();
        $slot = $periodId === null ? $slots->first() : $slots->firstWhere('periode_id', $periodId);
        $assignment->loadMissing(['classe', 'node']);

        if (! $slot instanceof ModeleMaximum) {
            return ['attribution' => $this->assignmentRow($assignment), 'periodes' => $this->periodRows($slots), 'periode' => null, 'classement' => []];
        }

        $enrollments = Inscription::query()->where('tenant_id', $assignment->tenant_id)
            ->where('classe_id', $assignment->classe_id)->where('statut', 'ACTIF')->with(['eleve', 'bulletin'])->get();
        $notes = Note::query()->whereIn('bulletin_id', $enrollments->pluck('bulletin.id')->filter())
            ->where('node_id', $assignment->node_id)->where('periode_id', $slot->periode_id)->get()->keyBy('bulletin_id');
        $rows = $enrollments->map(function (Inscription $enrollment) use ($notes, $slot): array {
            $note = $notes->get($enrollment->bulletin?->getKey());
            $valid = $note instanceof Note && $note->statut_resultat === NoteResultStatus::Saisie && $note->valeur !== null;
            $percentage = $valid ? round(((float) $note->valeur / (float) $slot->maxima) * 100, 2) : null;

            return ['rang' => null, 'inscription_id' => $enrollment->getKey(), 'eleve' => ['id' => $enrollment->eleve->getKey(), 'nom_complet' => $enrollment->eleve->nom_complet, 'num_permanent' => $enrollment->eleve->num_permanent], 'total_obtenu' => $valid ? (string) $note->valeur : null, 'total_maxima' => $valid ? (string) $slot->maxima : null, 'pourcentage' => $percentage];
        });

        return ['attribution' => $this->assignmentRow($assignment), 'periodes' => $this->periodRows($slots), 'periode' => $this->periodRow($slot), 'classement' => $this->rank($rows)];
    }

    private function rank(Collection $rows): array
    {
        $ranked = $rows->filter(fn (array $row): bool => $row['pourcentage'] !== null)->sortByDesc('pourcentage')->values();
        $previous = null;
        $rank = 0;
        $ranked = $ranked->map(function (array $row, int $index) use (&$previous, &$rank): array {
            $value = (float) $row['pourcentage'];
            $rank = $previous !== null && abs($value - $previous) < 0.00001 ? $rank : $index + 1;
            $previous = $value;
            $row['rang'] = $rank;

            return $row;
        });

        return $ranked->concat($rows->filter(fn (array $row): bool => $row['pourcentage'] === null)->sortBy('eleve.nom_complet'))->values()->all();
    }

    private function assignmentRow(AttributionCours $assignment): array
    {
        return ['id' => $assignment->getKey(), 'cours' => $assignment->node->libelle, 'classe' => ['id' => $assignment->classe->getKey(), 'nom' => $assignment->classe->nom]];
    }

    private function periodRows(Collection $slots): array
    {
        return $slots->map(fn (ModeleMaximum $slot): array => $this->periodRow($slot))->all();
    }

    private function periodRow(ModeleMaximum $slot): array
    {
        return ['id' => $slot->periode->getKey(), 'code' => $slot->periode->code, 'nom' => $slot->periode->nom, 'ordre' => $slot->periode->ordre, 'maximum' => (string) $slot->maxima];
    }
}
