<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\Exceptions\TeacherAssignmentListingConflictException;
use App\Models\AttributionCours;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\JoinClause;

final class ListMyCourseAssignmentsService
{
    public function paginate(
        Utilisateur $teacher,
        int $establishmentId,
        int $perPage,
    ): LengthAwarePaginator {
        $today = $this->currentBusinessDate();
        $assignments = $this->currentAssignmentsQuery(
            $teacher,
            $establishmentId,
            $today,
        );
        $this->assertNoConflictingAssignments(
            $assignments,
            $teacher,
            $today,
        );

        return $assignments
            ->select($this->assignmentColumns())
            ->with([
                'classe' => fn (BelongsTo $query): BelongsTo => $query
                    ->select([
                        'id',
                        'tenant_id',
                        'etablissement_id',
                        'annee_scolaire_id',
                        'nom',
                        'code',
                        'active',
                    ]),
                'classe.anneeScolaire' => fn (BelongsTo $query): BelongsTo => $query
                    ->select([
                        'id',
                        'libelle',
                        'date_debut',
                        'date_fin',
                        'active',
                        'cloturee',
                    ]),
                'node' => fn (BelongsTo $query): BelongsTo => $query
                    ->select([
                        'id',
                        'tenant_id',
                        'modele_bulletin_id',
                        'matiere_id',
                        'libelle',
                        'ordre',
                    ]),
            ])
            ->orderByDesc('annees_scolaires.date_debut')
            ->orderBy('classes.nom')
            ->orderBy('modele_nodes.ordre')
            ->orderBy('attributions_cours.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findCurrent(
        Utilisateur $teacher,
        int $establishmentId,
        int $assignmentId,
    ): AttributionCours {
        $today = $this->currentBusinessDate();
        $assignments = $this->currentAssignmentsQuery(
            $teacher,
            $establishmentId,
            $today,
        )->where('attributions_cours.id', $assignmentId);

        $this->assertNoConflictingAssignments(
            $assignments,
            $teacher,
            $today,
        );

        return $assignments
            ->select($this->assignmentColumns())
            ->firstOrFail();
    }

    /**
     * @param  Builder<AttributionCours>  $assignments
     */
    private function assertNoConflictingAssignments(
        Builder $assignments,
        Utilisateur $teacher,
        CarbonImmutable $today,
    ): void {
        $teacherSlots = (clone $assignments)
            ->select([
                'attributions_cours.classe_id',
                'attributions_cours.modele_bulletin_id',
                'attributions_cours.node_id',
            ]);

        $hasConflictingAssignment = AttributionCours::query()
            ->joinSub(
                $teacherSlots,
                'teacher_slots',
                function (JoinClause $join): void {
                    $join
                        ->on(
                            'teacher_slots.classe_id',
                            '=',
                            'attributions_cours.classe_id',
                        )
                        ->on(
                            'teacher_slots.modele_bulletin_id',
                            '=',
                            'attributions_cours.modele_bulletin_id',
                        )
                        ->on(
                            'teacher_slots.node_id',
                            '=',
                            'attributions_cours.node_id',
                        );
                },
            )
            ->where(
                'attributions_cours.tenant_id',
                $teacher->tenant_id,
            )
            ->where(
                'attributions_cours.enseignant_id',
                '<>',
                $teacher->getKey(),
            )
            ->effectiveOn($today)
            ->exists();

        if ($hasConflictingAssignment) {
            throw new TeacherAssignmentListingConflictException(
                'An active course assignment is inconsistent.',
            );
        }
    }

    /**
     * @return Builder<AttributionCours>
     */
    private function currentAssignmentsQuery(
        Utilisateur $teacher,
        int $establishmentId,
        CarbonImmutable $today,
    ): Builder {
        $schoolYearStartBoundary = $today->endOfDay()->toDateTimeString();
        $schoolYearEndBoundary = $today->toDateString();

        return AttributionCours::query()
            ->join('classes', function (JoinClause $join): void {
                $join
                    ->on(
                        'classes.id',
                        '=',
                        'attributions_cours.classe_id',
                    )
                    ->on(
                        'classes.tenant_id',
                        '=',
                        'attributions_cours.tenant_id',
                    );
            })
            ->join('annees_scolaires', function (JoinClause $join): void {
                $join
                    ->on(
                        'annees_scolaires.id',
                        '=',
                        'classes.annee_scolaire_id',
                    );
            })
            ->join('modeles_bulletin', function (JoinClause $join): void {
                $join
                    ->on(
                        'modeles_bulletin.id',
                        '=',
                        'attributions_cours.modele_bulletin_id',
                    )
                    ->on(
                        'modeles_bulletin.tenant_id',
                        '=',
                        'attributions_cours.tenant_id',
                    )
                    ->on(
                        'modeles_bulletin.classe_id',
                        '=',
                        'classes.id',
                    )
                    ->on(
                        'modeles_bulletin.annee_scolaire_id',
                        '=',
                        'annees_scolaires.id',
                    );
            })
            ->join('modele_nodes', function (JoinClause $join): void {
                $join
                    ->on(
                        'modele_nodes.id',
                        '=',
                        'attributions_cours.node_id',
                    )
                    ->on(
                        'modele_nodes.tenant_id',
                        '=',
                        'attributions_cours.tenant_id',
                    )
                    ->on(
                        'modele_nodes.modele_bulletin_id',
                        '=',
                        'modeles_bulletin.id',
                    );
            })
            ->where(
                'attributions_cours.tenant_id',
                $teacher->tenant_id,
            )
            ->where(
                'attributions_cours.enseignant_id',
                $teacher->getKey(),
            )
            ->where('classes.etablissement_id', $establishmentId)
            ->where('classes.active', true)
            ->where('annees_scolaires.active', true)
            ->where('annees_scolaires.cloturee', false)
            ->where(
                'annees_scolaires.date_debut',
                '<=',
                $schoolYearStartBoundary,
            )
            ->where(
                'annees_scolaires.date_fin',
                '>=',
                $schoolYearEndBoundary,
            )
            ->where('modeles_bulletin.actif', true)
            ->where('modele_nodes.type_node', 'MATIERE')
            ->effectiveOn($today);
    }

    private function currentBusinessDate(): CarbonImmutable
    {
        return CarbonImmutable::today(
            (string) config('app.business_timezone'),
        );
    }

    /**
     * @return list<string>
     */
    private function assignmentColumns(): array
    {
        return [
            'attributions_cours.id',
            'attributions_cours.tenant_id',
            'attributions_cours.classe_id',
            'attributions_cours.modele_bulletin_id',
            'attributions_cours.node_id',
            'attributions_cours.enseignant_id',
            'attributions_cours.date_debut',
            'attributions_cours.date_fin',
            'attributions_cours.actif',
        ];
    }
}
