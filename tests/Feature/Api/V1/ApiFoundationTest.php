<?php

use App\Http\Middleware\TenantMiddleware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

test('every V1 route has the API name prefix', function () {
    $v1Routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'api/v1'));

    expect($v1Routes)->not->toBeEmpty();

    $invalidRoutes = $v1Routes
        ->reject(fn (RouteDefinition $route): bool => str_starts_with((string) $route->getName(), 'api.v1.'))
        ->map(fn (RouteDefinition $route): string => $route->uri())
        ->values()
        ->all();

    expect($invalidRoutes)->toBe([]);
});

test('the modular route files expose the target V1 foundation endpoints', function () {
    expect(Route::has('api.v1.auth.login'))->toBeTrue()
        ->and(Route::has('api.v1.me.show'))->toBeTrue()
        ->and(Route::has('api.v1.annees-scolaires.index'))->toBeTrue()
        ->and(Route::has('api.v1.classes.index'))->toBeTrue()
        ->and(Route::has('api.v1.eleves.index'))->toBeTrue()
        ->and(Route::has('api.v1.notes.index'))->toBeTrue()
        ->and(Route::has('api.v1.bulletins.index'))->toBeFalse()
        ->and(Route::has('api.v1.bulletins.show'))->toBeTrue()
        ->and(Route::has('api.v1.bulletins.by-classe'))->toBeTrue()
        ->and(Route::has(
            'api.v1.bulletins.periodes.apercu-calcul.show',
        ))->toBeTrue()
        ->and(Route::has(
            'api.v1.bulletins.periodes.publier.store',
        ))->toBeTrue()
        ->and(Route::has(
            'api.v1.bulletins.periodes.rectifications.store',
        ))->toBeTrue()
        ->and(Route::has(
            'api.v1.classes.periodes.proclamations.store',
        ))->toBeTrue();
});

test('tenant context is resolved before route model binding', function () {
    $route = Route::getRoutes()->getByName('api.v1.me.show');
    $middleware = app(Router::class)->gatherRouteMiddleware($route);
    $tenantPosition = array_search(TenantMiddleware::class, $middleware, true);
    $bindingPosition = array_search(SubstituteBindings::class, $middleware, true);

    expect($tenantPosition)->toBeInt()
        ->and($bindingPosition)->toBeInt()
        ->and($tenantPosition)->toBeLessThan($bindingPosition);
});

test('API not found errors use Problem Details', function () {
    $this->getJson('/api/v1/route-inexistante')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJson([
            'type' => 'about:blank',
            'title' => 'Not Found',
            'status' => 404,
            'instance' => '/api/v1/route-inexistante',
        ]);
});

test('API validation errors use Problem Details', function () {
    $this->postJson('/api/v1/auth/login')
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJson([
            'type' => 'about:blank',
            'title' => 'Unprocessable Content',
            'status' => 422,
            'instance' => '/api/v1/auth/login',
        ])
        ->assertJsonValidationErrors(['identifiant', 'password']);
});

test('API authentication errors use Problem Details', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJson([
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
            'instance' => '/api/v1/me',
        ]);
});

test('Eloquent strict mode is enabled outside production', function () {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});
