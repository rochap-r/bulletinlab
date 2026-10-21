<?php

namespace App\Domains\Teacher\Services;

use App\Models\AttributionCours;
use App\Models\Classe;
use App\Models\Note;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class GetMyClassProgressService
{
    public function __construct(
        private ListMyCourseAssignmentSlotsService $slotsService,
    ) {}

    /** @return array<string, mixed> */
    public function get(Classe $class): array
    {
        $today = CarbonImmutable::today((string) config('app.business_timezone'));
        $assignments = AttributionCours::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('classe_id', $class->getKey())
            ->effectiveOn($today)
            ->with(['node', 'enseignant'])
            ->orderBy('node_id')
            ->orderBy('id')
            ->get();
        $activeStudents = $class->inscriptions()
            ->where('annee_scolaire_id', $class->annee_scolaire_id)
            ->where('statut', 'ACTIF')
            ->count();
        $nodeIds = $assignments->pluck('node_id')->unique()->values();
        $noteCounts = $this->noteCounts($class, $nodeIds);
        $courses = [];
        $periods = [];
        $encodedTotal = 0;
        $expectedTotal = 0;

        foreach ($assignments as $assignment) {
            $coursePeriods = [];
            $courseEncoded = 0;
            $courseExpected = 0;

            foreach ($this->slotsService->matrixColumns($assignment) as $slot) {
                if (! $slot->periode->saisissable) {
                    continue;
                }

                $key = $this->slotKey($assignment->node_id, $slot->periode_id);
                $encoded = (int) ($noteCounts[$key] ?? 0);
                $expected = $activeStudents;
                $percentage = $this->percentage($encoded, $expected);
                $isLocked = (bool) $slot->verrouille;
                $isOpen = ! $isLocked && (bool) ($slot->periode_ouverte ?? true);
                $period = [
                    'id' => $slot->periode->getKey(),
                    'code' => $slot->periode->code,
                    'nom' => $slot->periode->nom,
                    'ordre' => $slot->periode->ordre,
                    'notes_saisies' => $encoded,
                    'notes_attendues' => $expected,
                    'pourcentage' => $percentage,
                    'statut' => $this->status($encoded, $expected),
                    'ouverte' => $isOpen,
                    'verrouillee' => $isLocked,
                ];
                $coursePeriods[] = $period;
                $courseEncoded += $encoded;
                $courseExpected += $expected;

                $aggregate = $periods[$slot->periode_id] ?? [
                    'id' => $slot->periode->getKey(),
                    'code' => $slot->periode->code,
                    'nom' => $slot->periode->nom,
                    'ordre' => $slot->periode->ordre,
                    'notes_saisies' => 0,
                    'notes_attendues' => 0,
                    'ouverte' => false,
                    'verrouillee' => true,
                ];
                $aggregate['notes_saisies'] += $encoded;
                $aggregate['notes_attendues'] += $expected;
                $aggregate['ouverte'] = $aggregate['ouverte'] || $isOpen;
                $aggregate['verrouillee'] = $aggregate['verrouillee'] && $isLocked;
                $periods[$slot->periode_id] = $aggregate;
            }

            $courses[] = [
                'attribution_id' => $assignment->getKey(),
                'node_id' => $assignment->node_id,
                'cours' => $assignment->node->libelle,
                'enseignant' => $this->teacherName($assignment),
                'notes_saisies' => $courseEncoded,
                'notes_attendues' => $courseExpected,
                'pourcentage' => $this->percentage($courseEncoded, $courseExpected),
                'statut' => $this->status($courseEncoded, $courseExpected),
                'periodes' => $coursePeriods,
            ];
            $encodedTotal += $courseEncoded;
            $expectedTotal += $courseExpected;
        }

        $periodRows = collect($periods)->map(function (array $period): array {
            $period['pourcentage'] = $this->percentage(
                $period['notes_saisies'],
                $period['notes_attendues'],
            );
            $period['statut'] = $this->status(
                $period['notes_saisies'],
                $period['notes_attendues'],
            );

            return $period;
        })->sortBy('ordre')->values()->all();

        return [
            'classe' => [
                'id' => $class->getKey(),
                'nom' => $class->nom,
                'code' => $class->code,
            ],
            'nombre_eleves' => $activeStudents,
            'resume' => [
                'notes_saisies' => $encodedTotal,
                'notes_attendues' => $expectedTotal,
                'pourcentage' => $this->percentage($encodedTotal, $expectedTotal),
                'statut' => $this->status($encodedTotal, $expectedTotal),
            ],
            'periodes' => $periodRows,
            'cours' => $courses,
        ];
    }

    /**
     * @param  Collection<int, int>  $nodeIds
     * @return array<string, int>
     */
    private function noteCounts(Classe $class, Collection $nodeIds): array
    {
        if ($nodeIds->isEmpty()) {
            return [];
        }

        return Note::query()
            ->recordedResults()
            ->whereIn('node_id', $nodeIds)
            ->whereHas('bulletin', fn ($query) => $query
                ->where('classe_id', $class->getKey())
                ->whereHas('inscription', fn ($query) => $query
                    ->where('annee_scolaire_id', $class->annee_scolaire_id)
                    ->where('statut', 'ACTIF')))
            ->selectRaw('node_id, periode_id, COUNT(DISTINCT bulletin_id) as total')
            ->groupBy('node_id', 'periode_id')
            ->get()
            ->mapWithKeys(fn (Note $note): array => [
                $this->slotKey($note->node_id, $note->periode_id) => (int) $note->getAttribute('total'),
            ])
            ->all();
    }

    private function percentage(int $encoded, int $expected): float
    {
        return $expected > 0 ? round(($encoded / $expected) * 100, 2) : 0;
    }

    private function status(int $encoded, int $expected): string
    {
        return match (true) {
            $encoded === 0 => 'NON_COMMENCE',
            $expected > 0 && $encoded >= $expected => 'TERMINE',
            default => 'EN_COURS',
        };
    }

    private function teacherName(AttributionCours $assignment): string
    {
        return collect([
            $assignment->enseignant?->nom,
            $assignment->enseignant?->postnom,
            $assignment->enseignant?->prenom,
        ])->filter()->implode(' ');
    }

    private function slotKey(int $nodeId, int $periodId): string
    {
        return "{$nodeId}:{$periodId}";
    }
}
