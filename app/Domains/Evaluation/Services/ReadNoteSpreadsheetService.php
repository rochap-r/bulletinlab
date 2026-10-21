<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Evaluation\Exceptions\NoteImportException;
use App\Domains\Evaluation\Imports\NoteSpreadsheetImport;
use App\Models\ModeleMaximum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Throwable;
use ZipArchive;

final class ReadNoteSpreadsheetService
{
    private const MAX_ROWS = 1000;

    public function __construct(
        private Excel $excel,
    ) {}

    /**
     * @return list<array{
     *     row_number: int,
     *     matricule: string,
     *     note: ?string,
     *     status: NoteResultStatus,
     *     lock_version: ?int
     * }>
     */
    public function read(
        UploadedFile $file,
        bool $allowResultStatuses = false,
        bool $allowLockVersions = true,
    ): array {
        $extension = Str::lower($file->getClientOriginalExtension());
        $readerType = match ($extension) {
            'csv' => Excel::CSV,
            'xlsx' => Excel::XLSX,
            default => throw new NoteImportException(
                'Only CSV and XLSX note files are supported.',
            ),
        };
        $this->assertSafeDimensions(
            $file,
            $extension,
            2
                + ($allowResultStatuses ? 1 : 0)
                + ($allowLockVersions ? 1 : 0),
        );

        try {
            $sheets = $this->excel->toArray(
                new NoteSpreadsheetImport(self::MAX_ROWS + 2),
                $file,
                readerType: $readerType,
            );
        } catch (Throwable) {
            throw new NoteImportException(
                'The CSV or XLSX note file could not be read.',
            );
        }

        $nonEmptySheets = collect($sheets)
            ->filter(
                fn (array $sheet): bool => collect($sheet)
                    ->contains(fn (array $row): bool => ! $this->isEmptyRow($row)),
            )
            ->values();

        if ($nonEmptySheets->count() !== 1) {
            throw new NoteImportException(
                'The note file must contain exactly one non-empty worksheet.',
            );
        }

        /** @var list<array<int, mixed>> $sheet */
        $sheet = $nonEmptySheets->first();

        if (count($sheet) >= self::MAX_ROWS + 2) {
            throw new NoteImportException(
                'The note file exceeds the limit of '.self::MAX_ROWS.' data rows.',
            );
        }

        $header = array_shift($sheet);

        $headerDefinition = is_array($header)
            ? collect([
                [
                    'columns' => ['matricule', 'note'],
                    'with_status' => false,
                    'with_lock_version' => false,
                ],
                ...($allowResultStatuses ? [[
                    'columns' => [
                        'matricule',
                        'note',
                        'statut_resultat',
                    ],
                    'with_status' => true,
                    'with_lock_version' => false,
                ]] : []),
                ...($allowLockVersions ? [[
                    'columns' => ['matricule', 'note', 'lock_version'],
                    'with_status' => false,
                    'with_lock_version' => true,
                ]] : []),
                ...($allowResultStatuses && $allowLockVersions ? [[
                    'columns' => [
                        'matricule',
                        'note',
                        'statut_resultat',
                        'lock_version',
                    ],
                    'with_status' => true,
                    'with_lock_version' => true,
                ]] : []),
            ])->first(
                fn (array $definition): bool => $this->hasExpectedHeader(
                    $header,
                    $definition['columns'],
                ),
            )
            : null;

        if (! is_array($headerDefinition)) {
            throw new NoteImportException(
                'The note file header is invalid. Supported headers are: '
                .implode(', ', [
                    'matricule,note',
                    ...($allowResultStatuses
                        ? ['matricule,note,statut_resultat']
                        : []),
                    ...($allowLockVersions
                        ? ['matricule,note,lock_version']
                        : []),
                    ...($allowResultStatuses && $allowLockVersions
                        ? ['matricule,note,statut_resultat,lock_version']
                        : []),
                ]).'.',
            );
        }

        $columnCount = count($headerDefinition['columns']);
        $hasStatusColumn = $headerDefinition['with_status'];
        $hasLockVersionColumn = $headerDefinition['with_lock_version'];
        $rows = [];

        foreach ($sheet as $index => $line) {
            $rowNumber = $index + 2;

            if ($this->isEmptyRow($line)) {
                continue;
            }

            if (count($line) !== $columnCount) {
                throw new NoteImportException(
                    "Note row {$rowNumber} must contain exactly {$columnCount} columns.",
                );
            }

            $matricule = trim((string) $line[0]);
            $note = trim((string) $line[1]);
            $status = $hasStatusColumn
                ? NoteResultStatus::tryFrom(
                    Str::upper(trim((string) $line[2])),
                )
                : NoteResultStatus::Saisie;
            $lockVersionIndex = $hasStatusColumn ? 3 : 2;
            $lockVersionValue = $hasLockVersionColumn
                ? trim((string) $line[$lockVersionIndex])
                : '';
            $hasValidLockVersion = ! $hasLockVersionColumn
                || $lockVersionValue === ''
                || (
                    preg_match('/^[1-9]\d{0,9}$/D', $lockVersionValue) === 1
                    && (int) $lockVersionValue <= 4294967294
                );
            $hasValidResult = $status instanceof NoteResultStatus
                && $status->isTeacherEditable()
                && (
                    $status === NoteResultStatus::Saisie
                        ? preg_match(
                            '/^\d{1,6}(?:\.\d{1,2})?$/D',
                            $note,
                        ) === 1
                        : $note === ''
                );

            if ($matricule === ''
                || mb_strlen($matricule) > 80
                || preg_match('/[\x00-\x1F\x7F]/u', $matricule) !== 0
                || ! $hasValidResult
                || ! $hasValidLockVersion) {
                throw new NoteImportException(
                    "Note row {$rowNumber} contains an invalid matricule, note, or lock version.",
                );
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'matricule' => $matricule,
                'note' => $note === '' ? null : $note,
                'status' => $status,
                'lock_version' => $lockVersionValue === ''
                    ? null
                    : (int) $lockVersionValue,
            ];
        }

        if ($rows === []) {
            throw new NoteImportException('The note file contains no note rows.');
        }

        return $rows;
    }

