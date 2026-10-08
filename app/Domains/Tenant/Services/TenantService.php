<?php

namespace App\Domains\Tenant\Services;

use App\Domains\Tenant\Contexts\TenantContext;
use App\Domains\Tenant\Contracts\TenantServiceInterface;
use App\Domains\Tenant\Exceptions\TenantNotFoundException;
use App\Models\Tenant;

class TenantService implements TenantServiceInterface
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    public function findById(int $id): Tenant
    {
        $tenant = Tenant::find($id);

        if (! $tenant) {
            throw new TenantNotFoundException("Tenant with ID {$id} not found");
        }

        return $tenant;
    }

    public function findBySlug(string $slug): Tenant
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            throw new TenantNotFoundException("Tenant with slug {$slug} not found");
        }

        return $tenant;
    }

    public function getCurrentTenant(): Tenant
    {
        return $this->tenantContext->current();
    }
}
