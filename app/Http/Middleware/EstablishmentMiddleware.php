<?php

namespace App\Http\Middleware;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Domains\Tenant\Exceptions\EstablishmentNotFoundException;
use App\Models\Etablissement;
use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class EstablishmentMiddleware
{
    public function __construct(
        private EstablishmentContext $establishmentContext,
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

        if (! $user instanceof Utilisateur || ! $user->etablissement_id) {
            throw new EstablishmentNotFoundException;
        }

        $establishment = Etablissement::withoutGlobalScopes()
            ->whereKey($user->etablissement_id)
            ->where('tenant_id', $this->tenantContext->id())
            ->where('actif', true)
            ->first();

        if (! $establishment) {
            throw new EstablishmentNotFoundException;
        }

        $this->establishmentContext->set($establishment);

        try {
            $this->rejectClientManagedContext($request);

            return $next($request);
        } finally {
            $this->establishmentContext->clear();
        }
    }

    private function rejectClientManagedContext(Request $request): void
    {
        $errors = [];

        foreach (['tenant_id', 'etablissement_id'] as $attribute) {
            if ($request->exists($attribute)) {
                $errors[$attribute] = ["The {$attribute} field is managed by the server."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
