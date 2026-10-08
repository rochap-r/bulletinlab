<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Teacher\Exceptions\TeacherGradeSheetConflictException;
use App\Models\AttributionCours;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\ModeleMaximum;
use App\Models\Note;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\JoinClause;

final class ListMyCourseAssignmentGradeSheetService
{
    public function paginate(
        AttributionCours $assignment,
        ModeleMaximum $slot,
        int $perPage,
    ): LengthAwarePaginator {
        $schoolClass = $this->resolveSchoolClass($assignment);
        $this->assertConsistentActiveEnrollments(
            $assignment,
            $schoolClass,
            $slot,
        );

        return $this->activeEnrollmentsQuery($assignment, $schoolClass)
            ->select([
                'inscriptions.id',
                'inscriptions.tenant_id',
                'inscriptions.eleve_id',
                'inscriptions.classe_id',
                'inscriptions.annee_scolaire_id',
                'inscriptions.numero_inscription',
                'inscriptions.statut',
            ])
            ->join('eleves', function (JoinClause $join): void {
                $join
                    ->on('eleves.id', '=', 'inscriptions.eleve_id')
                    ->on(
                        'eleves.tenant_id',
                        '=',
                        'inscriptions.tenant_id',
                    );
            })
            ->where(
                'eleves.etablissement_id',
                $schoolClass->etablissement_id,
            )
            ->where('eleves.actif', true)
            ->with([
                'eleve' => fn (BelongsTo $query): BelongsTo => $query
                    ->select([
                        'id',
                        'tenant_id',
                        'etablissement_id',
                        'num_permanent',
                        'nom',
                        'postnom',
                        'prenom',
                        'sexe',
                        'actif',
                    ]),
                'bulletin' => fn (HasOne $query): HasOne => $query
                    ->select([
                        'id',
                        'tenant_id',
                        'inscription_id',
                        'classe_id',
                        'modele_bulletin_id',
                        'verrouille',
                    ])
                    ->where(
                        'modele_bulletin_id',
                        $assignment->modele_bulletin_id,
                    ),
                'bulletin.notes' => fn (HasMany $query): HasMany => $query
                    ->select([
                        'id',
                        'tenant_id',
                        'bulletin_id',
                        'node_id',
                        'periode_id',
                        'lock_version',
                        'statut_resultat',
                        'valeur',
                        'commentaire',
                        'date_saisie',
                    ])
                    ->where('node_id', $assignment->node_id)
                    ->where('periode_id', $slot->periode_id),
            ])
            ->orderBy('eleves.nom')
            ->orderBy('eleves.postnom')
            ->orderBy('eleves.prenom')
            ->orderBy('inscriptions.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function resolveSchoolClass(
        AttributionCours $assignment,
    ): Classe {
        $assignment->loadMissing([
            'classe' => fn (BelongsTo $query): BelongsTo => $query
                ->select([
                    'id',
                    'tenant_id',
                    'etablissement_id',
                    'annee_scolaire_id',
                ]),
        ]);

        $schoolClass = $assignment->classe;

        if (! $schoolClass instanceof Classe) {
            throw new TeacherGradeSheetConflictException(
                'The course assignment has no available class.',
            );
        }

        return $schoolClass;
    }

    private function assertConsistentActiveEnrollments(
        AttributionCours $assignment,
        Classe $schoolClass,
        ModeleMaximum $slot,
    ): void {
        $activeEnrollments = $this->activeEnrollmentsQuery(
            $assignment,
            $schoolClass,
        );
        $hasInvalidStudent = (clone $activeEnrollments)
            ->whereDoesntHave(
                'eleve',
                fn (Builder $student): Builder => $student
                    ->where('tenant_id', $assignment->tenant_id)
                    ->where(
                        'etablissement_id',
                        $schoolClass->etablissement_id,
                    )
                    ->where('actif', true),
            )
            ->exists();
        $hasInvalidReportCard = (clone $activeEnrollments)
            ->whereDoesntHave(
                'bulletin',
                fn (Builder $reportCard): Builder => $reportCard
                    ->where('tenant_id', $assignment->tenant_id)
                    ->where('classe_id', $assignment->classe_id)
                    ->where(
                        'modele_bulletin_id',
                        $assignment->modele_bulletin_id,
                    ),
            )
            ->exists();
        $hasInvalidNote = Note::withoutGlobalScopes()
            ->where('node_id', $assignment->node_id)
            ->where('periode_id', $slot->periode_id)
            ->where(function (Builder $note) use (
                $assignment,
                $slot,
            ): void {
                $note
                    ->where('tenant_id', '<>', $assignment->tenant_id)
                    ->orWhereNotIn(
                        'statut_resultat',
                        NoteResultStatus::values(),
                    )
                    ->orWhere(function (Builder $recorded) use ($slot): void {
                        $recorded
                            ->where(
                                'statut_resultat',
                                NoteResultStatus::Saisie->value,
                            )
                            ->where(function (Builder $range) use ($slot): void {
                                $range
                                    ->whereNull('valeur')
                                    ->orWhere('valeur', '<', 0)
                                    ->orWhere('valeur', '>', $slot->maxima);
                            });
                    })
                    ->orWhere(function (Builder $withoutValue): void {
                        $withoutValue
                            ->whereIn(
                                'statut_resultat',
                                [
                                    NoteResultStatus::Absente->value,
                                    NoteResultStatus::Dispensee->value,
                                    NoteResultStatus::Annulee->value,
                                ],
                            )
                            ->whereNotNull('valeur');
                    });
            })
            ->whereIn(
                'bulletin_id',
                $this->activeReportCardIds(
                    $assignment,
                    $schoolClass,
                ),
            )
            ->exists();

        if ($hasInvalidStudent
            || $hasInvalidReportCard
            || $hasInvalidNote) {
            throw new TeacherGradeSheetConflictException(
                'The grade sheet contains inconsistent active enrollments.',
            );
        }
    }

    /**
     * @return Builder<Inscription>
     */
    private function activeEnrollmentsQuery(
        AttributionCours $assignment,
        Classe $schoolClass,
    ): Builder {
        return Inscription::query()
            ->where('inscriptions.tenant_id', $assignment->tenant_id)
            ->where('inscriptions.classe_id', $assignment->classe_id)
            ->where(
                'inscriptions.annee_scolaire_id',
                $schoolClass->annee_scolaire_id,
            )
            ->where('inscriptions.statut', 'ACTIF');
    }

    /**
     * @return Builder<Bulletin>
     */
    private function activeReportCardIds(
        AttributionCours $assignment,
        Classe $schoolClass,
    ): Builder {
        return Bulletin::query()
            ->select('bulletins.id')
            ->where('bulletins.tenant_id', $assignment->tenant_id)
            ->where('bulletins.classe_id', $assignment->classe_id)
            ->where(
                'bulletins.modele_bulletin_id',
                $assignment->modele_bulletin_id,
            )
            ->whereIn(
                'bulletins.inscription_id',
                $this->activeEnrollmentsQuery(
                    $assignment,
                    $schoolClass,
                )->select('inscriptions.id'),
            );
    }
}
