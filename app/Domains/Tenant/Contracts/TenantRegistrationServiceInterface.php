<?php

namespace App\Domains\Tenant\Contracts;

use App\Domains\Tenant\DTOs\RegisteredTenantResult;
use App\Domains\Tenant\DTOs\RegisterTenantData;

interface TenantRegistrationServiceInterface
{
    public function register(RegisterTenantData $data): RegisteredTenantResult;
}
