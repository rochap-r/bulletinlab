<?php

use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    cache()->flush();
});

function globalTenant(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
}

function globalRole(string $code): Role
{
    return Role::query()->firstOrCreate(
        ['tenant_id' => null, 'code' => $code],
        ['nom' => Str::headline($code), 'system_role' => true],
    );
}

function globalUser(Tenant $tenant, string $phone): Utilisateur
{
    $school = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id, 'nom' => 'École', 'type_etablissement' => 'PRIMAIRE', 'actif' => true,
    ]);
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $school->id,
        'nom' => 'Enseignant',
        'telephone' => $phone,
        'mot_de_passe' => Hash::make('mot-de-passe-commun'),
        'actif' => true,
    ]);
    $user->roles()->attach(globalRole('ENSEIGNANT'));

    return $user;
}

test('a global login opens a session directly for a single space', function (): void {
    $tenant = globalTenant('global-unique');
    globalUser($tenant, '+243841000001');

    $this->postJson('/api/v1/auth/login', [
        'identifiant' => '+243841000001',
        'password' => 'mot-de-passe-commun',
    ])->assertOk()
        ->assertJsonPath('data.user.telephone', '+243841000001')
        ->assertJsonPath('data.user.tenant.slug', 'global-unique')
        ->assertJsonMissingPath('data.select_token');
});

test('a global login returns spaces and a one-shot select token for several spaces', function (): void {
    $tenantA = globalTenant('global-multi-a');
    $tenantB = globalTenant('global-multi-b');
    globalUser($tenantA, '+243841000002');
    globalUser($tenantB, '+243841000002');

    $response = $this->postJson('/api/v1/auth/login', [
        'identifiant' => '+243841000002',
        'password' => 'mot-de-passe-commun',
    ])->assertOk()->assertJsonCount(2, 'data.memberships')
        ->assertJsonMissingPath('data.access_token');

    $selectToken = $response->json('data.select_token');
    expect($selectToken)->toBeString();

    $selected = $this->withToken($selectToken)->postJson('/api/v1/auth/select', [
        'tenant_id' => $tenantB->id,
    ])->assertOk()->assertJsonPath('data.user.tenant.slug', 'global-multi-b');

    expect($selected->json('data.access_token'))->toBeString();
    $this->assertDatabaseHas('audit_logs', ['action' => 'TENANT_SWITCH', 'tenant_id' => $tenantB->id]);

    $this->app['auth']->forgetGuards();
    $this->withToken($selectToken)->postJson('/api/v1/auth/select', [
        'tenant_id' => $tenantA->id,
    ])->assertUnauthorized();
});

test('a global login stays generic on bad credentials', function (): void {
    $tenant = globalTenant('global-bad');
    globalUser($tenant, '+243841000003');

    $this->postJson('/api/v1/auth/login', [
        'identifiant' => '+243841000003',
        'password' => 'mauvais-mot-de-passe',
    ])->assertUnauthorized()->assertJsonPath('detail', 'Invalid credentials');

    $this->postJson('/api/v1/auth/login', [
        'identifiant' => '+243841000099',
        'password' => 'mot-de-passe-commun',
    ])->assertUnauthorized()->assertJsonPath('detail', 'Invalid credentials');
});

test('a select token cannot reach business routes nor select a foreign space', function (): void {
    $tenantA = globalTenant('global-sel-a');
    $tenantB = globalTenant('global-sel-b');
    $tenantC = globalTenant('global-sel-c');
    globalUser($tenantA, '+243841000004');
    globalUser($tenantB, '+243841000004');
    globalUser($tenantC, '+243841000005');

    $selectToken = $this->postJson('/api/v1/auth/login', [
        'identifiant' => '+243841000004',
        'password' => 'mot-de-passe-commun',
    ])->assertOk()->json('data.select_token');

    $this->withToken($selectToken)->getJson('/api/v1/me')->assertNotFound();

    $this->withToken($selectToken)->postJson('/api/v1/auth/select', [
        'tenant_id' => $tenantC->id,
    ])->assertUnauthorized()->assertJsonPath('detail', 'Invalid credentials');

    $full = globalUser($tenantA, '+243841000006');
    $fullToken = $full->createToken('full', ["tenant:{$tenantA->id}"])->plainTextToken;

    $this->app['auth']->forgetGuards();
    $this->withToken($fullToken)->postJson('/api/v1/auth/select', [
        'tenant_id' => $tenantB->id,
    ])->assertUnauthorized();
});
