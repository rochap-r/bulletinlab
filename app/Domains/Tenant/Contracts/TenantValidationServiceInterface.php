<?php

namespace App\Domains\Tenant\Contracts;

use App\Models\Tenant;
use App\Models\UtilisateurPlateforme;

interface TenantValidationServiceInterface
{
    public function activate(Tenant $tenant, UtilisateurPlateforme $validePar): Tenant;

    public function reject(Tenant $tenant, UtilisateurPlateforme $validePar, string $motif): Tenant;

    public function reexamine(Tenant $tenant, UtilisateurPlateforme $validePar): Tenant;
}
