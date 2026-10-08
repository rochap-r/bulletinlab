<?php

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Http\Middleware\EstablishmentMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Models\AnneeScolaire;
use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function createTenantForEstablishmentBoundaryTests(array $attributes = []): Tenant
{
    return Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'Tenant Boundary',
        'slug' => 'tenant-boundary-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createEstablishmentForBoundaryTests(Tenant $tenant, array $attributes = []): Etablissement
{
    return Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Établissement Boundary',
        'actif' => true,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createUserForEstablishmentBoundaryTests(Tenant $tenant, array $attributes = []): Utilisateur
{
    return Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => null,
        'nom' => 'Boundary',
        'postnom' => 'User',
        'prenom' => 'Test',
        'email' => 'boundary-'.Str::lower(Str::random(8)).'@example.test',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
        ...$attributes,
    ]);
}

function tokenForEstablishmentBoundaryTests(Utilisateur $user, Tenant $tenant): string
{
    return $user->createToken(
        'establishment-boundary',
        ["tenant:{$tenant->id}"],
    )->plainTextToken;
}

function grantEstablishmentManagementRole(
    Utilisateur $user,
    Tenant $tenant,
): void {
    $role = Role::query()->firstOrCreate(
        [
            'tenant_id' => $tenant->id,
            'code' => 'ADMIN_ECOLE',
        ],
        [
            'nom' => 'Administrateur école',
            'system_role' => false,
        ],
    );

    $user->roles()->syncWithoutDetaching($role);
}

test('school routes fail closed without an active establishment in the authenticated tenant', function () {
    $tenant = createTenantForEstablishmentBoundaryTests();
    $userWithoutEstablishment = createUserForEstablishmentBoundaryTests($tenant);

    $this->withToken(tokenForEstablishmentBoundaryTests($userWithoutEstablishment, $tenant))
        ->getJson('/api/v1/eleves')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    $inactiveEstablishment = createEstablishmentForBoundaryTests($tenant, ['actif' => false]);
    $userWithInactiveEstablishment = createUserForEstablishmentBoundaryTests($tenant, [
        'etablissement_id' => $inactiveEstablishment->id,
    ]);

    $this->withToken(tokenForEstablishmentBoundaryTests($userWithInactiveEstablishment, $tenant))
        ->getJson('/api/v1/eleves')
        ->assertNotFound();

    $otherTenant = createTenantForEstablishmentBoundaryTests();
    $foreignEstablishment = createEstablishmentForBoundaryTests($otherTenant);
    $userWithForeignEstablishment = createUserForEstablishmentBoundaryTests($tenant, [
        'etablissement_id' => $foreignEstablishment->id,
    ]);

    $this->withToken(tokenForEstablishmentBoundaryTests($userWithForeignEstablishment, $tenant))
        ->getJson('/api/v1/eleves')
        ->assertNotFound();
});

test('student creation derives tenant and establishment from server contexts', function () {
    $tenant = createTenantForEstablishmentBoundaryTests();
    $establishment = createEstablishmentForBoundaryTests($tenant);
    $user = createUserForEstablishmentBoundaryTests($tenant, [
        'etablissement_id' => $establishment->id,
    ]);
    grantEstablishmentManagementRole($user, $tenant);

    $this->withToken(tokenForEstablishmentBoundaryTests($user, $tenant))
        ->postJson('/api/v1/eleves', [
            'nom' => 'Mbuyi',
            'prenom' => 'Amina',
            'sexe' => 'F',
        ])
        ->assertCreated()
        ->assertJson([
            'data' => [
                'nom' => 'Mbuyi',
            ],
        ]);

    $this->assertDatabaseHas('eleves', [
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'nom' => 'Mbuyi',
    ]);

    expect(app(TenantContext::class)->has())->toBeFalse()
        ->and(app(EstablishmentContext::class)->has())->toBeFalse();
});

