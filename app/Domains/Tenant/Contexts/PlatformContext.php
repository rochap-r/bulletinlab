<?php

namespace App\Domains\Tenant\Contexts;

use App\Domains\Tenant\Exceptions\PlatformContextNotFoundException;
use App\Models\UtilisateurPlateforme;

/**
 * Contexte d'administration de la plateforme.
 *
 * Ce contexte est résolu uniquement depuis un token Sanctum de type
 * plateforme (`context:platform`). Il est distinct du contexte tenant :
 * les tokens plateforme et tenant sont refusés réciproquement hors de
 * leur contexte.
 */
final class PlatformContext
{
    private ?UtilisateurPlateforme $administrateur = null;

    public function set(UtilisateurPlateforme $administrateur): void
    {
        $this->administrateur = $administrateur;
    }

    public function has(): bool
    {
        return $this->administrateur !== null;
    }

    public function current(): UtilisateurPlateforme
    {
        return $this->administrateur
            ?? throw new PlatformContextNotFoundException('No platform context found');
    }

    public function id(): int
    {
        return $this->current()->getKey();
    }

    public function clear(): void
    {
        $this->administrateur = null;
    }
}
