<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Evaluation\Exceptions\PeriodLockedException;
use App\Domains\Teacher\DTOs\LockedCourseAssignmentNoteContext;
use App\Domains\Teacher\Exceptions\TeacherNoteEntryConflictException;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
use App\Models\Classe;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Utilisateur;
use App\Models\VerrouillagePeriode;
use Carbon\CarbonImmutable;

final class LockMyCourseAssignmentNoteContextService
{
    public function lock(
        AttributionCours $assignment,
        ModeleMaximum $slot,
        Utilisateur $teacher,
    ): LockedCourseAssignmentNoteContext {
        $today = $this->currentBusinessDate();
        $schoolClass = $this->lockSchoolClass($assignment, $teacher);
        $schoolYear = $this->lockSchoolYear($schoolClass, $today);
        $model = $this->lockReportCardModel(
            $assignment,
            $schoolClass,
            $schoolYear,
        );
        $course = $this->lockCourse($assignment, $model);
        $lockedSlot = $this->lockEntrySlot(
            $slot,
            $assignment,
            $model,
            $course,
        );
        $this->lockOpenPeriodState($schoolClass, $lockedSlot);
        $this->lockCurrentAssignment($assignment, $teacher, $today);

        return new LockedCourseAssignmentNoteContext(
            schoolClass: $schoolClass,
            schoolYear: $schoolYear,
            model: $model,
            course: $course,
            slot: $lockedSlot,
        );
    }

    private function lockSchoolClass(
        AttributionCours $assignment,
        Utilisateur $teacher,
    ): Classe {
        $schoolClass = Classe::query()
            ->whereKey($assignment->classe_id)
            ->where('tenant_id', $teacher->tenant_id)
            ->where('etablissement_id', $teacher->etablissement_id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $schoolClass->active) {
            throw new TeacherNoteEntryConflictException(
                'Notes cannot be recorded for an archived class.',
            );
        }

        return $schoolClass;
    }

    private function lockSchoolYear(
        Classe $schoolClass,
        CarbonImmutable $today,
    ): AnneeScolaire {
        $schoolYear = AnneeScolaire::withoutGlobalScopes()
            ->whereKey($schoolClass->annee_scolaire_id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $schoolYear->active
            || $schoolYear->cloturee
            || $today->lt(CarbonImmutable::parse($schoolYear->date_debut))
            || $today->gt(CarbonImmutable::parse($schoolYear->date_fin))) {
            throw new TeacherNoteEntryConflictException(
                'Notes cannot be recorded outside an open school year.',
            );
        }

        return $schoolYear;
    }

    private function lockReportCardModel(
        AttributionCours $assignment,
        Classe $schoolClass,
        AnneeScolaire $schoolYear,
    ): ModeleBulletin {
        $model = ModeleBulletin::query()
            ->whereKey($assignment->modele_bulletin_id)
            ->where('tenant_id', $schoolClass->tenant_id)
            ->where('classe_id', $schoolClass->getKey())
            ->where('annee_scolaire_id', $schoolYear->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if (! $model->actif) {
            throw new TeacherNoteEntryConflictException(
                'The report card model is no longer active.',
            );
        }

        return $model;
    }

    private function lockCourse(
        AttributionCours $assignment,
        ModeleBulletin $model,
    ): ModeleNode {
        return ModeleNode::query()
            ->whereKey($assignment->node_id)
            ->where('tenant_id', $model->tenant_id)
            ->where('modele_bulletin_id', $model->getKey())
            ->where('type_node', 'MATIERE')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockEntrySlot(
        ModeleMaximum $slot,
        AttributionCours $assignment,
        ModeleBulletin $model,
        ModeleNode $course,
    ): ModeleMaximum {
        $lockedSlot = ModeleMaximum::query()
            ->whereKey($slot->getKey())
            ->where('tenant_id', $model->tenant_id)
            ->where('modele_bulletin_id', $model->getKey())
            ->where('node_id', $course->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $period = ModelePeriode::query()
            ->whereKey($lockedSlot->periode_id)
            ->where('tenant_id', $model->tenant_id)
            ->where('modele_bulletin_id', $model->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ((float) $lockedSlot->maxima <= 0
            || ! $period->actif
            || ! $period->saisissable
            || $assignment->node_id !== $lockedSlot->node_id) {
            throw new TeacherNoteEntryConflictException(
                'The grade entry slot is no longer available.',
            );
        }

        return $lockedSlot;
    }

    private function lockOpenPeriodState(
        Classe $schoolClass,
        ModeleMaximum $slot,
    ): void {
        $periodState = VerrouillagePeriode::query()
            ->where('tenant_id', $schoolClass->tenant_id)
            ->where('classe_id', $schoolClass->getKey())
            ->where('periode_id', $slot->periode_id)
            ->lockForUpdate()
            ->first();

        if ($periodState?->verrouille) {
            throw new PeriodLockedException(
                'The note entry period is locked.',
            );
        }
    }

    private function lockCurrentAssignment(
        AttributionCours $assignment,
        Utilisateur $teacher,
        CarbonImmutable $today,
    ): void {
        $currentAssignments = AttributionCours::query()
            ->where('tenant_id', $assignment->tenant_id)
            ->where('classe_id', $assignment->classe_id)
            ->where(
                'modele_bulletin_id',
                $assignment->modele_bulletin_id,
            )
            ->where('node_id', $assignment->node_id)
            ->effectiveOn($today)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($currentAssignments->count() !== 1
            || $currentAssignments->first()?->getKey() !== $assignment->getKey()
            || $currentAssignments->first()?->enseignant_id !== $teacher->getKey()) {
            throw new TeacherNoteEntryConflictException(
                'The course assignment is no longer current.',
            );
        }
    }

    private function currentBusinessDate(): CarbonImmutable
    {
        return CarbonImmutable::today(
            (string) config('app.business_timezone'),
        );
    }
}
