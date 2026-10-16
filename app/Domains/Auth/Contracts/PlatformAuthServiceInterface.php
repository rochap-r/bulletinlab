<?php

namespace App\Domains\Auth\Contracts;

use App\Domains\Auth\DTOs\PlatformAuthenticatedSession;
use App\Domains\Auth\DTOs\PlatformLoginData;
use App\Models\UtilisateurPlateforme;

interface PlatformAuthServiceInterface
{
    public function login(PlatformLoginData $data): PlatformAuthenticatedSession;

    public function logout(UtilisateurPlateforme $utilisateur): void;

    public function logoutAll(UtilisateurPlateforme $utilisateur): void;
}
