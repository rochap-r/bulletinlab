<?php

namespace App\Domains\Tenant\DTOs;

use App\Models\Etablissement;
use App\Models\Tenant;
use App\Models\Utilisateur;

final readonly class RegisteredTenantResult
{
    public function __construct(
        public Tenant $tenant,
        public ?Etablissement $etablissement,
        public Utilisateur $utilisateur,
    ) {}
}
