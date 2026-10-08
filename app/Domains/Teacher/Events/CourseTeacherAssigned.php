<?php

namespace App\Domains\Teacher\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class CourseTeacherAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $establishmentId,
        public readonly int $schoolYearId,
        public readonly int $classId,
        public readonly int $courseNodeId,
        public readonly int $teacherId,
        public readonly ?int $previousTeacherId,
        public readonly int $actorId,
    ) {}
}
