<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class AccessTokenCookies
{
    public const TENANT = 'topscolaire_tenant_session';

    public const TENANT_SELECT = 'topscolaire_tenant_select';

    public const PLATFORM = 'topscolaire_platform_session';

    public const RESPONSABLE = 'topscolaire_responsable_session';

    public const TRANSPORT_HEADER = 'X-Auth-Mode';

    public const COOKIE_TRANSPORT = 'cookie';

    public static function usesCookieTransport(Request $request): bool
    {
        return $request->headers->get(self::TRANSPORT_HEADER) === self::COOKIE_TRANSPORT;
    }

    public static function forRequest(Request $request): ?string
    {
        if (! $request->is('api/v1/*')) {
            return null;
        }

        if ($request->is('api/v1/auth/select')) {
            return self::TENANT_SELECT;
        }

        if ($request->is('api/v1/platform/*')) {
            return self::PLATFORM;
        }

        if ($request->is('api/v1/responsables/*')) {
            return self::RESPONSABLE;
        }

        return self::TENANT;
    }

    public static function make(Request $request, string $name, string $token, ?int $minutes = null): Cookie
    {
        $lifetime = $minutes ?? (int) config('sanctum.expiration', 720);

        return cookie(
            name: $name,
            value: $token,
            minutes: $lifetime,
            path: '/api/v1',
            domain: config('session.domain'),
            secure: config('session.secure') ?? $request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: config('session.same_site', 'lax'),
        );
    }

    public static function forget(string $name): Cookie
    {
        return cookie()->forget(
            name: $name,
            path: '/api/v1',
            domain: config('session.domain'),
        );
    }
}