test('the global school year is readable through the current school year route', function () {
    $tenant = createTenantForEstablishmentBoundaryTests();
    $establishment = createEstablishmentForBoundaryTests($tenant);
    $user = createUserForEstablishmentBoundaryTests($tenant, [
        'etablissement_id' => $establishment->id,
    ]);
    grantEstablishmentManagementRole($user, $tenant);

    AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);

    $this->withToken(tokenForEstablishmentBoundaryTests($user, $tenant))
        ->getJson('/api/v1/annees-scolaires/courante')
        ->assertOk()
        ->assertJson([
            'data' => [
                'libelle' => '2026-2027',
                'active' => true,
                'cloturee' => false,
            ],
        ]);
});

test('an opening belongs to the establishment and is idempotent', function () {
    $tenant = createTenantForEstablishmentBoundaryTests();
    $establishment = createEstablishmentForBoundaryTests($tenant);
    $user = createUserForEstablishmentBoundaryTests($tenant, [
        'etablissement_id' => $establishment->id,
    ]);
    grantEstablishmentManagementRole($user, $tenant);
    $year = AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);

    $this->withToken(tokenForEstablishmentBoundaryTests($user, $tenant))
        ->postJson('/api/v1/ouvertures-annuelles', [
            'annee_scolaire_id' => $year->id,
        ])
        ->assertCreated()
        ->assertJson([
            'data' => [
                'etablissement_id' => $establishment->id,
                'annee_scolaire_id' => $year->id,
                'statut' => 'OUVERTE',
            ],
        ]);

    $this->withToken(tokenForEstablishmentBoundaryTests($user, $tenant))
        ->postJson('/api/v1/ouvertures-annuelles', [
            'annee_scolaire_id' => $year->id,
        ])
        ->assertCreated();

    $this->assertDatabaseCount('ouvertures_annuelles', 1);
    $this->assertDatabaseHas('ouvertures_annuelles', [
        'etablissement_id' => $establishment->id,
        'annee_scolaire_id' => $year->id,
        'statut' => 'OUVERTE',
    ]);
});

test('school routes reject client managed context identifiers', function () {
    $tenant = createTenantForEstablishmentBoundaryTests();
    $establishment = createEstablishmentForBoundaryTests($tenant);
    $user = createUserForEstablishmentBoundaryTests($tenant, [
        'etablissement_id' => $establishment->id,
    ]);
    grantEstablishmentManagementRole($user, $tenant);
    $otherTenant = createTenantForEstablishmentBoundaryTests();
    $otherEstablishment = createEstablishmentForBoundaryTests($otherTenant);

    $this->withToken(tokenForEstablishmentBoundaryTests($user, $tenant))
        ->postJson('/api/v1/eleves', [
            'tenant_id' => $otherTenant->id,
            'etablissement_id' => $otherEstablishment->id,
            'nom' => 'Forged',
        ])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonValidationErrors(['tenant_id', 'etablissement_id']);

    $this->assertDatabaseMissing('eleves', ['nom' => 'Forged']);
});

test('enrollment rejects an unknown class before validating the payload', function () {
    $tenant = createTenantForEstablishmentBoundaryTests();
    $establishment = createEstablishmentForBoundaryTests($tenant);
    $user = createUserForEstablishmentBoundaryTests($tenant, [
        'etablissement_id' => $establishment->id,
    ]);
    grantEstablishmentManagementRole($user, $tenant);

    $this->withToken(tokenForEstablishmentBoundaryTests($user, $tenant))
        ->postJson('/api/v1/classes/999999/inscriptions', [
            'eleve_id' => 999999,
        ])
        ->assertNotFound();
});

test('establishment context is resolved after tenant and before route bindings', function () {
    $route = Route::getRoutes()->getByName('api.v1.eleves.index');
    $middleware = app(Router::class)->gatherRouteMiddleware($route);

    $tenantPosition = array_search(TenantMiddleware::class, $middleware, true);
    $establishmentPosition = array_search(EstablishmentMiddleware::class, $middleware, true);
    $bindingPosition = array_search(SubstituteBindings::class, $middleware, true);

    expect($tenantPosition)->toBeInt()
        ->and($establishmentPosition)->toBeInt()
        ->and($bindingPosition)->toBeInt()
        ->and($tenantPosition)->toBeLessThan($establishmentPosition)
        ->and($establishmentPosition)->toBeLessThan($bindingPosition);
});
