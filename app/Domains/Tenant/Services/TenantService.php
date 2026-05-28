<?php

namespace App\Domains\Tenant\Services;

use App\Domains\Tenant\Contracts\TenantServiceInterface;
use App\Domains\Tenant\Exceptions\TenantNotFoundException;
use App\Models\Tenant;
use Illuminate\Support\Facades\App;

class TenantService implements TenantServiceInterface
{
    public function findById(int $id): Tenant
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            throw new TenantNotFoundException("Tenant with ID {$id} not found");
        }

        return $tenant;
    }

    public function findBySlug(string $slug): Tenant
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (!$tenant) {
            throw new TenantNotFoundException("Tenant with slug {$slug} not found");
        }

        return $tenant;
    }

    public function getCurrentTenant(): Tenant
    {
        if (!App::has('tenant')) {
            throw new TenantNotFoundException('No tenant context found');
        }

        return App::get('tenant');
    }
}
