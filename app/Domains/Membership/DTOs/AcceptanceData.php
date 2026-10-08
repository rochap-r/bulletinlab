<?php

namespace App\Domains\Membership\DTOs;

final readonly class AcceptanceData
{
    public function __construct(
        public string $token,
        public string $nom,
        public ?string $postnom,
        public ?string $prenom,
        public string $telephone,
        public string $motDePasse,
    ) {}
}
