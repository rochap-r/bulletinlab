<?php

namespace App\Domains\Evaluation\Imports;

use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithLimit;

final class NoteSpreadsheetImport implements WithCustomCsvSettings, WithLimit
{
    public function __construct(
        private readonly int $rowLimit,
    ) {}

    public function limit(): int
    {
        return $this->rowLimit;
    }

    /**
     * @return array{delimiter: string, input_encoding: string}
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'input_encoding' => 'UTF-8',
        ];
    }
}
