<?php

namespace App\Http\Middleware;

use App\Domains\Tenant\Contexts\TenantContext;
use App\Domains\Tenant\Exceptions\TenantNotFoundException;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Utilisateur || ! $user->tenant_id) {
            throw new TenantNotFoundException;
        }

        $accessToken = $user->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken
            || ! $accessToken->can("tenant:{$user->tenant_id}")) {
            throw new TenantNotFoundException;
        }

        abort_unless($user->actif, Response::HTTP_FORBIDDEN);

        $tenant = Tenant::query()
            ->whereKey($user->tenant_id)
            ->whereIn('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF'])
            ->first();

        if (! $tenant) {
            throw new TenantNotFoundException;
        }

        $this->tenantContext->set($tenant);

        setPermissionsTeamId($tenant->getKey());

        Context::add([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'user_type' => 'tenant',
        ]);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
            setPermissionsTeamId(null);
        }
    }
}
