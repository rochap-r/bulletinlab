<?php

namespace App\Domains\Student\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class EnrollmentExited implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $establishmentId,
        public readonly int $schoolYearId,
        public readonly int $classId,
        public readonly int $studentId,
        public readonly int $enrollmentId,
        public readonly int $actorId,
        public readonly string $status,
        public readonly string $exitDate,
    ) {}
}