    /**
     * @param  Collection<int, ModeleMaximum>  $columns
     * @return array<int, list<array{
     *     row_number: int,
     *     matricule: string,
     *     note: ?string,
     *     status: NoteResultStatus,
     *     lock_version: ?int
     * }>>
     */
    public function readMatrix(
        UploadedFile $file,
        Collection $columns,
    ): array {
        $entryColumns = $columns
            ->filter(
                fn (ModeleMaximum $column): bool => (bool) $column->periode->saisissable,
            )
            ->values();
        $expectedHeader = [
            'matricule',
            'eleve',
            ...$columns
                ->map(
                    fn (ModeleMaximum $column): string => Str::lower(
                        $this->matrixImportCode((string) $column->periode->code),
                    ),
                )
                ->all(),
            ...$entryColumns
                ->map(
                    fn (ModeleMaximum $column): string => 'version_'.Str::lower(
                        $this->matrixImportCode((string) $column->periode->code),
                    ),
                )
                ->all(),
        ];
        $extension = Str::lower($file->getClientOriginalExtension());
        $readerType = match ($extension) {
            'csv' => Excel::CSV,
            'xlsx' => Excel::XLSX,
            default => throw new NoteImportException(
                'Only CSV and XLSX note files are supported.',
            ),
        };

        $this->assertSafeDimensions($file, $extension, count($expectedHeader));

        try {
            $sheets = $this->excel->toArray(
                new NoteSpreadsheetImport(self::MAX_ROWS + 2),
                $file,
                readerType: $readerType,
            );
        } catch (Throwable) {
            throw new NoteImportException(
                'The CSV or XLSX note file could not be read.',
            );
        }

        $nonEmptySheets = collect($sheets)
            ->filter(
                fn (array $sheet): bool => collect($sheet)
                    ->contains(fn (array $row): bool => ! $this->isEmptyRow($row)),
            )
            ->values();

        if ($nonEmptySheets->count() !== 1) {
            throw new NoteImportException(
                'The note file must contain exactly one non-empty worksheet.',
            );
        }

        /** @var list<array<int, mixed>> $sheet */
        $sheet = $nonEmptySheets->first();

        if (count($sheet) >= self::MAX_ROWS + 2) {
            throw new NoteImportException(
                'The note file exceeds the limit of '.self::MAX_ROWS.' data rows.',
            );
        }

        $header = array_shift($sheet);

        if (! is_array($header)
            || ! $this->hasExpectedHeader($header, $expectedHeader)) {
            throw new NoteImportException(
                'The matrix header is invalid. Download a fresh grade matrix template.',
            );
        }

        $rowsBySlot = [];
        $matricules = [];
        $hasImportableCell = false;
        $displayColumnOffset = 2;
        $versionColumnOffset = $displayColumnOffset + $columns->count();

        foreach ($sheet as $index => $line) {
            $rowNumber = $index + 2;

            if ($this->isEmptyRow($line)) {
                continue;
            }

            if (count($line) !== count($expectedHeader)) {
                throw new NoteImportException(
                    "Note row {$rowNumber} must contain exactly ".count($expectedHeader).' columns.',
                );
            }

            $matricule = trim((string) $line[0]);
            $normalizedMatricule = Str::lower($matricule);

            if ($matricule === ''
                || mb_strlen($matricule) > 80
                || preg_match('/[\x00-\x1F\x7F]/u', $matricule) !== 0
                || in_array($normalizedMatricule, $matricules, true)) {
                throw new NoteImportException(
                    "Note row {$rowNumber} contains an invalid or duplicate matricule.",
                );
            }

            $matricules[] = $normalizedMatricule;
            $entryColumnIndex = 0;

            foreach ($columns->values() as $columnIndex => $column) {
                $cell = trim((string) $line[$displayColumnOffset + $columnIndex]);

                if (! $column->periode->saisissable) {
                    if (mb_strlen($cell) > 40
                        || preg_match('/[\x00-\x1F\x7F]/u', $cell) !== 0) {
                        throw new NoteImportException(
                            "Note row {$rowNumber} contains an invalid calculated total.",
                        );
                    }

                    continue;
                }

                $versionValue = trim(
                    (string) $line[$versionColumnOffset + $entryColumnIndex],
                );
                $entryColumnIndex++;

                if ($cell === '') {
                    continue;
                }

                if ((bool) $column->verrouille) {
                    throw new NoteImportException(
                        "Note row {$rowNumber} targets a locked period.",
                    );
                }

                $parsed = $this->parseMatrixCell($cell, $rowNumber);
                $hasValidVersion = $versionValue === ''
                    || (
                        preg_match('/^[1-9]\d{0,9}$/D', $versionValue) === 1
                        && (int) $versionValue <= 4294967294
                    );

                if (! $hasValidVersion) {
                    throw new NoteImportException(
                        "Note row {$rowNumber} contains an invalid lock version.",
                    );
                }

                $rowsBySlot[$column->getKey()][] = [
                    'row_number' => $rowNumber,
                    'matricule' => $matricule,
                    'note' => $parsed['note'],
                    'status' => $parsed['status'],
                    'lock_version' => $versionValue === '' ? null : (int) $versionValue,
                ];
                $hasImportableCell = true;
            }
        }

        if (! $hasImportableCell) {
            throw new NoteImportException(
                'The note matrix contains no editable note cells.',
            );
        }

        return $rowsBySlot;
    }

