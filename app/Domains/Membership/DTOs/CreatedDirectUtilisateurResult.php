<?php

namespace App\Domains\Membership\DTOs;

use App\Models\Utilisateur;

final readonly class CreatedDirectUtilisateurResult
{
    public function __construct(public Utilisateur $utilisateur, public string $motDePasseTemporaire) {}
}
