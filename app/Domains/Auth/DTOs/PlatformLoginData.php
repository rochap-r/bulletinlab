<?php

namespace App\Domains\Auth\DTOs;

final readonly class PlatformLoginData
{
    public function __construct(
        public string $identifiant,
        public string $password,
    ) {}
}
