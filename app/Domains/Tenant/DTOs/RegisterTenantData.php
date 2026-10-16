<?php

namespace App\Domains\Tenant\DTOs;

final readonly class RegisterTenantData
{
    public function __construct(
        public string $nomEspace,
        public string $typeTenant,
        public ?RegisterEtablissementData $etablissement,
        public RegisterAdministrateurData $administrateur,
    ) {}
}