    /**
     * @return array{note: ?string, status: NoteResultStatus}
     */
    private function parseMatrixCell(string $cell, int $rowNumber): array
    {
        $normalized = Str::upper($cell);

        if (in_array($normalized, ['ABS', 'ABSENTE'], true)) {
            return ['note' => null, 'status' => NoteResultStatus::Absente];
        }

        if (in_array($normalized, ['DISP', 'DISPENSEE'], true)) {
            return ['note' => null, 'status' => NoteResultStatus::Dispensee];
        }

        $note = str_replace(',', '.', $cell);

        if (preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D', $note) !== 1) {
            throw new NoteImportException(
                "Note row {$rowNumber} contains an invalid note. Use a number, ABS, or DISP.",
            );
        }

        return ['note' => $note, 'status' => NoteResultStatus::Saisie];
    }

    private function matrixImportCode(string $code): string
    {
        if (preg_match('/^EXAM_S(\d+)$/i', $code, $matches) === 1) {
            return 'EXAM'.$matches[1];
        }

        if (preg_match('/^TOTAL_S(\d+)$/i', $code, $matches) === 1) {
            return 'TOT'.$matches[1];
        }

        return Str::upper($code) === 'TOTAL_GENERAL' ? 'TOTG' : $code;
    }

    /**
     * @param  array<int, mixed>  $header
     * @param  list<string>  $expectedHeader
     */
    private function hasExpectedHeader(
        array $header,
        array $expectedHeader,
    ): bool {
        if (count($header) !== count($expectedHeader)) {
            return false;
        }

        $firstColumn = ltrim((string) $header[0], "\xEF\xBB\xBF");
        $normalizedHeader = collect($header)
            ->map(
                fn (mixed $column): string => Str::lower(
                    trim((string) $column),
                ),
            )
            ->all();
        $normalizedHeader[0] = Str::lower(trim($firstColumn));

        return $normalizedHeader === $expectedHeader;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)
            ->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    private function assertSafeDimensions(
        UploadedFile $file,
        string $extension,
        int $maximumColumn,
    ): void {
        if ($extension === 'csv') {
            $this->assertSafeCsvDimensions($file);

            return;
        }

        $this->assertSafeXlsxArchive($file, $maximumColumn);
    }

