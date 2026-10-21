<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\Exceptions\TeacherAssignmentSlotListingConflictException;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
use App\Models\CalendrierPeriode;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\VerrouillagePeriode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListMyCourseAssignmentSlotsService
{
    public function paginate(
        AttributionCours $assignment,
        int $perPage,
        bool $includeCalculated = false,
    ): LengthAwarePaginator {
        $this->assertConsistentSlots($assignment);

        $slots = $this->slotsQuery($assignment, $includeCalculated)
            ->orderBy('modele_periodes.ordre')
            ->orderBy('modele_periodes.id')
            ->orderBy('modele_maxima.id')
            ->paginate($perPage)
            ->withQueryString();

        $this->attachAssignmentId($slots->getCollection(), $assignment);

        return $slots;
    }

    public function findEligible(
        AttributionCours $assignment,
        int $slotId,
    ): ModeleMaximum {
        $this->assertConsistentSlots($assignment);

        $slot = $this->eligibleSlotsQuery($assignment)
            ->where('modele_maxima.id', $slotId)
            ->firstOrFail();
        $slot->setAttribute('assignment_id', $assignment->getKey());

        return $slot;
    }

    /**
     * @return Collection<int, ModeleMaximum>
     */
    public function matrixColumns(AttributionCours $assignment): Collection
    {
        $this->assertConsistentSlots($assignment);

        $columns = $this->slotsQuery($assignment, true)
            ->orderBy('modele_periodes.ordre')
            ->orderBy('modele_periodes.id')
            ->orderBy('modele_maxima.id')
            ->get();

        $this->attachAssignmentId($columns, $assignment);

        return $columns;
    }

    /**
     * @return Builder<ModeleMaximum>
     */
    private function eligibleSlotsQuery(
        AttributionCours $assignment,
    ): Builder {
        return $this->slotsQuery($assignment, false);
    }

    /**
     * @return Builder<ModeleMaximum>
     */
    private function slotsQuery(
        AttributionCours $assignment,
        bool $includeCalculated,
    ): Builder {
        $lockedPeriod = VerrouillagePeriode::query()
            ->selectRaw('1')
            ->where(
                'verrouillages_periodes.tenant_id',
                $assignment->tenant_id,
            )
            ->where(
                'verrouillages_periodes.classe_id',
                $assignment->classe_id,
            )
            ->whereColumn(
                'verrouillages_periodes.periode_id',
                'modele_maxima.periode_id',
            )
            ->where('verrouillages_periodes.verrouille', true)
            ->limit(1);

        $query = ModeleMaximum::query()
            ->select([
                'modele_maxima.id',
                'modele_maxima.tenant_id',
                'modele_maxima.modele_bulletin_id',
                'modele_maxima.node_id',
                'modele_maxima.periode_id',
                'modele_maxima.maxima',
                'modele_maxima.coefficient',
            ])
            ->addSelect(['verrouille' => $lockedPeriod])
            ->join('modele_periodes', function (JoinClause $join): void {
                $join
                    ->on(
                        'modele_periodes.id',
                        '=',
                        'modele_maxima.periode_id',
                    )
                    ->on(
                        'modele_periodes.tenant_id',
                        '=',
                        'modele_maxima.tenant_id',
                    )
                    ->on(
                        'modele_periodes.modele_bulletin_id',
                        '=',
                        'modele_maxima.modele_bulletin_id',
                    );
            })
            ->where('modele_maxima.tenant_id', $assignment->tenant_id)
            ->where(
                'modele_maxima.modele_bulletin_id',
                $assignment->modele_bulletin_id,
            )
            ->where('modele_maxima.node_id', $assignment->node_id)
            ->where('modele_maxima.maxima', '>', 0)
            ->where('modele_periodes.actif', true)
            ->with([
                'periode' => fn (BelongsTo $query): BelongsTo => $query
                    ->select([
                        'id',
                        'tenant_id',
                        'modele_bulletin_id',
                        'code',
                        'nom',
                        'type_periode',
                        'ordre',
                        'saisissable',
                        'calculable',
                        'actif',
                    ]),
            ]);

        if (! $includeCalculated) {
            $query->where('modele_periodes.saisissable', true);
        }

        $this->applyCalendarOpenness($query, $assignment);

        return $query;
    }

    /**
     * Exposes whether the period is currently inside its school calendar
     * window. Years without a calendar or template periods without a mapping
     * stay open so schools outside calendar governance are unaffected.
     */
    private function applyCalendarOpenness(
        Builder $query,
        AttributionCours $assignment,
    ): void {
        $schoolYearId = $assignment->offsetExists('calendar_school_year_id')
            ? $assignment->getAttribute('calendar_school_year_id')
            : null;
        $calendarId = $assignment->offsetExists('calendar_id')
            ? $assignment->getAttribute('calendar_id')
            : null;

        if ($schoolYearId === null && ! $assignment->offsetExists('calendar_school_year_id')) {
            $schoolYearId = ModeleBulletin::query()
                ->whereKey($assignment->modele_bulletin_id)
                ->where('tenant_id', $assignment->tenant_id)
                ->value('annee_scolaire_id');
            $calendarId = $schoolYearId === null
                ? null
                : AnneeScolaire::withoutGlobalScopes()
                    ->whereKey($schoolYearId)
                    ->value('calendrier_scolaire_id');
        }

        if ($schoolYearId === null || $calendarId === null) {
            $query->addSelect([
                DB::raw('1 as "periode_ouverte"'),
            ]);

            return;
        }

        $today = CarbonImmutable::today(
            (string) config('app.business_timezone'),
        )->toDateString();

        $closedCalendarPeriod = CalendrierPeriode::query()
            ->selectRaw('1')
            ->join(
                'annee_periode_mappings',
                fn (JoinClause $join): JoinClause => $join
                    ->on(
                        'annee_periode_mappings.calendrier_periode_id',
                        '=',
                        'calendrier_periodes.id',
                    ),
            )
            ->whereColumn(
                'annee_periode_mappings.code_periode_template',
                'modele_periodes.code',
            )
            ->where('annee_periode_mappings.annee_scolaire_id', $schoolYearId)
            ->where(
                'calendrier_periodes.calendrier_scolaire_id',
                $calendarId,
            )
            ->where(function (Builder $periodQuery) use ($today): void {
                $periodQuery
                    ->where('calendrier_periodes.date_debut', '>', $today)
                    ->orWhere('calendrier_periodes.date_fin', '<', $today);
            })
            ->limit(1);

        $query->addSelect([
            DB::raw(
                '(CASE WHEN EXISTS ('.$closedCalendarPeriod->toSql().') THEN 0 ELSE 1 END) as "periode_ouverte"',
            ),
        ]);
        $query->addBinding($closedCalendarPeriod->getBindings(), 'select');
    }

    /**
     * @param  Collection<int, ModeleMaximum>  $slots
     */
    private function attachAssignmentId(
        Collection $slots,
        AttributionCours $assignment,
    ): void {
        $slots->each(
            fn (ModeleMaximum $slot): ModeleMaximum => $slot->setAttribute(
                'assignment_id',
                $assignment->getKey(),
            ),
        );
    }

    private function assertConsistentSlots(
        AttributionCours $assignment,
    ): void {
        $hasInconsistentSlot = ModeleMaximum::query()
            ->where('tenant_id', $assignment->tenant_id)
            ->where('node_id', $assignment->node_id)
            ->where(function (Builder $query) use ($assignment): void {
                $query
                    ->where(
                        'modele_bulletin_id',
                        '<>',
                        $assignment->modele_bulletin_id,
                    )
                    ->orWhereDoesntHave(
                        'periode',
                        fn (Builder $period): Builder => $period
                            ->where(
                                'tenant_id',
                                $assignment->tenant_id,
                            )
                            ->where(
                                'modele_bulletin_id',
                                $assignment->modele_bulletin_id,
                            ),
                    );
            })
            ->exists();

        if ($hasInconsistentSlot) {
            throw new TeacherAssignmentSlotListingConflictException(
                'The course assignment contains inconsistent entry slots.',
            );
        }
    }
}
