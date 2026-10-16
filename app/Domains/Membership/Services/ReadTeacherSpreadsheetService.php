<?php

namespace App\Domains\Membership\Services;

use App\Domains\Membership\Exceptions\TeacherImportException;
use App\Domains\Membership\Imports\TeacherSpreadsheetImport;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Throwable;

final class ReadTeacherSpreadsheetService
{
    private const MAX_ROWS = 500;

    private const HEADERS = [
        'nom',
        'postnom',
        'prenom',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'email',
        'telephone',
        'adresse',
        'matricule',
        'qualification',
        'specialite',
        'date_embauche',
    ];

    public function __construct(
        private Excel $excel,
    ) {}

    /**
     * Lit la première feuille d'un classeur Excel et retourne les lignes
     * de données avec leur numéro de ligne Excel (l'en-tête est la ligne 1).
     *
     * @return list<array{ligne: int, values: array<string, string>}>
     */
    public function read(UploadedFile $file): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $readerType = match ($extension) {
            'xlsx' => Excel::XLSX,
            'xls' => Excel::XLS,
            default => throw new TeacherImportException(
                'Seuls les fichiers Excel (.xlsx, .xls) sont acceptés.',
            ),
        };

        try {
            $sheets = $this->excel->toArray(
                new TeacherSpreadsheetImport(self::MAX_ROWS + 2),
                $file,
                readerType: $readerType,
            );
        } catch (Throwable $throwable) {
            if ($throwable instanceof TeacherImportException) {
                throw $throwable;
            }

            throw new TeacherImportException(
                'Le fichier Excel n’a pas pu être lu.',
            );
        }

        $nonEmptySheets = collect($sheets)
            ->filter(
                fn (array $sheet): bool => collect($sheet)
                    ->contains(fn (array $row): bool => ! $this->isEmptyRow($row)),
            )
            ->values();

        if ($nonEmptySheets->count() !== 1) {
            throw new TeacherImportException(
                'Le fichier Excel doit contenir exactement une feuille non vide.',
            );
        }

        /** @var list<array<int, mixed>> $sheet */
        $sheet = $nonEmptySheets->first();

        if (count($sheet) > self::MAX_ROWS + 1) {
            throw new TeacherImportException(
                'Le fichier Excel dépasse la limite de '.self::MAX_ROWS.' lignes de données.',
            );
        }

        $header = array_shift($sheet);
        $columns = $this->resolveColumns($header);

        $rows = [];

        foreach ($sheet as $index => $line) {
            // Ligne Excel physique : l'en-tête est la ligne 1.
            $ligne = $index + 2;

            if ($this->isEmptyRow($line)) {
                continue;
            }

            $values = [];

            foreach ($columns as $position => $name) {
                $values[$name] = $this->cellToString($line[$position] ?? null);
            }

            $rows[] = ['ligne' => $ligne, 'values' => $values];
        }

        if ($rows === []) {
            throw new TeacherImportException(
                'Le fichier Excel ne contient aucune ligne d’enseignant.',
            );
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $header
     * @return array<int, string>
     */
    private function resolveColumns(array $header): array
    {
        // Colonne `role` historique : ignorée, l'import ne crée que des enseignants.
        $columns = [];

        foreach ($header as $position => $raw) {
            $text = trim((string) $raw);

            if ($position === 0) {
                $text = ltrim($text, "\xEF\xBB\xBF");
            }

            $name = Str::lower($text);

            if ($name === '' || $name === 'role') {
                continue;
            }

            if (! in_array($name, self::HEADERS, true)) {
                throw new TeacherImportException(
                    "Colonne inconnue « {$text} ». Téléchargez le modèle Excel pour voir les colonnes attendues.",
                );
            }

            $columns[$position] = $name;
        }

        foreach (['nom', 'telephone'] as $required) {
            if (! in_array($required, $columns, true)) {
                throw new TeacherImportException(
                    'En-tête invalide : les colonnes « nom » et « telephone » sont obligatoires.',
                );
            }
        }

        return $columns;
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return trim((string) $value);
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)
            ->every(fn (mixed $value): bool => trim((string) (
                $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value
            )) === '');
    }
}