    private function assertSafeCsvDimensions(UploadedFile $file): void
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new NoteImportException(
                'The CSV or XLSX note file could not be read.',
            );
        }

        try {
            $physicalRow = 0;

            while (($row = fgetcsv($handle, escape: '')) !== false) {
                $physicalRow++;

                if ($physicalRow > self::MAX_ROWS + 1
                    && ! $this->isEmptyRow($row)) {
                    throw new NoteImportException(
                        'The note file exceeds the supported worksheet dimensions.',
                    );
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function assertSafeXlsxArchive(
        UploadedFile $file,
        int $maximumColumn,
    ): void {
        $archive = new ZipArchive;

        if ($archive->open($file->getRealPath()) !== true) {
            throw new NoteImportException(
                'The CSV or XLSX note file could not be read.',
            );
        }

        try {
            if ($archive->numFiles > 512) {
                throw new NoteImportException(
                    'The XLSX note file exceeds the supported archive limits.',
                );
            }

            $totalUncompressedSize = 0;

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $statistics = $archive->statIndex($index);

                if (! is_array($statistics)) {
                    throw new NoteImportException(
                        'The CSV or XLSX note file could not be read.',
                    );
                }

                $uncompressedSize = (int) ($statistics['size'] ?? 0);
                $compressedSize = (int) ($statistics['comp_size'] ?? 0);
                $entryName = (string) ($statistics['name'] ?? '');
                $totalUncompressedSize += $uncompressedSize;

                if ($uncompressedSize > 16 * 1024 * 1024
                    || $totalUncompressedSize > 32 * 1024 * 1024
                    || ($compressedSize > 0
                        && $uncompressedSize / $compressedSize > 200)) {
                    throw new NoteImportException(
                        'The XLSX note file exceeds the supported archive limits.',
                    );
                }

                if (! preg_match(
                    '#^xl/worksheets/[^/]+\.xml$#i',
                    $entryName,
                )) {
                    continue;
                }

                $worksheet = $archive->getFromIndex($index);

                if (! is_string($worksheet)
                    || preg_match('/<f(?:\s|\/?>)/i', $worksheet) === 1
                    || preg_match(
                        '/<c\b[^>]*\bt="(?:b|e)"/i',
                        $worksheet,
                    ) === 1) {
                    throw new NoteImportException(
                        'The XLSX note file contains unsupported cell content.',
                    );
                }

                $this->assertWorksheetCoordinates(
                    $worksheet,
                    $maximumColumn,
                );
            }
        } finally {
            $archive->close();
        }
    }

    private function assertWorksheetCoordinates(
        string $worksheet,
        int $maximumColumn,
    ): void {
        preg_match_all(
            '/<row\b[^>]*\br="(\d+)"/i',
            $worksheet,
            $rowMatches,
        );
        $maximumRow = collect($rowMatches[1] ?? [])
            ->map(fn (string $row): int => (int) $row)
            ->max() ?? 0;

        if ($maximumRow > self::MAX_ROWS + 1) {
            throw new NoteImportException(
                'The note file exceeds the supported worksheet dimensions.',
            );
        }

        preg_match_all(
            '/<c\b[^>]*\br="([A-Z]+)\d+"/i',
            $worksheet,
            $columnMatches,
        );
        $hasExtraColumn = collect($columnMatches[1] ?? [])
            ->contains(
                fn (string $column): bool => $this->columnNumber($column)
                    > $maximumColumn,
            );

        if ($hasExtraColumn) {
            throw new NoteImportException(
                'The note file exceeds the supported worksheet dimensions.',
            );
        }
    }

    private function columnNumber(string $column): int
    {
        $number = 0;

        foreach (str_split(Str::upper($column)) as $character) {
            $number = ($number * 26) + ord($character) - 64;
        }

        return $number;
    }
}
