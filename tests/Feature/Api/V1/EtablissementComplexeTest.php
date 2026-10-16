<?php

use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createTenantForComplexe(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
}

function complexAdmin(Tenant $tenant): Utilisateur
{
    $role = Role::query()->firstOrCreate(
        ['tenant_id' => null, 'name' => 'ADMIN_TENANT'],
        ['code' => 'ADMIN_TENANT', 'nom' => 'Administrateur tenant', 'guard_name' => 'web', 'system_role' => true],
    );
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Administrateur',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

function tokenForComplexe(Utilisateur $user): string
{
    return $user->createToken('complexe', ["tenant:{$user->tenant_id}"])->plainTextToken;
}

test('an administrator creates the three schools of a complex in one call', function (): void {
    $tenant = createTenantForComplexe('complexe-admin');
    $admin = complexAdmin($tenant);

    $response = $this->withToken(tokenForComplexe($admin))->postJson('/api/v1/etablissements/complexe', [
        'nom_base' => 'Royal School Academy',
        'types' => ['PRIMAIRE', 'MATERNELLE', 'SECONDAIRE'],
    ])->assertCreated();

    $names = collect($response->json('data'))->pluck('nom')->all();
    expect($names)->toBe([
        'Royal School Academy Maternelle',
        'Royal School Academy Primaire',
        'Royal School Academy Secondaire',
    ]);

    $types = collect($response->json('data'))->pluck('type_etablissement')->all();
    expect($types)->toBe(['MATERNELLE', 'PRIMAIRE', 'SECONDAIRE']);

    expect(Etablissement::query()->where('tenant_id', $tenant->id)->count())->toBe(3);
});

test('a complex can be limited to a single level', function (): void {
    $tenant = createTenantForComplexe('complexe-single');
    $admin = complexAdmin($tenant);

    $this->withToken(tokenForComplexe($admin))->postJson('/api/v1/etablissements/complexe', [
        'nom_base' => 'Royal School Academy',
        'types' => ['PRIMAIRE'],
    ])->assertCreated()
        ->assertJsonPath('data.0.nom', 'Royal School Academy Primaire')
        ->assertJsonPath('data.0.type_etablissement', 'PRIMAIRE');

    expect(Etablissement::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

function complexeToken(): string
{
    $tenant = createTenantForComplexe('complexe-invalid-'.Str::lower(Str::random(6)));
    $admin = complexAdmin($tenant);

    return tokenForComplexe($admin);
}

test('a complex requires at least one level', function (): void {
    $this->withToken(complexeToken())->postJson('/api/v1/etablissements/complexe', [
        'nom_base' => 'Royal School Academy',
        'types' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['types']);
});

test('a complex only accepts maternelle, primaire and secondaire levels', function (): void {
    $token = complexeToken();

    foreach ([['UNIVERSITE'], ['MIXTE']] as $types) {
        $this->withToken($token)->postJson('/api/v1/etablissements/complexe', [
            'nom_base' => 'Royal School Academy',
            'types' => $types,
        ])->assertUnprocessable()->assertJsonValidationErrors(['types.0']);
    }
});

test('a complex rejects duplicated levels', function (): void {
    $this->withToken(complexeToken())->postJson('/api/v1/etablissements/complexe', [
        'nom_base' => 'Royal School Academy',
        'types' => ['PRIMAIRE', 'PRIMAIRE'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['types.0']);
});

test('a complex requires its base name and rejects unknown fields', function (): void {
    $token = complexeToken();

    $this->withToken($token)->postJson('/api/v1/etablissements/complexe', [
        'types' => ['PRIMAIRE'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['nom_base']);

    $this->withToken($token)->postJson('/api/v1/etablissements/complexe', [
        'nom_base' => 'Royal School Academy',
        'types' => ['PRIMAIRE'],
        'type_etablissement' => 'PRIMAIRE',
    ])->assertUnprocessable()->assertJsonValidationErrors(['type_etablissement']);
});

test('a complex refuses levels that already exist in the tenant', function (): void {
    $tenant = createTenantForComplexe('complexe-duplicate');
    $admin = complexAdmin($tenant);
    Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Ecole Primaire Kamanyola',
        'type_etablissement' => 'PRIMAIRE',
    ]);

    $this->withToken(tokenForComplexe($admin))->postJson('/api/v1/etablissements/complexe', [
        'nom_base' => 'Royal School Academy',
        'types' => ['MATERNELLE', 'PRIMAIRE'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['types']);

    // Rien n'est créé : l'opération est atomique.
    expect(Etablissement::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('a non-administrator cannot create a complex', function (): void {
    $tenant = createTenantForComplexe('complexe-forbidden');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École A', 'type_etablissement' => 'PRIMAIRE']);
    $role = Role::query()->firstOrCreate(
        ['tenant_id' => null, 'name' => 'GESTIONNAIRE_ECOLE'],
        ['code' => 'GESTIONNAIRE_ECOLE', 'nom' => 'Gestionnaire', 'guard_name' => 'web', 'system_role' => true],
    );
    $manager = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $school->id,
        'nom' => 'Gestionnaire',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);
    $manager->assignRole($role);

    $this->withToken(tokenForComplexe($manager))->postJson('/api/v1/etablissements/complexe', [
        'nom_base' => 'Royal School Academy',
        'types' => ['PRIMAIRE'],
    ])->assertForbidden();

    expect(Etablissement::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});
