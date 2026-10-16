<?php

namespace App\Domains\Membership\DTOs;

final readonly class RattacherUtilisateurData
{
    public function __construct(
        public string $telephone,
        public string $nom,
        public string $role,
        public ?int $etablissementId,
    ) {}
}
