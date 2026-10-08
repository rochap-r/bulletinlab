<?php

use App\Domains\Tenant\Contracts\TenantValidationServiceInterface;
use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    cache()->flush();
});

function createPendingTenantForAccessTests(string $statut = 'EN_ATTENTE'): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe En Attente',
        'slug' => 'pending-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => $statut,
    ]);
}

function createPendingTenantManagerForAccessTests(
    Tenant $tenant,
    string $telephone = '+243811000001',
): Utilisateur {
    $establishment = Etablissement::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'École En Attente',
        'actif' => true,
    ]);

    $role = Role::query()->firstOrCreate(
        ['tenant_id' => $tenant->id, 'code' => 'ADMIN_ECOLE'],
        ['nom' => 'Administrateur école', 'system_role' => false],
    );

    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'nom' => 'Mbala',
        'postnom' => 'Kasongo',
        'prenom' => 'Grâce',
        'email' => 'pending-'.Str::lower(Str::random(8)).'@example.test',
        'telephone' => $telephone,
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);

    $user->roles()->attach($role);

    return $user;
}

function tokenForPendingTenantManager(Utilisateur $user, Tenant $tenant): string
{
    return $user->createToken('pending-suite', ["tenant:{$tenant->id}"])->plainTextToken;
}

test('a pending-tenant administrator can log in', function () {
    $tenant = createPendingTenantForAccessTests();
    $user = createPendingTenantManagerForAccessTests($tenant, '+243811000001');

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'identifiant' => $user->telephone,
        'password' => 'secret-password',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure(['data' => ['access_token']]);
});

test('login is refused for a refused tenant', function () {
    $tenant = createPendingTenantForAccessTests('REFUSE');
    $user = createPendingTenantManagerForAccessTests($tenant, '+243811000001');

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'identifiant' => $user->telephone,
        'password' => 'secret-password',
    ])->assertUnauthorized();
});

test('a pending-tenant administrator can read their own profile', function () {
    $tenant = createPendingTenantForAccessTests();
    $user = createPendingTenantManagerForAccessTests($tenant);

    $this->withToken(tokenForPendingTenantManager($user, $tenant))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

test('business endpoints are blocked for a pending tenant', function () {
    $tenant = createPendingTenantForAccessTests();
    $user = createPendingTenantManagerForAccessTests($tenant);

    $this->withToken(tokenForPendingTenantManager($user, $tenant))
        ->getJson('/api/v1/classes')
        ->assertForbidden()
        ->assertJsonPath('status', 403)
        ->assertJsonPath('title', 'Forbidden');
});

test('the same administrator gains access after platform validation', function () {
    $tenant = createPendingTenantForAccessTests();
    $user = createPendingTenantManagerForAccessTests($tenant);

    $valideur = UtilisateurPlateforme::factory()->create();
    app(TenantValidationServiceInterface::class)->activate($tenant, $valideur);

    $this->withToken(tokenForPendingTenantManager($user, $tenant))
        ->getJson('/api/v1/classes')
        ->assertOk();
});
