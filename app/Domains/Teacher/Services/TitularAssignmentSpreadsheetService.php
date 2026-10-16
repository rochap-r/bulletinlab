<?php

namespace App\Domains\Teacher\Services;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etablissement;
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

final class TitularAssignmentSpreadsheetService
{
    private const HEADERS = ['Classe', 'Titulaire', 'Référence classe', 'Référence école'];

    /** @return Collection<int, Classe> */
    private function classes(Etablissement $school, int $yearId): Collection
    {
        AnneeScolaire::query()->whereKey($yearId)->where('active', true)->where('cloturee', false)->firstOrFail();

        return Classe::query()->where('tenant_id', $school->tenant_id)->where('etablissement_id', $school->id)
            ->where('annee_scolaire_id', $yearId)->where('active', true)->with('titulaire')->orderBy('nom')->get();
    }

    /** @return Collection<int, Utilisateur> */
    private function teachers(Etablissement $school): Collection
    {
        return Utilisateur::query()->where('tenant_id', $school->tenant_id)->where('etablissement_id', $school->id)->where('actif', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['ENSEIGNANT', 'TITULAIRE'])->validForTenant($school->tenant_id))
            ->orderBy('nom')->get();
    }

    private function label(Utilisateur $teacher): string
    {
        return $teacher->nom_complet.' · '.$teacher->telephone;
    }

    public function template(Etablissement $school, int $yearId): Spreadsheet
    {
        $classes = $this->classes($school, $yearId);
        if ($classes->count() > 200) {
            throw ValidationException::withMessages(['fichier' => ['Le modèle est limité à 200 classes.']]);
        }
        $teachers = $this->teachers($school);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Titulariats');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $roster = $book->createSheet()->setTitle('Enseignants');
        $roster->fromArray(['Enseignant à choisir', 'Spécialité'], null, 'A1');
        foreach ($teachers->values() as $index => $teacher) {
            $roster->setCellValueExplicit('A'.($index + 2), $this->label($teacher), DataType::TYPE_STRING);
            $roster->setCellValueExplicit('B'.($index + 2), $teacher->specialite ?? '', DataType::TYPE_STRING);
        }
        $book->addNamedRange(new NamedRange('ListeEnseignants', $roster, '$A$2:$A$'.max(2, $teachers->count() + 1)));
        foreach ($classes->values() as $index => $class) {
            $row = $index + 2;
            $teacher = $class->titulaire_id ? $teachers->firstWhere('id', $class->titulaire_id) : null;
            foreach (['A' => $class->nom, 'B' => $teacher ? $this->label($teacher) : '', 'C' => $class->public_id, 'D' => $school->public_id] as $column => $value) {
                $sheet->setCellValueExplicit($column.$row, $value, DataType::TYPE_STRING);
            }
            $sheet->getCell('B'.$row)->getDataValidation()->setType(DataValidation::TYPE_LIST)->setFormula1('ListeEnseignants')
                ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                ->setErrorTitle('Choisissez un enseignant')->setError('Choisissez un enseignant dans la liste.');
        }
        foreach ([$sheet, $roster] as $tab) {
            $tab->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $tab->getStyle('A1:B1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF243D54');
            $tab->getRowDimension(1)->setRowHeight(28);
            $tab->getColumnDimension('A')->setWidth(52);
            $tab->getColumnDimension('B')->setWidth(58);
            $tab->freezePane('A2');
        }
        $sheet->getColumnDimension('C')->setVisible(false);
        $sheet->getColumnDimension('D')->setVisible(false);
        if ($classes->isNotEmpty()) {
            $lastRow = $classes->count() + 1;
            $sheet->getStyle('B2:B'.$lastRow)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF3FF');
            $sheet->setAutoFilter('A1:B'.$lastRow);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** @return array{lignes: list<array{ligne: int, classe_id: int, classe: string, titulaire_id: int, titulaire: string, remplace: ?string}>, erreurs: list<array{ligne: int, message: string}>} */
    public function preview(Etablissement $school, int $yearId, UploadedFile $file): array
    {
        $classes = $this->classes($school, $yearId)->keyBy('public_id');
        $teachers = $this->teachers($school);
        $reader = new Xlsx;
        try {
            $info = $reader->listWorksheetInfo($file->getRealPath());
            if (! isset($info[0]) || $info[0]['totalRows'] > 201 || count($info) > 3) {
                throw ValidationException::withMessages(['fichier' => ['Utilisez le modèle fourni avec au maximum 200 classes.']]);
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
            $rows = $book->getSheet(0)->rangeToArray('A1:D'.max(1, $info[0]['totalRows']), null, false, false);
            if (array_shift($rows) !== self::HEADERS) {
                throw ValidationException::withMessages(['fichier' => ['Les colonnes ont changé. Utilisez le modèle Excel des titulariats.']]);
            }
            $lines = [];
            $errors = [];
            $seen = [];
            foreach ($rows as $index => $row) {
                $label = trim((string) ($row[1] ?? ''));
                if ($label === '') {
                    continue;
                }
                $class = $classes->get((string) ($row[2] ?? ''));
                $matches = $teachers->filter(fn (Utilisateur $teacher): bool => in_array(Str::lower(Str::ascii($label)),
                    array_map(fn (string $value): string => Str::lower(Str::ascii($value)), [$this->label($teacher), $teacher->nom_complet, $teacher->telephone, $teacher->matricule ?? '']), true));
                $message = null;
                if (! $class || (string) ($row[3] ?? '') !== $school->public_id || (string) ($row[0] ?? '') !== $class->nom) {
                    $message = 'Cette classe ne correspond pas à l’école et à l’année sélectionnées.';
                } elseif (isset($seen[$class->id])) {
                    $message = 'Cette classe figure plusieurs fois dans le fichier.';
                } elseif ($matches->count() !== 1) {
                    $message = 'Enseignant introuvable ou nom ambigu. Choisissez un nom dans la liste du modèle.';
                }
                if ($message !== null) {
                    $errors[] = ['ligne' => $index + 2, 'message' => $message];

                    continue;
                }
                $seen[$class->id] = true;
                $teacher = $matches->first();
                if ($class->titulaire_id === $teacher->id) {
                    continue;
                }
                $lines[] = ['ligne' => $index + 2, 'classe_id' => $class->id, 'classe' => $class->nom,
                    'titulaire_id' => $teacher->id, 'titulaire' => $teacher->nom_complet, 'remplace' => $class->titulaire?->nom_complet];
            }

            return ['lignes' => $lines, 'erreurs' => $errors];
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
