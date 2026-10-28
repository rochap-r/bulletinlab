<?php

namespace App\Domains\Responsable\Contracts;

use App\Domains\Responsable\DTOs\ResponsableAuthenticatedSession;
use App\Models\Responsable;

interface ResponsableAuthServiceInterface
{
    public function login(string $telephone, string $password): ResponsableAuthenticatedSession;

    public function setInitialPassword(
        Responsable $responsable,
        string $password,
    ): ResponsableAuthenticatedSession;

    public function logout(Responsable $responsable): void;

    public function logoutAll(Responsable $responsable): void;
}
