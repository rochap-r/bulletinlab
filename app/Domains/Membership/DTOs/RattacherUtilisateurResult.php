<?php

namespace App\Domains\Membership\DTOs;

use App\Models\Utilisateur;

final readonly class RattacherUtilisateurResult
{
    public function __construct(
        public Utilisateur $utilisateur,
        public bool $compteCree,
    ) {}
}
