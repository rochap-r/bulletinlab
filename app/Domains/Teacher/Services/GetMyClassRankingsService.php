<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Models\CalendrierPeriode;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\ResultatBulletin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class GetMyClassRankingsService
{
    /** @return array<string, mixed> */
    public function get(Classe $class, ?int $periodId): array
    {
        $model = ModeleBulletin::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('classe_id', $class->getKey())
            ->where('annee_scolaire_id', $class->annee_scolaire_id)
            ->where('actif', true)
            ->first();

        if (! $model instanceof ModeleBulletin) {
            return $this->emptyResult($class);
        }

        $periods = ModelePeriode::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('modele_bulletin_id', $model->getKey())
            ->where('actif', true)
            ->whereIn('type_periode', ['PERIODE', 'TRIMESTRE', 'SEMESTRE', 'TOTAL'])
            ->where(fn ($query) => $query->where('saisissable', true)->orWhere('calculable', true))
            ->orderBy('ordre')->orderBy('id')->get();
        $automaticPeriod = $periodId === null;
        $period = $automaticPeriod
            ? $this->calendarPeriod($class, $periods)
            : $periods->firstWhere('id', $periodId);

        if (! $period instanceof ModelePeriode) {
            return $this->emptyResult($class, $periods);
        }

        $enrollments = Inscription::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('classe_id', $class->getKey())
            ->where('annee_scolaire_id', $class->annee_scolaire_id)
            ->where('statut', 'ACTIF')
            ->with(['eleve', 'bulletin'])->get();
        $results = ResultatBulletin::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('periode_id', $period->getKey())
            ->whereIn('bulletin_id', $enrollments->pluck('bulletin.id')->filter())
            ->orderByDesc('version')->orderByDesc('id')->get()
            ->unique('bulletin_id')->keyBy('bulletin_id');
        $maxima = collect();
        $notes = collect();

        if ($period->saisissable) {
            $maxima = ModeleMaximum::query()
                ->where('tenant_id', $class->tenant_id)
                ->where('modele_bulletin_id', $model->getKey())
                ->where('periode_id', $period->getKey())
                ->get()->keyBy('node_id');
            $notes = Note::query()
                ->whereIn('bulletin_id', $enrollments->pluck('bulletin.id')->filter())
                ->where('periode_id', $period->getKey())
                ->whereIn('node_id', $maxima->keys())
                ->get()->keyBy(fn (Note $note): string => $note->bulletin_id.':'.$note->node_id);
        }

        $rows = $enrollments->map(function (Inscription $enrollment) use ($results, $maxima, $notes, $period): array {
            $result = $results->get($enrollment->bulletin?->getKey());

            if (! $result instanceof ResultatBulletin && $period->saisissable) {
                return $this->livePeriodRow($enrollment, $maxima, $notes);
            }

            return [
                'rang' => $result?->rang,
                'inscription_id' => $enrollment->getKey(),
                'eleve' => ['id' => $enrollment->eleve->getKey(), 'nom_complet' => $enrollment->eleve->nom_complet, 'num_permanent' => $enrollment->eleve->num_permanent],
                'total_obtenu' => $result?->total_obtenu,
                'total_maxima' => $result?->total_maxima,
                'pourcentage' => $result?->pourcentage === null ? null : (float) $result->pourcentage,
            ];
        });

        return [
            'classe' => ['id' => $class->getKey(), 'nom' => $class->nom],
            'nombre_eleves' => $enrollments->count(),
            'selection_automatique' => $automaticPeriod,
            'periodes' => $this->periodRows($periods),
            'periode' => $this->periodRow($period),
            'classement_general' => $this->rank($rows),
        ];
    }

    private function calendarPeriod(Classe $class, Collection $periods): ?ModelePeriode
    {
        if ($periods->isEmpty()) {
            return null;
        }

        $today = CarbonImmutable::today((string) config('app.business_timezone'));
        $calendarPeriods = CalendrierPeriode::query()
            ->join('annee_periode_mappings', 'annee_periode_mappings.calendrier_periode_id', '=', 'calendrier_periodes.id')
            ->where('annee_periode_mappings.annee_scolaire_id', $class->annee_scolaire_id)
            ->where('calendrier_periodes.periode_active', true)
            ->where('calendrier_periodes.date_debut', '<=', $today->toDateString())
            ->orderByDesc('calendrier_periodes.date_fin')
            ->orderByDesc('calendrier_periodes.ordre')
            ->get(['calendrier_periodes.*', 'annee_periode_mappings.code_periode_template']);

        $current = $calendarPeriods
            ->filter(fn (CalendrierPeriode $candidate): bool => $today->lte(CarbonImmutable::parse($candidate->date_fin)))
            ->map(fn (CalendrierPeriode $candidate): ?ModelePeriode => $this->matchingPeriod($periods, (string) $candidate->code_periode_template))
            ->filter()
            ->sortBy(fn (ModelePeriode $candidate): int => $this->periodPriority($candidate))
            ->first();

        if ($current instanceof ModelePeriode) {
            return $current;
        }

        foreach ($calendarPeriods as $candidate) {
            $matched = $this->matchingPeriod($periods, (string) $candidate->code_periode_template);
            if ($matched instanceof ModelePeriode) {
                return $matched;
            }
        }

        return $periods->first();
    }

    private function matchingPeriod(Collection $periods, string $calendarCode): ?ModelePeriode
    {
        $normalized = preg_replace('/^(?:PRI|MAT)_/i', '', strtoupper($calendarCode));
        $matched = $periods->first(fn (ModelePeriode $period): bool => strtoupper($period->code) === $normalized);

        if ($matched instanceof ModelePeriode) {
            return $matched;
        }

        if (preg_match('/^SEMESTRE_(\d+)$/', $normalized ?? '', $matches) === 1) {
            return $periods->first(fn (ModelePeriode $period): bool => in_array(strtoupper($period->code), ["TOTAL_S{$matches[1]}", "SEMESTRE_{$matches[1]}"], true));
        }

        return null;
    }

    private function periodPriority(ModelePeriode $period): int
    {
        return match ($period->type_periode) {
            'PERIODE' => 0,
            'TRIMESTRE' => 1,
            'SEMESTRE' => 2,
            default => 3,
        };
    }

    private function livePeriodRow(Inscription $enrollment, Collection $maxima, Collection $notes): array
    {
        $obtained = 0.0;
        $expected = 0.0;
        $complete = $maxima->isNotEmpty();

        foreach ($maxima as $maximum) {
            $note = $notes->get($enrollment->bulletin?->getKey().':'.$maximum->node_id);
            if (! $note instanceof Note || $note->statut_resultat !== NoteResultStatus::Saisie || $note->valeur === null) {
                $complete = false;

                continue;
            }
            $obtained += (float) $note->valeur;
            $expected += (float) $maximum->maxima;
        }

        return [
            'rang' => null,
            'inscription_id' => $enrollment->getKey(),
            'eleve' => ['id' => $enrollment->eleve->getKey(), 'nom_complet' => $enrollment->eleve->nom_complet, 'num_permanent' => $enrollment->eleve->num_permanent],
            'total_obtenu' => $complete ? number_format($obtained, 2, '.', '') : null,
            'total_maxima' => $complete ? number_format($expected, 2, '.', '') : null,
            'pourcentage' => $complete && $expected > 0 ? round(($obtained / $expected) * 100, 2) : null,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function rank(Collection $rows): array
    {
        $ranked = $rows->filter(fn (array $row): bool => $row['pourcentage'] !== null)->sortByDesc('pourcentage')->values();
        $previousPercentage = null;
        $previousRank = 0;

        $ranked = $ranked->map(function (array $row, int $index) use (&$previousPercentage, &$previousRank): array {
            $percentage = (float) $row['pourcentage'];
            $row['rang'] = $previousPercentage !== null && abs($percentage - $previousPercentage) < 0.00001 ? $previousRank : $index + 1;
            $previousPercentage = $percentage;
            $previousRank = $row['rang'];

            return $row;
        });

        return $ranked->concat($rows->filter(fn (array $row): bool => $row['pourcentage'] === null)->sortBy('eleve.nom_complet'))->values()->all();
    }

    private function emptyResult(Classe $class, ?Collection $periods = null): array
    {
        return ['classe' => ['id' => $class->getKey(), 'nom' => $class->nom], 'nombre_eleves' => 0, 'selection_automatique' => true, 'periodes' => $this->periodRows($periods ?? collect()), 'periode' => null, 'classement_general' => []];
    }

    private function periodRows(Collection $periods): array
    {
        return $periods->map(fn (ModelePeriode $period): array => $this->periodRow($period))->all();
    }

    private function periodRow(ModelePeriode $period): array
    {
        return ['id' => $period->getKey(), 'code' => $period->code, 'nom' => $period->nom, 'type' => $period->type_periode, 'ordre' => $period->ordre];
    }
}
