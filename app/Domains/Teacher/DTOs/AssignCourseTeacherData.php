<?php

namespace App\Domains\Teacher\DTOs;

final readonly class AssignCourseTeacherData
{
    public function __construct(
        public int $teacherId,
        public ?string $startDate,
        public ?string $endDate,
    ) {}
}
