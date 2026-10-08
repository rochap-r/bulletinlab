<?php

namespace App\Domains\Teacher\DTOs;

use App\Models\Note;

final readonly class UpsertedCourseAssignmentNote
{
    public function __construct(
        public Note $note,
        public int $entrySlotId,
    ) {}
}
