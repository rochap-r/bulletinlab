<?php

namespace App\Http\Middleware;

use App\Support\AccessTokenCookies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateWithAccessTokenCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = null;

        if ($request->bearerToken() === null && AccessTokenCookies::usesCookieTransport($request)) {
            $cookieName = AccessTokenCookies::forRequest($request);
            $token = $cookieName === null ? null : $request->cookie($cookieName);

            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        $response = $next($request);

        if ($cookieName !== null && $response->getStatusCode() === Response::HTTP_UNAUTHORIZED) {
            $response->headers->setCookie(AccessTokenCookies::forget($cookieName));
        }

        return $response;
    }
}
