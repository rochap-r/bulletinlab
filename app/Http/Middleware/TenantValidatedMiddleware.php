<?php

namespace App\Http\Middleware;

use App\Domains\Tenant\Contexts\TenantContext;
use App\Domains\Tenant\Exceptions\TenantNotValidatedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'accès aux ressources métier tant que le tenant est en attente
 * de validation par la plateforme (statut EN_ATTENTE).
 */
class TenantValidatedMiddleware
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantContext->current()?->isPendingValidation() === true) {
            throw new TenantNotValidatedException(
                'The tenant account is awaiting platform validation.'
            );
        }

        return $next($request);
    }
}
