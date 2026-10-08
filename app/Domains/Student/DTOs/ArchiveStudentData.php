<?php

namespace App\Domains\Student\DTOs;

final readonly class ArchiveStudentData
{
    public function __construct(
        public string $reason,
    ) {}
}
