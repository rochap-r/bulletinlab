<?php

namespace App\Domains\Membership\DTOs;

final readonly class CreateDirectUtilisateurData
{
    public function __construct(
        public string $nom,
        public ?string $postnom,
        public ?string $prenom,
        public ?string $email,
        public string $telephone,
        public string $role,
        public ?int $etablissementId,
        public ?string $sexe = null,
        public ?string $dateNaissance = null,
        public ?string $lieuNaissance = null,
        public ?string $adresse = null,
        public ?string $matricule = null,
        public ?string $qualification = null,
        public ?string $specialite = null,
        public ?string $dateEmbauche = null,
    ) {}
}
