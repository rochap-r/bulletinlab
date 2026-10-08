<?php

namespace App\Domains\Auth\DTOs;

use App\Models\UtilisateurPlateforme;

final readonly class PlatformAuthenticatedSession
{
    public function __construct(
        public UtilisateurPlateforme $utilisateur,
        public string $accessToken,
    ) {}
}
