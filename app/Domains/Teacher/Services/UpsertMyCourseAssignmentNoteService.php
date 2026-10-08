<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Evaluation\Exceptions\EvaluationException;
use App\Domains\Evaluation\Exceptions\NoteVersionConflictException;
use App\Domains\Evaluation\Services\UpdateVersionedNoteService;
use App\Domains\Teacher\DTOs\UpsertedCourseAssignmentNote;
use App\Domains\Teacher\Exceptions\TeacherNoteEntryConflictException;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\Note;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class UpsertMyCourseAssignmentNoteService
{
    public function __construct(
        private LockMyCourseAssignmentNoteContextService $lockContextService,
        private UpdateCourseAssignmentNoteProgressService $progressService,
        private UpdateVersionedNoteService $versionedNoteService,
    ) {}

    public function upsert(
        AttributionCours $assignment,
        ModeleMaximum $slot,
        int $enrollmentId,
        Utilisateur $teacher,
        NoteResultStatus $status,
        int|float|string|null $value,
        ?string $comment,
        ?string $correctionReason,
        ?int $expectedVersion,
    ): UpsertedCourseAssignmentNote {
        return DB::transaction(function () use (
            $assignment,
            $slot,
            $enrollmentId,
            $teacher,
            $status,
            $value,
            $comment,
            $correctionReason,
            $expectedVersion,
        ): UpsertedCourseAssignmentNote {
            $context = $this->lockContextService->lock(
                $assignment,
                $slot,
                $teacher,
            );
            $schoolClass = $context->schoolClass;
            $schoolYear = $context->schoolYear;
            $model = $context->model;
            $course = $context->course;
            $lockedSlot = $context->slot;
            $enrollment = $this->lockEnrollment(
                $enrollmentId,
                $schoolClass,
                $schoolYear,
            );
            $this->lockActiveStudent($enrollment, $schoolClass);
            $reportCard = $this->lockReportCard(
                $enrollment,
                $schoolClass,
                $model,
            );
            $normalizedValue = $this->normalizeResultValue(
                $status,
                $value,
                $lockedSlot,
            );
            $existingNote = $this->lockExistingNote(
                $reportCard,
                $course,
                $lockedSlot,
            );
            $normalizedComment = $comment === null
                ? null
                : trim($comment);
            $normalizedComment = $normalizedComment === ''
                ? null
                : $normalizedComment;

            if ($existingNote?->statut_resultat
                === NoteResultStatus::Annulee) {
                throw new EvaluationException(
                    'An annulled result cannot be modified by a teacher.',
                );
            }

            if ($existingNote instanceof Note
                && $existingNote->statut_resultat === $status
                && $existingNote->valeur === $normalizedValue
                && $existingNote->commentaire === $normalizedComment) {
                return $this->result(
                    $existingNote,
                    $lockedSlot,
                );
            }

            if ($existingNote instanceof Note) {
                $this->versionedNoteService->assertExpectedVersion(
                    $existingNote,
                    $expectedVersion,
                );
                $reason = trim((string) $correctionReason);

                if ($reason === '') {
                    throw new EvaluationException(
                        'A correction reason is required to modify an existing note.',
                    );
                }

                $oldStatus = $existingNote->statut_resultat;
                $oldValue = $existingNote->valeur;
                $wasCounted = $oldStatus->countsTowardProgress($oldValue);
                $existingNote = $this->versionedNoteService->update(
                    note: $existingNote,
                    expectedVersion: $expectedVersion,
                    status: $status,
                    value: $normalizedValue,
                    actorId: $teacher->getKey(),
                    recordedAt: now(),
                    comment: $normalizedComment,
                );
                $existingNote->historique()->create([
                    'ancien_statut_resultat' => $oldStatus,
                    'nouveau_statut_resultat' => $status,
                    'ancienne_valeur' => $oldValue,
                    'nouvelle_valeur' => $normalizedValue,
                    'modifie_par' => $teacher->getKey(),
                    'motif' => $reason,
                ]);

                if ($wasCounted
                    !== $status->countsTowardProgress($normalizedValue)) {
                    $this->progressService->update($context, $teacher);
                }

                return $this->result(
                    $existingNote,
                    $lockedSlot,
                );
            }

            if ($expectedVersion !== null) {
                throw new NoteVersionConflictException(
                    'A lock version cannot target a note that does not exist.',
                );
            }

            $note = Note::query()->create([
                'tenant_id' => $schoolClass->tenant_id,
                'bulletin_id' => $reportCard->getKey(),
                'node_id' => $course->getKey(),
                'periode_id' => $lockedSlot->periode_id,
                'statut_resultat' => $status,
                'valeur' => $normalizedValue,
                'saisie_par' => $teacher->getKey(),
                'date_saisie' => now(),
                'commentaire' => $normalizedComment,
            ]);
            $this->progressService->update($context, $teacher);

            return $this->result(
                $note,
                $lockedSlot,
            );
        }, attempts: 3);
    }

    private function lockEnrollment(
        int $enrollmentId,
        Classe $schoolClass,
        AnneeScolaire $schoolYear,
    ): Inscription {
        $enrollment = Inscription::query()
            ->whereKey($enrollmentId)
            ->where('tenant_id', $schoolClass->tenant_id)
            ->where('classe_id', $schoolClass->getKey())
            ->where('annee_scolaire_id', $schoolYear->getKey())
            ->lockForUpdate()
            ->first();

        if (! $enrollment instanceof Inscription) {
            throw (new ModelNotFoundException)->setModel(
                Inscription::class,
                [$enrollmentId],
            );
        }

        if ($enrollment->statut !== 'ACTIF') {
            throw new TeacherNoteEntryConflictException(
                'Notes can only be recorded for an active enrollment.',
            );
        }

        return $enrollment;
    }

    private function lockActiveStudent(
        Inscription $enrollment,
        Classe $schoolClass,
    ): void {
        $student = Eleve::query()
            ->whereKey($enrollment->eleve_id)
            ->where('tenant_id', $schoolClass->tenant_id)
            ->where('etablissement_id', $schoolClass->etablissement_id)
            ->lockForUpdate()
            ->first();

        if (! $student instanceof Eleve || ! $student->actif) {
            throw new TeacherNoteEntryConflictException(
                'Notes can only be recorded for an active student.',
            );
        }
    }

    private function lockReportCard(
        Inscription $enrollment,
        Classe $schoolClass,
        ModeleBulletin $model,
    ): Bulletin {
        $reportCards = Bulletin::query()
            ->where('tenant_id', $schoolClass->tenant_id)
            ->where('inscription_id', $enrollment->getKey())
            ->where('classe_id', $schoolClass->getKey())
            ->where('modele_bulletin_id', $model->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($reportCards->count() !== 1) {
            throw new TeacherNoteEntryConflictException(
                'The enrollment has no unique report card for this class.',
            );
        }

        $reportCard = $reportCards->firstOrFail();

        if ($reportCard->verrouille) {
            throw new TeacherNoteEntryConflictException(
                'The report card is locked.',
            );
        }

        return $reportCard;
    }

    private function lockExistingNote(
        Bulletin $reportCard,
        ModeleNode $course,
        ModeleMaximum $slot,
    ): ?Note {
        $note = Note::withoutGlobalScopes()
            ->where('bulletin_id', $reportCard->getKey())
            ->where('node_id', $course->getKey())
            ->where('periode_id', $slot->periode_id)
            ->lockForUpdate()
            ->first();

        if ($note instanceof Note
            && $note->tenant_id !== $reportCard->tenant_id) {
            throw new TeacherNoteEntryConflictException(
                'The existing note is inconsistent with its report card.',
            );
        }

        return $note;
    }

    private function normalizeResultValue(
        NoteResultStatus $status,
        int|float|string|null $value,
        ModeleMaximum $slot,
    ): ?string {
        if (! $status->isTeacherEditable()) {
            throw new EvaluationException(
                'Teachers cannot set this result status.',
            );
        }

        if ($status !== NoteResultStatus::Saisie) {
            if ($value !== null && $value !== '') {
                throw new EvaluationException(
                    'A grade value is only allowed for a recorded result.',
                );
            }

            return null;
        }

        if ($value === null || $value === '') {
            throw new EvaluationException(
                'A grade value is required for a recorded result.',
            );
        }

        $normalizedValue = $this->normalizeDecimal($value);

        if ($this->minorUnits($normalizedValue) < 0) {
            throw new EvaluationException(
                'A grade value cannot be negative.',
            );
        }

        $this->assertWithinMaximum(
            $normalizedValue,
            (string) $slot->maxima,
        );

        return $normalizedValue;
    }

    private function normalizeDecimal(int|float|string $value): string
    {
        $parts = explode('.', (string) $value, 2);
        $integer = ltrim($parts[0], '0');
        $fraction = $parts[1] ?? '';

        return ($integer === '' ? '0' : $integer)
            .'.'
            .str_pad($fraction, 2, '0');
    }

    private function assertWithinMaximum(
        string $value,
        string $maximum,
    ): void {
        if ($this->minorUnits($value) > $this->minorUnits($maximum)) {
            throw new EvaluationException(
                'The note exceeds the configured maximum.',
            );
        }
    }

    private function minorUnits(string $value): int
    {
        $isNegative = str_starts_with($value, '-');
        $unsignedValue = ltrim($value, '+-');
        [$integer, $fraction] = array_pad(
            explode('.', $unsignedValue, 2),
            2,
            '',
        );

        $minorUnits = ((int) $integer * 100)
            + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $isNegative ? -$minorUnits : $minorUnits;
    }

    private function result(
        Note $note,
        ModeleMaximum $slot,
    ): UpsertedCourseAssignmentNote {
        $note->loadMissing('bulletin.inscription.eleve');

        return new UpsertedCourseAssignmentNote(
            note: $note,
            entrySlotId: $slot->getKey(),
        );
    }
}
