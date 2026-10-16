<?php

namespace App\Http\Middleware;

use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Utilisateur
            && $user->doit_changer_mot_de_passe
            && ! $request->routeIs('me.show', 'me.password.update', 'auth.logout', 'auth.logout-all')
        ) {
            abort(Response::HTTP_FORBIDDEN, 'PASSWORD_CHANGE_REQUIRED');
        }

        return $next($request);
    }
}
