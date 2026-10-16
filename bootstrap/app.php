<?php

use App\Http\Middleware\EnsureTenantPermission;
use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\EstablishmentMiddleware;
use App\Http\Middleware\PeriodeOuverteMiddleware;
use App\Http\Middleware\PlatformMiddleware;
use App\Http\Middleware\RequestLogging;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\TenantValidatedMiddleware;
use App\Http\Responses\ProblemDetailsResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));

        $middleware->append(SecurityHeaders::class);
        $middleware->append(RequestLogging::class);

        $middleware->alias([
            'establishment' => EstablishmentMiddleware::class,
            'platform' => PlatformMiddleware::class,
            'tenant' => TenantMiddleware::class,
            'tenant.validated' => TenantValidatedMiddleware::class,
            'require.password.change' => RequirePasswordChange::class,
            'periode.ouverte' => PeriodeOuverteMiddleware::class,
            'tenant.role' => EnsureTenantRole::class,
            'tenant.permission' => EnsureTenantPermission::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request): ?string => null);

        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EstablishmentMiddleware::class,
        );

        $middleware->prependToPriorityList(
            TenantValidatedMiddleware::class,
            RequirePasswordChange::class,
        );

        $middleware->prependToPriorityList(
            EstablishmentMiddleware::class,
            TenantValidatedMiddleware::class,
        );

        $middleware->prependToPriorityList(
            TenantValidatedMiddleware::class,
            TenantMiddleware::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $response = ProblemDetailsResponse::fromException($exception, $request);

            if ($request->attributes->has('request_id')) {
                $response->headers->set('X-Request-Id', (string) $request->attributes->get('request_id'));
            }

            return $response;
        });
    })->create();
