<?php

namespace App\Http\Middleware;

use App\Domains\Tenant\Exceptions\TenantNotFoundException;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            throw new TenantNotFoundException();
        }

        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            throw new TenantNotFoundException();
        }

        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
