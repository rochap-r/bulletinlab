<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\Exceptions\TeacherAssignmentSlotListingConflictException;
use App\Models\AttributionCours;
use App\Models\ModeleMaximum;
use App\Models\VerrouillagePeriode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

final class ListMyCourseAssignmentSlotsService
{
    public function paginate(
        AttributionCours $assignment,
        int $perPage,
    ): LengthAwarePaginator {
        $this->assertConsistentSlots($assignment);

        $slots = $this->eligibleSlotsQuery($assignment)
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
     * @return Builder<ModeleMaximum>
     */
    private function eligibleSlotsQuery(
        AttributionCours $assignment,
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

        return ModeleMaximum::query()
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
            ->where('modele_periodes.saisissable', true)
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
                        'actif',
                    ]),
            ]);
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
