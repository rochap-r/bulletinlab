<?php

use App\Models\RolePlateforme;
use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use App\Support\AccessTokenCookies;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('browser authentication uses an http only cookie without exposing the token', function () {
    config()->set('session.secure', true);

    $tenant = Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École sécurisée',
        'slug' => 'ecole-securisee',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
    $user = Utilisateur::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'telephone' => '+243810000088',
        'mot_de_passe' => Hash::make('SecurePassword1'),
        'actif' => true,
    ]);

    $response = $this
        ->withHeaders([
            'Origin' => 'http://localhost',
            AccessTokenCookies::TRANSPORT_HEADER => AccessTokenCookies::COOKIE_TRANSPORT,
        ])
        ->postJson('/api/v1/auth/login', [
            'tenant_slug' => $tenant->slug,
            'identifiant' => $user->telephone,
            'password' => 'SecurePassword1',
        ])
        ->assertOk()
        ->assertJsonMissingPath('data.access_token')
        ->assertJsonMissingPath('data.token_type');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === AccessTokenCookies::TENANT);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax');

    $decryptedCookie = app('encrypter')->decrypt($cookie->getValue(), false);
    $plainTextToken = CookieValuePrefix::validate(
        AccessTokenCookies::TENANT,
        $decryptedCookie,
        app('encrypter')->getAllKeys(),
    );

    expect($plainTextToken)->toBeString();

    $this
        ->withHeaders([
            'Origin' => 'http://localhost',
            AccessTokenCookies::TRANSPORT_HEADER => AccessTokenCookies::COOKIE_TRANSPORT,
        ])
        ->withCredentials()
        ->withCookie(AccessTokenCookies::TENANT, $plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.telephone', '+243810000088');
});

test('bearer clients retain the token response contract', function () {
    $tenant = Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École API',
        'slug' => 'ecole-api',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
    $user = Utilisateur::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'telephone' => '+243810000089',
        'mot_de_passe' => Hash::make('SecurePassword1'),
        'actif' => true,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'identifiant' => $user->telephone,
        'password' => 'SecurePassword1',
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['user', 'access_token', 'token_type']]);
});

test('public registration rejects weak passwords', function () {
    $this->postJson('/api/v1/public/tenants', [
        'nom_espace' => 'École faible',
        'type_tenant' => 'ECOLE',
        'admin_nom' => 'Admin',
        'admin_telephone' => '+243810000090',
        'mot_de_passe' => 'faible',
        'mot_de_passe_confirmation' => 'faible',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mot_de_passe');
});

test('platform routes deny active users without the administrator role', function () {
    RolePlateforme::query()->create([
        'code' => 'SUPPORT_PLATEFORME',
        'nom' => 'Support plateforme',
    ]);
    $user = UtilisateurPlateforme::factory()->create(['actif' => true]);
    $user->roles()->attach('SUPPORT_PLATEFORME');
    $token = $user->createToken('support', ['context:platform']);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/platform/me')
        ->assertNotFound();
});

test('api responses disable caching and browser embedding', function () {
    $this->getJson('/api/v1/route-inexistante')
        ->assertNotFound()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
});
