<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Evaluation\Exceptions\NoteImportException;
use App\Domains\Evaluation\Exceptions\NoteVersionConflictException;
use App\Domains\Evaluation\Services\ReadNoteSpreadsheetService;
use App\Domains\Evaluation\Services\UpdateVersionedNoteService;
use App\Domains\Teacher\DTOs\ImportedCourseAssignmentNotes;
use App\Domains\Teacher\DTOs\LockedCourseAssignmentNoteContext;
use App\Domains\Teacher\Exceptions\TeacherNoteEntryConflictException;
use App\Models\AttributionCours;
use App\Models\Bulletin;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\ModeleMaximum;
use App\Models\Note;
use App\Models\Utilisateur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImportMyCourseAssignmentNotesService
{
    public function __construct(
        private ReadNoteSpreadsheetService $spreadsheetReader,
        private LockMyCourseAssignmentNoteContextService $lockContextService,
        private UpdateCourseAssignmentNoteProgressService $progressService,
        private UpdateVersionedNoteService $versionedNoteService,
    ) {}

    public function import(
        UploadedFile $file,
        AttributionCours $assignment,
        ModeleMaximum $slot,
        Utilisateur $teacher,
        ?string $correctionReason,
    ): ImportedCourseAssignmentNotes {
        $rows = $this->spreadsheetReader->read(
            $file,
            allowResultStatuses: true,
            allowLockVersions: true,
        );

        return $this->importRows(
            rows: $rows,
            assignment: $assignment,
            slot: $slot,
            teacher: $teacher,
            correctionReason: $correctionReason,
        );
    }

    /**
     * @param  list<array{
     *     row_number: int,
     *     matricule: string,
     *     note: ?string,
     *     status: NoteResultStatus,
     *     lock_version: ?int
     * }>  $rows
     */
    public function importRows(
        array $rows,
        AttributionCours $assignment,
        ModeleMaximum $slot,
        Utilisateur $teacher,
        ?string $correctionReason,
    ): ImportedCourseAssignmentNotes {
        return DB::transaction(function () use (
            $rows,
            $assignment,
            $slot,
            $teacher,
            $correctionReason,
        ): ImportedCourseAssignmentNotes {
            $context = $this->lockContextService->lock(
                $assignment,
                $slot,
                $teacher,
            );
            $preparedRows = $this->prepareRows($rows, $context);
            $reason = trim((string) $correctionReason);
            $hasCorrection = $preparedRows->contains(
                fn (array $row): bool => $row['note'] instanceof Note
                    && (
                        $row['note']->statut_resultat
                            !== $row['status']
                        || $row['note']->valeur !== $row['value']
                    ),
            );

            $this->assertLockVersions($preparedRows);

            if ($hasCorrection && $reason === '') {
                throw new NoteImportException(
                    'A correction reason is required to modify existing notes.',
                );
            }

            $created = 0;
            $corrected = 0;
            $unchanged = 0;
            $progressMustBeUpdated = false;
            $recordedAt = now();

            foreach ($preparedRows as $row) {
                $existingNote = $row['note'];

                if ($existingNote instanceof Note
                    && $existingNote->statut_resultat
                        === $row['status']
                    && $existingNote->valeur === $row['value']) {
                    $unchanged++;

                    continue;
                }

                if ($existingNote instanceof Note) {
                    $oldStatus = $existingNote->statut_resultat;
                    $oldValue = $existingNote->valeur;
                    $wasCounted = $oldStatus
                        ->countsTowardProgress($oldValue);
                    $existingNote = $this->versionedNoteService->update(
                        note: $existingNote,
                        expectedVersion: $row['lock_version'],
                        status: $row['status'],
                        value: $row['value'],
                        actorId: $teacher->getKey(),
                        recordedAt: $recordedAt,
                        comment: $existingNote->commentaire,
                    );
                    $existingNote->historique()->create([
                        'ancien_statut_resultat' => $oldStatus,
                        'nouveau_statut_resultat' => $row['status'],
                        'ancienne_valeur' => $oldValue,
                        'nouvelle_valeur' => $row['value'],
                        'modifie_par' => $teacher->getKey(),
                        'motif' => $reason,
                    ]);
                    $progressMustBeUpdated = $progressMustBeUpdated
                        || $wasCounted !== $row['status']
                            ->countsTowardProgress($row['value']);
                    $corrected++;

                    continue;
                }

                Note::query()->create([
                    'tenant_id' => $context->schoolClass->tenant_id,
                    'bulletin_id' => $row['report_card']->getKey(),
                    'node_id' => $context->course->getKey(),
                    'periode_id' => $context->slot->periode_id,
                    'statut_resultat' => $row['status'],
                    'valeur' => $row['value'],
                    'saisie_par' => $teacher->getKey(),
                    'date_saisie' => $recordedAt,
                ]);
                $progressMustBeUpdated = true;
                $created++;
            }

            if ($progressMustBeUpdated) {
                $this->progressService->update($context, $teacher);
            }

            return new ImportedCourseAssignmentNotes(
                total: $preparedRows->count(),
                created: $created,
                corrected: $corrected,
                unchanged: $unchanged,
            );
        }, attempts: 3);
    }

    /**
     * @param  list<array{
     *     row_number: int,
     *     matricule: string,
     *     note: ?string,
     *     status: NoteResultStatus,
     *     lock_version: ?int
     * }>  $rows
     * @return Collection<int, array{
     *     row_number: int,
     *     value: ?string,
     *     status: NoteResultStatus,
     *     lock_version: ?int,
     *     report_card: Bulletin,
     *     note: ?Note
     * }>
     */
    private function prepareRows(
        array $rows,
        LockedCourseAssignmentNoteContext $context,
    ): Collection {
        $normalizedMatricules = collect($rows)
            ->map(
                fn (array $row): string => $this->normalizeMatricule(
                    $row['matricule'],
                ),
            );

        if ($normalizedMatricules->duplicates()->isNotEmpty()) {
            throw new NoteImportException(
                'The note file contains duplicate matricules.',
            );
        }

        $studentsByMatricule = Eleve::query()
            ->select(['id', 'tenant_id', 'etablissement_id', 'num_permanent'])
            ->where('tenant_id', $context->schoolClass->tenant_id)
            ->where(
                'etablissement_id',
                $context->schoolClass->etablissement_id,
            )
            ->where('actif', true)
            ->whereIn(
                DB::raw('LOWER(TRIM(num_permanent))'),
                $normalizedMatricules->all(),
            )
            ->get()
            ->groupBy(
                fn (Eleve $student): string => $this->normalizeMatricule(
                    (string) $student->num_permanent,
                ),
            );
        $mappedRows = collect($rows)->map(function (array $row) use (
            $studentsByMatricule,
        ): array {
            $students = $studentsByMatricule->get(
                $this->normalizeMatricule($row['matricule']),
                collect(),
            );

            if ($students->count() !== 1) {
                throw new NoteImportException(
                    "Note row {$row['row_number']} does not identify one active student.",
                );
            }

            /** @var Eleve $student */
            $student = $students->first();

            return [
                ...$row,
                'student_id' => $student->getKey(),
            ];
        });
        $studentIds = $mappedRows
            ->pluck('student_id')
            ->map(fn (mixed $studentId): int => (int) $studentId)
            ->all();
        $enrollmentsByStudent = Inscription::query()
            ->where('tenant_id', $context->schoolClass->tenant_id)
            ->where('classe_id', $context->schoolClass->getKey())
            ->where('annee_scolaire_id', $context->schoolYear->getKey())
            ->where('statut', 'ACTIF')
            ->whereIn('eleve_id', $studentIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('eleve_id');
        $lockedStudents = Eleve::query()
            ->where('tenant_id', $context->schoolClass->tenant_id)
            ->where(
                'etablissement_id',
                $context->schoolClass->etablissement_id,
            )
            ->whereIn('id', $studentIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $enrollmentIds = $enrollmentsByStudent
            ->flatten()
            ->map(
                fn (Inscription $enrollment): int => $enrollment->getKey(),
            )
            ->all();
        $reportCardsByEnrollment = Bulletin::query()
            ->where('tenant_id', $context->schoolClass->tenant_id)
            ->where('classe_id', $context->schoolClass->getKey())
            ->where('modele_bulletin_id', $context->model->getKey())
            ->whereIn('inscription_id', $enrollmentIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('inscription_id');
        $reportCardIds = $reportCardsByEnrollment
            ->flatten()
            ->map(fn (Bulletin $reportCard): int => $reportCard->getKey())
            ->all();
        $notesByReportCard = Note::withoutGlobalScopes()
            ->whereIn('bulletin_id', $reportCardIds)
            ->where('node_id', $context->course->getKey())
            ->where('periode_id', $context->slot->periode_id)
            ->orderBy('bulletin_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('bulletin_id');

        return $mappedRows
            ->map(function (array $row) use (
                $context,
                $enrollmentsByStudent,
                $lockedStudents,
                $reportCardsByEnrollment,
                $notesByReportCard,
            ): array {
                $student = $lockedStudents->get($row['student_id']);

                if (! $student instanceof Eleve
                    || ! $student->actif
                    || $this->normalizeMatricule(
                        (string) $student->num_permanent,
                    ) !== $this->normalizeMatricule($row['matricule'])) {
                    throw new NoteImportException(
                        "Note row {$row['row_number']} does not identify one active student.",
                    );
                }

                $enrollments = $enrollmentsByStudent->get(
                    $student->getKey(),
                    collect(),
                );

                if ($enrollments->count() !== 1) {
                    throw new NoteImportException(
                        "Note row {$row['row_number']} does not identify one active enrollment.",
                    );
                }

                /** @var Inscription $enrollment */
                $enrollment = $enrollments->first();
                $reportCards = $reportCardsByEnrollment->get(
                    $enrollment->getKey(),
                    collect(),
                );

                if ($reportCards->count() !== 1) {
                    throw new NoteImportException(
                        "Note row {$row['row_number']} has no unique report card.",
                    );
                }

                /** @var Bulletin $reportCard */
                $reportCard = $reportCards->first();

                if ($reportCard->verrouille) {
                    throw new TeacherNoteEntryConflictException(
                        'The report card is locked.',
                    );
                }

                $value = $row['status'] === NoteResultStatus::Saisie
                    ? $this->normalizeDecimal((string) $row['note'])
                    : null;

                if ($value !== null
                    && $this->minorUnits($value)
                    > $this->minorUnits((string) $context->slot->maxima)) {
                    throw new NoteImportException(
                        "Note row {$row['row_number']} contains a note outside the allowed range.",
                    );
                }

                $notes = $notesByReportCard->get(
                    $reportCard->getKey(),
                    collect(),
                );

                if ($notes->count() > 1) {
                    throw new TeacherNoteEntryConflictException(
                        'The report card contains duplicate notes for this slot.',
                    );
                }

                /** @var ?Note $note */
                $note = $notes->first();

                if ($note instanceof Note
                    && $note->tenant_id !== $reportCard->tenant_id) {
                    throw new TeacherNoteEntryConflictException(
                        'The existing note is inconsistent with its report card.',
                    );
                }

                if ($note?->statut_resultat
                    === NoteResultStatus::Annulee) {
                    throw new NoteImportException(
                        "Note row {$row['row_number']} targets an annulled result.",
                    );
                }

                return [
                    'row_number' => $row['row_number'],
                    'value' => $value,
                    'status' => $row['status'],
                    'lock_version' => $row['lock_version'],
                    'report_card' => $reportCard,
                    'note' => $note,
                ];
            })
            ->sortBy('report_card.id')
            ->values();
    }

    /**
     * @param  Collection<int, array{
     *     row_number: int,
     *     value: ?string,
     *     status: NoteResultStatus,
     *     lock_version: ?int,
     *     report_card: Bulletin,
     *     note: ?Note
     * }>  $rows
     */
    private function assertLockVersions(Collection $rows): void
    {
        foreach ($rows as $row) {
            $note = $row['note'];
            $isReplay = $note instanceof Note
                && $note->statut_resultat === $row['status']
                && $note->valeur === $row['value'];

            if ($isReplay) {
                continue;
            }

            if (! $note instanceof Note) {
                if ($row['lock_version'] !== null) {
                    throw new NoteVersionConflictException(
                        "Note row {$row['row_number']} provides a lock version for a note that does not exist.",
                    );
                }

                continue;
            }

            if ($row['lock_version'] === null) {
                throw new NoteVersionConflictException(
                    "Note row {$row['row_number']} requires the current note lock version.",
                );
            }

            if ($note->lock_version !== $row['lock_version']) {
                throw new NoteVersionConflictException(
                    "Note row {$row['row_number']} was modified by another request.",
                );
            }
        }
    }

    private function normalizeMatricule(string $matricule): string
    {
        return Str::lower(trim($matricule));
    }

    private function normalizeDecimal(string $value): string
    {
        $parts = explode('.', $value, 2);
        $integer = ltrim($parts[0], '0');
        $fraction = $parts[1] ?? '';

        return ($integer === '' ? '0' : $integer)
            .'.'
            .str_pad($fraction, 2, '0');
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
}
