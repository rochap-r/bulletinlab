<?php

namespace App\Domains\SchoolYear\DTOs;

final readonly class ArchiveClassData
{
    public function __construct(
        public string $reason,
    ) {}
}
