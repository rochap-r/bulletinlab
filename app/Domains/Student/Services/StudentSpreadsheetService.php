<?php

namespace App\Domains\Student\Services;

use App\Http\Requests\Api\V1\Student\MyClassStudentRequest;
use App\Models\Classe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;
use ZipArchive;

final class StudentSpreadsheetService
{
    private const COLUMNS = ['nom' => 'Nom', 'postnom' => 'Post-nom', 'prenom' => 'Prénom', 'sexe' => 'Sexe (M/F)',
        'date_naissance' => 'Date de naissance (AAAA-MM-JJ)', 'lieu_naissance' => 'Lieu de naissance',
        'num_permanent' => 'Numéro permanent', 'nom_pere' => 'Nom du père', 'nom_mere' => 'Nom de la mère',
        'telephone_parent' => 'Téléphone du parent', 'adresse' => 'Adresse'];

    public function __construct(private ManageMyClassStudentsService $students) {}

    public function template(Classe $class): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Élèves');
        $sheet->fromArray(array_values(self::COLUMNS));
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'E' ? 36 : 24);
        }
        $sheet->getStyle('A2:K201')->getNumberFormat()->setFormatCode('@');
        $context = $book->createSheet()->setTitle('Classe');
        foreach ([$class->public_id, (string) $class->annee_scolaire_id, $class->nom] as $index => $value) {
            $context->setCellValueExplicit('A'.($index + 1), $value, DataType::TYPE_STRING);
        }
        $context->setSheetState('hidden');
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** @return array{lignes: list<array<string, mixed>>, erreurs: list<array{ligne: int, message: string}>} */
    public function preview(Classe $class, UploadedFile $file): array
    {
        $this->students->assertOpen($class);
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        try {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                throw ValidationException::withMessages(['fichier' => 'Fichier Excel invalide.']);
            }
            try {
                $size = 0;
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $size += $zip->statIndex($index)['size'];
                }
                if ($zip->numFiles > 1000 || $size > 25 * 1024 * 1024) {
                    throw ValidationException::withMessages(['fichier' => 'Ce fichier est trop volumineux après décompression. Utilisez le modèle fourni.']);
                }
            } finally {
                $zip->close();
            }
            $names = $reader->listWorksheetNames($file->getRealPath());
            $studentSheets = array_values(array_filter($names, fn (string $name): bool => $name !== 'Classe'));
            if (count($studentSheets) !== 1) {
                throw ValidationException::withMessages(['fichier' => 'Le fichier doit contenir une seule feuille d’élèves, avec les onze colonnes du modèle. La feuille technique « Classe » est facultative.']);
            }
            $reader->setReadFilter(new class implements IReadFilter
            {
                private int $visitedCells = 0;

                public function readCell(mixed $columnAddress, mixed $row, mixed $worksheetName = ''): bool
                {
                    if (++$this->visitedCells > 50000) {
                        throw ValidationException::withMessages(['fichier' => 'Le fichier contient trop de cellules enregistrées. Copiez les lignes renseignées dans un nouveau modèle Excel.']);
                    }

                    return true;
                }
            });
            $book = $reader->load($file->getRealPath());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['fichier' => 'Fichier Excel illisible. Téléchargez le modèle et réessayez.']);
        }
        try {
            $context = $book->getSheetByName('Classe');
            if ($context !== null && ($context->getCell('A1')->getValue() !== $class->public_id
                || (string) $context->getCell('A2')->getValue() !== (string) $class->annee_scolaire_id)) {
                throw ValidationException::withMessages(['fichier' => 'Ce fichier appartient à une autre classe. Téléchargez le bon modèle.']);
            }
            $sheet = $book->getSheetByName($studentSheets[0]);
            $normalizeHeader = fn (mixed $value): string => Str::lower(trim(preg_replace('/[\s\p{Z}]+/u', ' ', (string) $value)));
            if (array_map($normalizeHeader, $sheet->rangeToArray('A1:K1', null, false, false)[0]) !== array_map($normalizeHeader, array_values(self::COLUMNS))) {
                throw ValidationException::withMessages(['fichier' => 'Les onze colonnes doivent reprendre les titres et l’ordre du modèle Excel. Téléchargez le modèle et copiez vos élèves sous ses titres.']);
            }
            $studentRows = [];
            foreach ($sheet->getCoordinates() as $coordinate) {
                $value = $sheet->getCell($coordinate)->getValue();
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    continue;
                }
                [$column, $rowNumber] = Coordinate::coordinateFromString($coordinate);
                if (Coordinate::columnIndexFromString($column) > count(self::COLUMNS)) {
                    throw ValidationException::withMessages(['fichier' => "La cellule $coordinate contient des données en dehors des onze colonnes prévues. Supprimez ces données ou recopiez les élèves dans le modèle."]);
                }
                if ((int) $rowNumber > 1) {
                    $studentRows[(int) $rowNumber] = true;
                }
                if (count($studentRows) > 200) {
                    throw ValidationException::withMessages(['fichier' => 'Le fichier contient plus de 200 lignes d’élèves renseignées. Répartissez les élèves dans plusieurs fichiers.']);
                }
            }
            ksort($studentRows);
            $lines = $errors = $seen = $numbers = [];
            foreach (array_keys($studentRows) as $line) {
                $values = $sheet->rangeToArray("A$line:K$line", null, false, false)[0];
                if (collect($values)->every(fn ($value): bool => $value === null || trim((string) $value) === '')) {
                    continue;
                }
                $row = [];
                $formula = false;
                foreach (array_keys(self::COLUMNS) as $index => $field) {
                    $value = $values[$index];
                    $formula = $formula || $sheet->getCell([1 + $index, $line])->getDataType() === DataType::TYPE_FORMULA;
                    if ($field === 'date_naissance' && is_numeric($value) && (float) $value >= 0 && (float) $value < 3000000) {
                        $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                    }
                    $row[$field] = $value === null || trim((string) $value) === '' ? null : trim((string) $value);
                }
                $validation = Validator::make($row, MyClassStudentRequest::studentRules(), MyClassStudentRequest::validationMessages(), self::COLUMNS);
                $identity = mb_strtolower(json_encode(array_intersect_key($row, array_flip(['nom', 'postnom', 'prenom', 'date_naissance'])), JSON_UNESCAPED_UNICODE));
                $number = $row['num_permanent'];
                $message = $formula ? 'Les formules Excel ne sont pas acceptées. Remplacez-les par des valeurs.'
                    : ($validation->fails() ? implode(' ', $validation->errors()->all()) : null);
                if ($message === null && (isset($seen[$identity]) || ($number !== null && isset($numbers[mb_strtolower($number)])) || $this->students->duplicate($class, $row))) {
                    $message = 'Doublon : cette identité ou ce numéro figure déjà dans le fichier ou dans l’école. Vérifiez auprès de la direction.';
                }
                $seen[$identity] = true;
                if ($number !== null) {
                    $numbers[mb_strtolower($number)] = true;
                }
                if ($message !== null) {
                    $errors[] = ['ligne' => $line, 'message' => $message];
                } else {
                    $lines[] = ['ligne' => $line, 'eleve' => $row];
                }
            }
            if ($lines === [] && $errors === []) {
                $errors[] = ['ligne' => 2, 'message' => 'Le fichier ne contient aucun élève. Remplissez au moins une ligne.'];
            }

            return ['lignes' => $lines, 'erreurs' => $errors];
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
