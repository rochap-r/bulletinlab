<?php

namespace App\Domains\Auth\Contracts;

use App\Domains\Auth\DTOs\AuthenticatedSession;
use App\Domains\Auth\DTOs\GlobalLoginResult;
use App\Domains\Auth\DTOs\LoginData;
use App\Models\Utilisateur;

interface AuthServiceInterface
{
    public function login(LoginData $data): AuthenticatedSession;

    public function loginGlobal(string $identifiant, string $password): GlobalLoginResult;

    public function selectTenant(Utilisateur $selector, int $tenantId): AuthenticatedSession;

    public function logout(Utilisateur $utilisateur): void;

    public function logoutAll(Utilisateur $utilisateur): void;

    public function switchTenant(Utilisateur $user, int $tenantId): AuthenticatedSession;
}
