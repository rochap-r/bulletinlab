<?php

namespace App\Domains\Responsable\DTOs;

use App\Models\Responsable;

final readonly class ResponsableAuthenticatedSession
{
    public function __construct(
        public Responsable $responsable,
        public string $accessToken,
    ) {}
}
