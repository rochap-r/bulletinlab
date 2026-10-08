<?php

use App\Domains\Tenant\Contexts\TenantContext;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createTenantForAuthEndpointTests(array $attributes = []): Tenant
{
    return Tenant::query()->create(array_merge([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École Auth',
        'slug' => 'ecole-auth',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ], $attributes));
}

function createUtilisateurForAuthEndpointTests(Tenant $tenant, array $attributes = []): Utilisateur
{
    return Utilisateur::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'nom' => 'Mukendi',
        'telephone' => '+243810000001',
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ], $attributes));
}

test('tenant login issues a tenant-bound token without exposing internal identifiers', function () {
    $tenant = createTenantForAuthEndpointTests();
    $utilisateur = createUtilisateurForAuthEndpointTests($tenant);

    $response = $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'identifiant' => $utilisateur->telephone,
        'password' => 'secret-password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.user.nom', 'Mukendi')
        ->assertJsonPath('data.user.telephone', '+243810000001')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure(['data' => ['user', 'access_token', 'token_type']])
        ->assertJsonMissingPath('data.user.id')
        ->assertJsonMissingPath('data.user.tenant_id')
        ->assertJsonMissingPath('data.user.etablissement_id');

    expect($utilisateur->tokens()->first()->abilities)
        ->toBe(["tenant:{$tenant->id}"]);
});

test('tenant login scopes an identical identifier to the requested tenant', function () {
    $firstTenant = createTenantForAuthEndpointTests();
    createUtilisateurForAuthEndpointTests($firstTenant, ['nom' => 'Premier']);

    $secondTenant = createTenantForAuthEndpointTests([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École B',
        'slug' => 'ecole-b',
    ]);
    createUtilisateurForAuthEndpointTests($secondTenant, [
        'nom' => 'Deuxième',
        'mot_de_passe' => Hash::make('second-secret'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => 'ecole-b',
        'identifiant' => '+243810000001',
        'password' => 'second-secret',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.nom', 'Deuxième');
});

test('tenant login returns the same generic error for invalid contextual credentials', function (array $payload) {
    $tenant = createTenantForAuthEndpointTests();
    $utilisateur = createUtilisateurForAuthEndpointTests($tenant);

    $response = $this->postJson('/api/v1/auth/login', array_merge([
        'tenant_slug' => $tenant->slug,
        'identifiant' => $utilisateur->telephone,
        'password' => 'secret-password',
    ], $payload));

    $response
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('detail', 'Invalid credentials');
})->with([
    'unknown tenant' => [['tenant_slug' => 'inconnu']],
    'unknown user' => [['identifiant' => '+243899999999']],
    'wrong password' => [['password' => 'incorrect']],
]);

test('inactive users receive the generic login error', function () {
    $tenant = createTenantForAuthEndpointTests();
    $utilisateur = createUtilisateurForAuthEndpointTests($tenant, ['actif' => false]);

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'identifiant' => $utilisateur->telephone,
        'password' => 'secret-password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('detail', 'Invalid credentials');
});

test('tenant login is rate limited', function () {
    $payload = [
        'tenant_slug' => 'ecole-rate-limit',
        'identifiant' => '+243810000099',
        'password' => 'incorrect',
    ];

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', $payload)
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('logout revokes only the token used by the current request', function () {
    $tenant = createTenantForAuthEndpointTests();
    $utilisateur = createUtilisateurForAuthEndpointTests($tenant);
    $firstToken = $utilisateur->createToken('first', ["tenant:{$tenant->id}"]);
    $secondToken = $utilisateur->createToken('second', ["tenant:{$tenant->id}"]);

    $this->withToken($firstToken->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $firstToken->accessToken->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $secondToken->accessToken->id]);
});

test('me returns the authenticated profile and releases the request tenant context', function () {
    $tenant = createTenantForAuthEndpointTests();
    $utilisateur = createUtilisateurForAuthEndpointTests($tenant);
    $token = $utilisateur->createToken('me', ["tenant:{$tenant->id}"]);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.nom', 'Mukendi')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.tenant_id');

    expect(app(TenantContext::class)->has())->toBeFalse();
});

test('tenant middleware rejects a token bound to another tenant', function () {
    $tenant = createTenantForAuthEndpointTests();
    $utilisateur = createUtilisateurForAuthEndpointTests($tenant);
    $token = $utilisateur->createToken('wrong-context', ['tenant:999999']);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('tenant middleware rejects an authentication without a tenant-bound personal token', function () {
    $tenant = createTenantForAuthEndpointTests();
    $utilisateur = createUtilisateurForAuthEndpointTests($tenant);

    Sanctum::actingAs($utilisateur);

    $this->getJson('/api/v1/me')->assertNotFound();
});

test('tenant middleware rejects a suspended tenant', function () {
    $tenant = createTenantForAuthEndpointTests(['statut' => 'SUSPENDU']);
    $utilisateur = createUtilisateurForAuthEndpointTests($tenant);
    $token = $utilisateur->createToken('suspended', ["tenant:{$tenant->id}"]);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertNotFound();
});

test('legacy registration user and tenant routes are removed', function () {
    $this->postJson('/api/v1/auth/register')->assertNotFound();
    $this->getJson('/api/v1/user')->assertNotFound();
    $this->getJson('/api/v1/tenants')->assertNotFound();
});
