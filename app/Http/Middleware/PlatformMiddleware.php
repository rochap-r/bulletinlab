<?php

namespace App\Http\Middleware;

use App\Domains\Tenant\Contexts\PlatformContext;
use App\Domains\Tenant\Exceptions\PlatformContextNotFoundException;
use App\Models\UtilisateurPlateforme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class PlatformMiddleware
{
    public function __construct(
        private PlatformContext $platformContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof UtilisateurPlateforme) {
            throw new PlatformContextNotFoundException;
        }

        $accessToken = $user->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken
            || ! $accessToken->can('context:platform')) {
            throw new PlatformContextNotFoundException;
        }

        if (! $user->isActive()) {
            throw new PlatformContextNotFoundException;
        }

        $this->platformContext->set($user);

        Context::add([
            'user_id' => $user->getKey(),
            'user_type' => 'platform',
        ]);

        try {
            return $next($request);
        } finally {
            $this->platformContext->clear();
        }
    }
}
