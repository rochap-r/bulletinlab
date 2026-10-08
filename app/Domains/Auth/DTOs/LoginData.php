<?php

namespace App\Domains\Auth\DTOs;

final readonly class LoginData
{
    public function __construct(
        public string $tenantSlug,
        public string $identifiant,
        public string $password,
    ) {}
}
