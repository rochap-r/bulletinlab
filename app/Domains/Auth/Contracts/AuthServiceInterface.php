<?php

namespace App\Domains\Auth\Contracts;

use App\Domains\Auth\DTOs\AuthenticatedSession;
use App\Domains\Auth\DTOs\LoginData;
use App\Models\Utilisateur;

interface AuthServiceInterface
{
    public function login(LoginData $data): AuthenticatedSession;

    public function logout(Utilisateur $utilisateur): void;
}
