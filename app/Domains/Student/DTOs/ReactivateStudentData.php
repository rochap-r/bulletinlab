<?php

namespace App\Domains\Student\DTOs;

final readonly class ReactivateStudentData
{
    public function __construct(
        public string $reason,
    ) {}
}
