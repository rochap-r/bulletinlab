<?php

namespace App\Domains\Student\DTOs;

final readonly class EnrollmentData
{
    public function __construct(
        public int $studentId,
        public ?string $registrationNumber,
        public string $enrollmentDate,
    ) {}
}
