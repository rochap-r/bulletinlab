<?php

namespace App\Domains\Auth\Contracts;

use App\Models\Utilisateur;

interface AuthServiceInterface
{
    public function login(string $telephone, string $password): array;
    public function register(array $data): Utilisateur;
    public function logout(): void;
}
