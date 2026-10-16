<?php

namespace App\Domains\Membership\Imports;

use Maatwebsite\Excel\Concerns\WithLimit;

final class TeacherSpreadsheetImport implements WithLimit
{
    public function __construct(
        private readonly int $rowLimit,
    ) {}

    public function limit(): int
    {
        return $this->rowLimit;
    }
}
