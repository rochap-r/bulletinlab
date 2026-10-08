<?php

namespace App\Domains\Tenant\DTOs;

final readonly class RegisterEtablissementData
{
    public function __construct(
        public string $nom,
        public ?string $codeMinistere,
        public ?string $provinceEducationnelle,
        public ?string $province,
        public ?string $ville,
        public ?string $communeTerritoire,
        public ?string $adresse,
        public ?string $telephone,
        public ?string $email,
    ) {}
}
