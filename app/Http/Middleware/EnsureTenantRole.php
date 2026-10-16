<?php

namespace App\Http\Middleware;

use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie un rôle Spatie dans le scope du tenant courant.
 *
 * Usage : `->middleware('tenant.role:ADMIN_TENANT|ADMIN_ECOLE')` (OU logique).
 */
final class EnsureTenantRole
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user instanceof Utilisateur) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($user->hasTenantRole(explode('|', $roles))) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}
