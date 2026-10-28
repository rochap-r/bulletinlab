<?php

namespace App\Http\Middleware;

use App\Domains\Responsable\Exceptions\ResponsableAuthenticationException;
use App\Models\Responsable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class ResponsableMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        $accessToken = is_string($plainTextToken)
            ? PersonalAccessToken::findToken($plainTextToken)
            : null;
        $responsable = $accessToken?->tokenable;

        if (! $responsable instanceof Responsable
            || ! $accessToken instanceof PersonalAccessToken
            || ! $accessToken->can('context:responsable')
            || ($accessToken->expires_at !== null && $accessToken->expires_at->isPast())) {
            throw new ResponsableAuthenticationException('Unauthenticated');
        }

        abort_unless($responsable->actif, Response::HTTP_FORBIDDEN);

        $responsable->withAccessToken($accessToken);
        $request->setUserResolver(fn (): Responsable => $responsable);

        Context::add([
            'responsable_id' => $responsable->getKey(),
            'user_type' => 'responsable',
        ]);

        return $next($request);
    }
}
