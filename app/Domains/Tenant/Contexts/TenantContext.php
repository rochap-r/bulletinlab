<?php

namespace App\Domains\Tenant\Contexts;

use App\Domains\Tenant\Exceptions\TenantNotFoundException;
use App\Models\Tenant;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function current(): Tenant
    {
        return $this->tenant ?? throw new TenantNotFoundException('No tenant context found');
    }

    public function id(): int
    {
        return $this->current()->getKey();
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
