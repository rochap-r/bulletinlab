<?php

namespace App\Http\Middleware;

use App\Models\Responsable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireResponsablePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $responsable = $request->user();

        if ($responsable instanceof Responsable
            && $responsable->doit_changer_mot_de_passe
            && ! $request->routeIs(
                'api.v1.responsables.me.show',
                'api.v1.responsables.me.password.initial.store',
                'api.v1.responsables.auth.logout',
                'api.v1.responsables.auth.logout-all',
            )) {
            abort(Response::HTTP_FORBIDDEN, 'PASSWORD_CHANGE_REQUIRED');
        }

        return $next($request);
    }
}
