<?php

namespace App\Domains\Student\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class StudentReactivated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $establishmentId,
        public readonly int $studentId,
        public readonly int $actorId,
    ) {}
}
