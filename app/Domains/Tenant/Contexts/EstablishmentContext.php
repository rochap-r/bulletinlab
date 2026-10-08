<?php

namespace App\Domains\Tenant\Contexts;

use App\Domains\Tenant\Exceptions\EstablishmentNotFoundException;
use App\Models\Etablissement;

final class EstablishmentContext
{
    private ?Etablissement $establishment = null;

    public function set(Etablissement $establishment): void
    {
        $this->establishment = $establishment;
    }

    public function has(): bool
    {
        return $this->establishment !== null;
    }

    public function current(): Etablissement
    {
        return $this->establishment
            ?? throw new EstablishmentNotFoundException('No establishment context found');
    }

    public function id(): int
    {
        return $this->current()->getKey();
    }

    public function clear(): void
    {
        $this->establishment = null;
    }
}
