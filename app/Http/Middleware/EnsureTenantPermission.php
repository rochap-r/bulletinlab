<?php

namespace App\Http\Middleware;

use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie une permission Spatie dans le scope du tenant courant.
 *
 * Utilise `$request->user()` (déjà authentifié via Sanctum `tenant:{id}`)
 * au lieu de `Auth::guard()`, puis `hasTenantPermission()` qui combine
 * le check Spatie (équipe courante via `setPermissionsTeamId()`) et la
 * garde métier (`roles.tenant_id` système ou même tenant).
 *
 * Usage : `->middleware('tenant.permission:teachers.manage')`
 * ou `->middleware('tenant.permission:notes.enter|notes.monitor')` (OU logique).
 */
final class EnsureTenantPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof Utilisateur) {
            abort(Response::HTTP_FORBIDDEN);
        }

        foreach (explode('|', $permissions) as $permission) {
            if ($user->hasTenantPermission(trim($permission))) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}
