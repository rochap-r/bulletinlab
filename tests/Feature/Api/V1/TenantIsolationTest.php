<?php

use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createIsolationTenant(array $attributes = []): Tenant
{
    return Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'Isolation',
        'slug' => 'isolation-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
        ...$attributes,
    ]);
}

function createIsolationEstablishment(Tenant $tenant): Etablissement
{
    return Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Établissement Isolation',
        'actif' => true,
    ]);
}

function createIsolationUser(Tenant $tenant, ?int $etablissementId = null): Utilisateur
{
    return Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissementId,
        'nom' => 'Isolation',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
}

test('a tenant token cannot read an eleve from another tenant', function () {
    $tenantA = createIsolationTenant();
    $etablissementA = createIsolationEstablishment($tenantA);
    $userA = createIsolationUser($tenantA, $etablissementA->id);
    $tokenA = $userA->createToken('isolation-a', ["tenant:{$tenantA->id}"])->plainTextToken;

    $tenantB = createIsolationTenant();
    $etablissementB = createIsolationEstablishment($tenantB);
    $eleveB = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'etablissement_id' => $etablissementB->id,
        'nom' => 'Cible',
        'sexe' => 'M',
        'actif' => true,
    ]);

    $this->withToken($tokenA)
        ->getJson("/api/v1/eleves/{$eleveB->id}")
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('a tenant token is rejected on platform routes', function () {
    $tenant = createIsolationTenant();
    $etablissement = createIsolationEstablishment($tenant);
    $user = createIsolationUser($tenant, $etablissement->id);
    $token = $user->createToken('isolation-tenant', ["tenant:{$tenant->id}"])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/platform/me')
        ->assertNotFound();
});

test('client managed tenant identifiers are rejected', function () {
    $tenant = createIsolationTenant();
    $etablissement = createIsolationEstablishment($tenant);
    $user = createIsolationUser($tenant, $etablissement->id);
    $token = $user->createToken('isolation-forge', ["tenant:{$tenant->id}"])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/eleves?tenant_id=999999', [
            'nom' => 'Forge',
            'sexe' => 'F',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_id']);
});
