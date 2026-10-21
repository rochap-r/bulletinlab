<?php

namespace App\Domains\Teacher\Services;

use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

final class CourseAssignmentSpreadsheetService
{
    private const HEADERS = ['Cours', 'Enseignant', 'Référence cours', 'Référence classe'];

    public function __construct(private ListClassCoursesService $courses) {}

    /** @return Collection<int, Utilisateur> */
    private function teachers(Classe $class): Collection
    {
        return Utilisateur::query()->where('tenant_id', $class->tenant_id)
            ->where('etablissement_id', $class->etablissement_id)->where('actif', true)
            ->eligibleForCourseTeaching($class->tenant_id)
            ->orderBy('nom')->get();
    }

    private function label(Utilisateur $teacher): string
    {
        return $teacher->nom_complet.' · '.$teacher->telephone;
    }

    public function template(Classe $class): Spreadsheet
    {
        $courses = $this->courses->paginate($class, 200);
        if ($courses->total() > 200) {
            throw ValidationException::withMessages(['fichier' => ['Le modèle est limité à 200 cours par classe.']]);
        }
        $teachers = $this->teachers($class);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Attributions');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $roster = $book->createSheet()->setTitle('Enseignants');
        $roster->fromArray(['Enseignant à choisir', 'Spécialité'], null, 'A1');
        foreach ($teachers->values() as $index => $teacher) {
            $roster->setCellValueExplicit('A'.($index + 2), $this->label($teacher), DataType::TYPE_STRING);
            $roster->setCellValueExplicit('B'.($index + 2), $teacher->specialite ?? '', DataType::TYPE_STRING);
        }
        $book->addNamedRange(new NamedRange('ListeEnseignants', $roster, '$A$2:$A$'.max(2, $teachers->count() + 1)));
        $row = 2;
        foreach ($courses->getCollection()->where('attribuable', true) as $course) {
            $assignment = $course->attributionsCours->first();
            $teacher = $assignment ? $teachers->firstWhere('id', $assignment->enseignant_id) : null;
            $sheet->setCellValueExplicit('A'.$row, $course->libelle, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $teacher ? $this->label($teacher) : '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, (string) $course->id, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$row, $class->public_id, DataType::TYPE_STRING);
            $sheet->getCell('B'.$row)->getDataValidation()->setType(DataValidation::TYPE_LIST)
                ->setFormula1('ListeEnseignants')->setAllowBlank(true)->setShowDropDown(true)
                ->setShowErrorMessage(true)->setErrorTitle('Choisissez un enseignant')
                ->setError('Choisissez un enseignant dans la liste.')->setErrorStyle(DataValidation::STYLE_STOP);
            $row++;
        }
        $sheet->getColumnDimension('A')->setWidth(52);
        $sheet->getColumnDimension('B')->setWidth(58);
        $sheet->getColumnDimension('C')->setVisible(false);
        $sheet->getColumnDimension('D')->setVisible(false);
        $roster->getColumnDimension('A')->setWidth(58);
        $roster->getColumnDimension('B')->setWidth(38);
        foreach ([$sheet, $roster] as $tab) {
            $tab->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $tab->getStyle('A1:B1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF243D54');
            $tab->getRowDimension(1)->setRowHeight(28);
            $tab->freezePane('A2');
        }
        if ($row > 2) {
            $sheet->getStyle('B2:B'.($row - 1))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF3FF');
            $sheet->setAutoFilter('A1:B'.($row - 1));
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * @return array{lignes: list<array{ligne: int, node_id: int, cours: string, enseignant_id: int, enseignant: string, remplace: ?string}>, erreurs: list<array{ligne: int, message: string}>}
     */
    public function preview(Classe $class, UploadedFile $file): array
    {
        $reader = new Xlsx;
        try {
            $info = $reader->listWorksheetInfo($file->getRealPath());
            if (! isset($info[0]) || $info[0]['totalRows'] > 201 || count($info) > 3) {
                throw ValidationException::withMessages(['fichier' => ['Utilisez le modèle fourni, avec au maximum 200 cours.']]);
            }
            $reader->setReadDataOnly(true)->setLoadSheetsOnly([$info[0]['worksheetName']]);
            $reader->setReadFilter(new class implements IReadFilter
            {
                public function readCell(mixed $columnAddress, mixed $row, mixed $worksheetName = ''): bool
                {
                    return $row <= 201 && in_array($columnAddress, ['A', 'B', 'C', 'D'], true);
                }
            });
            $book = $reader->load($file->getRealPath());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['fichier' => ['Ce fichier Excel ne peut pas être lu. Téléchargez à nouveau le modèle.']]);
        }
        try {
            $sheet = $book->getSheet(0);
            $values = $sheet->rangeToArray('A1:D'.max(1, $info[0]['totalRows']), null, false, false);
            if (array_shift($values) !== self::HEADERS) {
                throw ValidationException::withMessages(['fichier' => ['Les colonnes ont changé. Utilisez le modèle Excel fourni.']]);
            }
            $courses = $this->courses->paginate($class, 200)->getCollection()->keyBy('id');
            $teachers = $this->teachers($class);
            $lines = [];
            $errors = [];
            $seen = [];
            foreach ($values as $index => $row) {
                $line = $index + 2;
                $label = trim((string) ($row[1] ?? ''));
                if ($label === '') {
                    continue;
                }
                $course = $courses->get((int) ($row[2] ?? 0));
                $matches = $teachers->filter(fn (Utilisateur $teacher): bool => in_array(
                    Str::lower(Str::ascii($label)),
                    array_map(fn (string $value): string => Str::lower(Str::ascii($value)), [$this->label($teacher), $teacher->nom_complet, $teacher->telephone, $teacher->matricule ?? '']), true,
                ));
                $message = null;
                if (! $course || (string) ($row[3] ?? '') !== $class->public_id || (string) ($row[0] ?? '') !== $course->libelle || ! $course->attribuable) {
                    $message = 'Ce cours ne correspond pas à la classe sélectionnée. Utilisez son modèle Excel.';
                } elseif (isset($seen[$course->id])) {
                    $message = 'Ce cours figure plusieurs fois dans le fichier.';
                } elseif ($matches->count() !== 1) {
                    $message = 'Enseignant introuvable ou nom ambigu. Choisissez un nom dans la liste du modèle.';
                }
                if ($message !== null) {
                    $errors[] = ['ligne' => $line, 'message' => $message];

                    continue;
                }
                $seen[$course->id] = true;
                $teacher = $matches->first();
                $previous = $course->attributionsCours->first();
                if ($previous?->enseignant_id === $teacher->id
                    && $teacher->hasTenantRole('ENSEIGNANT', $class->tenant_id)) {
                    continue;
                }
                $lines[] = ['ligne' => $line, 'node_id' => $course->id, 'cours' => $course->libelle,
                    'enseignant_id' => $teacher->id, 'enseignant' => $teacher->nom_complet,
                    'remplace' => $previous?->enseignant->nom_complet];
            }

            return ['lignes' => $lines, 'erreurs' => $errors];
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
