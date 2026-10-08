<?php

namespace App\Domains\Teacher\DTOs;

final readonly class AssignClassTitularData
{
    public function __construct(
        public int $titularId,
        public ?string $startDate,
        public ?string $endDate,
    ) {}
}
