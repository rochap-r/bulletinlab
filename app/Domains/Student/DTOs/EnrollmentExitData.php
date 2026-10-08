<?php

namespace App\Domains\Student\DTOs;

final readonly class EnrollmentExitData
{
    public function __construct(
        public string $status,
        public string $exitDate,
        public string $reason,
    ) {}
}
