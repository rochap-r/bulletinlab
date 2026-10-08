<?php

namespace App\Domains\SchoolYear\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ClassArchived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $establishmentId,
        public readonly int $schoolYearId,
        public readonly int $classId,
        public readonly int $actorId,
    ) {}
}
