<?php

namespace App\Domains\Tenant\DTOs;

final readonly class RegisterAdministrateurData
{
    public function __construct(
        public string $nom,
        public ?string $postnom,
        public ?string $prenom,
        public ?string $email,
        public string $telephone,
        public string $motDePasse,
    ) {}
}
