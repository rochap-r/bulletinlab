<?php

namespace App\Domains\Tenant\Contracts;

use App\Models\Tenant;

interface TenantServiceInterface
{
    public function findById(int $id): Tenant;

    public function findBySlug(string $slug): Tenant;

    public function getCurrentTenant(): Tenant;
}
