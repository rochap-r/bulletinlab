<?php

use App\Domains\Tenant\Contexts\PlatformContext;
use App\Models\RolePlateforme;
use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createPlatformUtilisateurForTests(array $attributes = []): UtilisateurPlateforme
{
    RolePlateforme::query()->updateOrCreate(
        ['code' => 'ADMIN_PLATEFORME'],
        ['nom' => 'Administrateur plateforme'],
    );

    $utilisateur = UtilisateurPlateforme::factory()->create(array_merge([
        'email' => 'admin@plateforme.test',
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ], $attributes));

    $utilisateur->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);

    return $utilisateur;
}

function createTenantWithUtilisateurForTests(): array
{
    $tenant = Tenant::query()->create([
        'uuid' => '0193b5c0-0000-7000-8000-000000000001',
        'nom_espace' => 'École Test',
        'slug' => 'ecole-test',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $utilisateur = Utilisateur::factory()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Mukendi',
        'telephone' => '+243810000001',
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);

    return [$tenant, $utilisateur];
}

test('platform login issues a context platform token without exposing internal identifiers', function () {
    createPlatformUtilisateurForTests();

    $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@plateforme.test',
        'password' => 'secret-password',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'admin@plateforme.test')
        ->assertJsonPath('data.user.roles', ['ADMIN_PLATEFORME'])
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure(['data' => ['user', 'access_token', 'token_type']])
        ->assertJsonMissingPath('data.user.id')
        ->assertJsonMissingPath('data.user.mot_de_passe');

    $token = UtilisateurPlateforme::query()->firstOrFail()->tokens()->first();
    expect($token->abilities)->toBe(['context:platform']);
});

test('platform login returns the same generic error for invalid credentials', function (array $payload) {
    createPlatformUtilisateurForTests();

    $this->postJson('/api/v1/platform/auth/login', array_merge([
        'email' => 'admin@plateforme.test',
        'password' => 'secret-password',
    ], $payload))
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('detail', 'Invalid credentials');
})->with([
    'unknown email' => [['email' => 'inconnu@plateforme.test']],
    'wrong password' => [['password' => 'incorrect']],
]);

test('inactive platform users receive the generic login error', function () {
    createPlatformUtilisateurForTests(['actif' => false]);

    $this->postJson('/api/v1/platform/auth/login', [
        'email' => 'admin@plateforme.test',
        'password' => 'secret-password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('detail', 'Invalid credentials');
});

test('platform login is rate limited', function () {
    $payload = [
        'email' => 'rate-limit@plateforme.test',
        'password' => 'incorrect',
    ];

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/platform/auth/login', $payload)->assertUnauthorized();
    }

    $this->postJson('/api/v1/platform/auth/login', $payload)
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('platform logout revokes only the token used by the current request', function () {
    $utilisateur = createPlatformUtilisateurForTests();
    $firstToken = $utilisateur->createToken('first', ['context:platform']);
    $secondToken = $utilisateur->createToken('second', ['context:platform']);

    $this->withToken($firstToken->plainTextToken)
        ->postJson('/api/v1/platform/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $firstToken->accessToken->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $secondToken->accessToken->id]);
});

test('platform me returns the authenticated profile and releases the request platform context', function () {
    $utilisateur = createPlatformUtilisateurForTests();
    $token = $utilisateur->createToken('me', ['context:platform']);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/platform/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@plateforme.test')
        ->assertJsonPath('data.roles', ['ADMIN_PLATEFORME'])
        ->assertJsonPath('data.actif', true)
        ->assertJsonMissingPath('data.id');

    expect(app(PlatformContext::class)->has())->toBeFalse();
});

test('platform middleware rejects a tenant token on platform routes', function () {
    [$tenant, $utilisateur] = createTenantWithUtilisateurForTests();
    $token = $utilisateur->createToken('tenant', ["tenant:{$tenant->id}"]);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/platform/me')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('tenant middleware rejects a platform token on tenant routes', function () {
    $utilisateur = createPlatformUtilisateurForTests();
    $token = $utilisateur->createToken('platform', ['context:platform']);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('platform login validates the request payload', function () {
    $this->postJson('/api/v1/platform/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
