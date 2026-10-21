<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Evaluation\Exceptions\PeriodLockedException;
use App\Domains\Evaluation\Services\ReadNoteSpreadsheetService;
use App\Domains\Teacher\DTOs\ImportedCourseAssignmentNotes;
use App\Models\AttributionCours;
use App\Models\ModeleMaximum;
use App\Models\Utilisateur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ImportMyCourseAssignmentGradeMatrixService
{
    public function __construct(
        private ReadNoteSpreadsheetService $spreadsheetReader,
        private ImportMyCourseAssignmentNotesService $importNotesService,
    ) {}

    /**
     * @param  Collection<int, ModeleMaximum>  $columns
     */
    public function import(
        UploadedFile $file,
        AttributionCours $assignment,
        Collection $columns,
        Utilisateur $teacher,
        ?string $correctionReason,
    ): ImportedCourseAssignmentNotes {
        $rowsBySlot = $this->spreadsheetReader->readMatrix($file, $columns);

        $closedColumn = $columns->first(function (ModeleMaximum $column) use ($rowsBySlot): bool {
            $hasImportedRows = ($rowsBySlot[$column->getKey()] ?? []) !== [];

            return $hasImportedRows
                && (bool) $column->periode->saisissable
                && ((bool) $column->verrouille || ! (bool) $column->getAttribute('periode_ouverte'));
        });

        if ($closedColumn instanceof ModeleMaximum) {
            throw new PeriodLockedException('The note entry period is closed on the school calendar.');
        }

        return DB::transaction(function () use (
            $rowsBySlot,
            $assignment,
            $columns,
            $teacher,
            $correctionReason,
        ): ImportedCourseAssignmentNotes {
            $total = 0;
            $created = 0;
            $corrected = 0;
            $unchanged = 0;

            foreach ($columns as $column) {
                $rows = $rowsBySlot[$column->getKey()] ?? [];

                if ($rows === []) {
                    continue;
                }

                $result = $this->importNotesService->importRows(
                    rows: $rows,
                    assignment: $assignment,
                    slot: $column,
                    teacher: $teacher,
                    correctionReason: $correctionReason,
                );
                $total += $result->total;
                $created += $result->created;
                $corrected += $result->corrected;
                $unchanged += $result->unchanged;
            }

            return new ImportedCourseAssignmentNotes(
                total: $total,
                created: $created,
                corrected: $corrected,
                unchanged: $unchanged,
            );
        }, attempts: 3);
    }
}
