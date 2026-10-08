<?php

namespace App\Domains\Auth\DTOs;

use App\Models\Utilisateur;

final readonly class AuthenticatedSession
{
    public function __construct(
        public Utilisateur $utilisateur,
        public string $accessToken,
    ) {}
}
