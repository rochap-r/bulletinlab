<?php

namespace App\Domains\Teacher\DTOs;

final readonly class ImportedCourseAssignmentNotes
{
    public function __construct(
        public int $total,
        public int $created,
        public int $corrected,
        public int $unchanged,
    ) {}

    /**
     * @return array{
     *     total: int,
     *     created: int,
     *     corrected: int,
     *     unchanged: int
     * }
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'created' => $this->created,
            'corrected' => $this->corrected,
            'unchanged' => $this->unchanged,
        ];
    }
}
