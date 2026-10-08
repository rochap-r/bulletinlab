<?php

namespace App\Models\Concerns;

use App\Domains\Tenant\Contexts\TenantContext;
use App\Models\Scopes\TenantScope;

trait HasTenant
{
    protected static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $tenantContext = app(TenantContext::class);

            if ($tenantContext->has()) {
                $model->tenant_id = $tenantContext->id();
            }
        });

        static::updating(function ($model): void {
            $tenantContext = app(TenantContext::class);

            if ($tenantContext->has()) {
                $model->tenant_id = $tenantContext->id();
            }
        });
    }
}
