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

function mtaTenant(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
}

function mtaRole(string $code): Role
{
    return Role::query()->firstOrCreate(
        ['tenant_id' => null, 'code' => $code],
        ['nom' => Str::headline($code), 'system_role' => true],
    );
}

function mtaSchool(Tenant $tenant, string $nom = 'École'): Etablissement
{
    return Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => $nom,
        'type_etablissement' => 'PRIMAIRE',
        'actif' => true,
    ]);
}

function mtaUser(Tenant $tenant, string $phone, string $role, ?Etablissement $school = null, bool $mustChange = false): Utilisateur
{
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $school?->id,
        'nom' => 'Enseignant',
        'telephone' => $phone,
        'mot_de_passe' => Hash::make('mot-de-passe-commun'),
        'doit_changer_mot_de_passe' => $mustChange,
        'actif' => true,
    ]);
    $user->roles()->attach(mtaRole($role));

    return $user;
}

function mtaAdmin(Tenant $tenant): Utilisateur
{
    $admin = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => null,
        'nom' => 'Admin',
        'telephone' => '+243899000001',
        'mot_de_passe' => Hash::make('admin-secret'),
        'actif' => true,
    ]);
    $admin->roles()->attach(mtaRole('ADMIN_TENANT'));

    return $admin;
}

function mtaToken(Utilisateur $user): string
{
    return $user->createToken('mta', ["tenant:{$user->tenant_id}"])->plainTextToken;
}

test('memberships list spaces for a known phone and stay empty for an unknown one', function (): void {
    $tenantA = mtaTenant('mta-espaces-a');
    $tenantB = mtaTenant('mta-espaces-b');
    mtaUser($tenantA, '+243831000001', 'ENSEIGNANT', mtaSchool($tenantA));
    mtaUser($tenantB, '+243831000001', 'ENSEIGNANT', mtaSchool($tenantB));

    $this->getJson('/api/v1/public/memberships?telephone='.urlencode('+243831000001'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'mta-espaces-a')
        ->assertJsonPath('data.1.roles', ['ENSEIGNANT']);

    $this->getJson('/api/v1/public/memberships?telephone='.urlencode('+243831000099'))
        ->assertOk()
        ->assertJsonPath('data', []);

    $this->getJson('/api/v1/public/memberships?telephone=0811000001')
        ->assertOk()->assertJsonPath('data', []);
});

test('login accepts a tenant id and stays generic on unknown tenant', function (): void {
    $tenant = mtaTenant('mta-login-id');
    mtaUser($tenant, '+243831000002', 'ENSEIGNANT', mtaSchool($tenant));

    $this->postJson('/api/v1/auth/login', [
        'tenant_id' => $tenant->id,
        'identifiant' => '+243831000002',
        'password' => 'mot-de-passe-commun',
    ])->assertOk()->assertJsonPath('data.user.telephone', '+243831000002');

    $this->postJson('/api/v1/auth/login', [
        'tenant_id' => 987654321,
        'identifiant' => '+243831000002',
        'password' => 'mot-de-passe-commun',
    ])->assertUnauthorized()->assertJsonPath('detail', 'Invalid credentials');

    $this->postJson('/api/v1/auth/login', [
        'identifiant' => '+243831000002',
        'password' => 'mot-de-passe-commun',
    ])->assertOk()->assertJsonPath('data.user.tenant.slug', 'mta-login-id');
});

test('switch issues a target token with the same account', function (): void {
    $tenantA = mtaTenant('mta-switch-a');
    $tenantB = mtaTenant('mta-switch-b');
    $userA = mtaUser($tenantA, '+243831000003', 'ENSEIGNANT', mtaSchool($tenantA));
    mtaUser($tenantB, '+243831000003', 'ENSEIGNANT', mtaSchool($tenantB));

    $switched = $this->withToken(mtaToken($userA))->postJson('/api/v1/auth/switch', [
        'tenant_id' => $tenantB->id,
    ])->assertOk()->assertJsonPath('data.user.telephone', '+243831000003');

    $this->app['auth']->forgetGuards();
    $this->withToken($switched->json('data.access_token'))->getJson('/api/v1/me')
        ->assertOk()->assertJsonPath('data.tenant.slug', 'mta-switch-b');

    $this->assertDatabaseHas('audit_logs', ['action' => 'TENANT_SWITCH', 'tenant_id' => $tenantB->id]);

    $this->withToken(mtaToken($userA))->postJson('/api/v1/auth/switch', [
        'tenant_id' => 987654321,
    ])->assertUnauthorized()->assertJsonPath('detail', 'Invalid credentials');
});

test('rattacher copies the password so both tenants share it', function (): void {
    $tenantA = mtaTenant('mta-ratt-a');
    $tenantB = mtaTenant('mta-ratt-b');
    mtaUser($tenantA, '+243831000004', 'ENSEIGNANT', mtaSchool($tenantA));
    $schoolB = mtaSchool($tenantB, 'École B');
    $adminB = mtaAdmin($tenantB);

    $response = $this->withToken(mtaToken($adminB))->postJson('/api/v1/utilisateurs/rattacher', [
        'telephone' => '+243831000004',
        'nom' => 'Enseignant',
        'role' => 'ENSEIGNANT',
        'etablissement_id' => $schoolB->id,
    ])->assertCreated()->assertJsonPath('data.compte_existant', true)
        ->assertJsonPath('data.compte_cree', true)
        ->assertJsonPath('data.utilisateur.doit_changer_mot_de_passe', false);

    expect($response->json('data.utilisateur.telephone'))->toBe('+243831000004');

    foreach ([$tenantA->slug, $tenantB->slug] as $slug) {
        $this->postJson('/api/v1/auth/login', [
            'tenant_slug' => $slug,
            'identifiant' => '+243831000004',
            'password' => 'mot-de-passe-commun',
        ])->assertOk();
    }

    $this->getJson('/api/v1/public/memberships?telephone='.urlencode('+243831000004'))
        ->assertOk()->assertJsonCount(2, 'data');

    $this->assertDatabaseHas('audit_logs', ['action' => 'UTILISATEUR_RATTACHE_EXISTANT', 'tenant_id' => $tenantB->id]);
});

test('rattacher inherits the forced change of a never-connected account', function (): void {
    $tenantA = mtaTenant('mta-ratt-first-a');
    $tenantB = mtaTenant('mta-ratt-first-b');
    mtaUser($tenantA, '+243831000005', 'ENSEIGNANT', mtaSchool($tenantA), true);
    $schoolB = mtaSchool($tenantB, 'École B');
    $adminB = mtaAdmin($tenantB);

    $this->withToken(mtaToken($adminB))->postJson('/api/v1/utilisateurs/rattacher', [
        'telephone' => '+243831000005',
        'nom' => 'Enseignant',
        'role' => 'ENSEIGNANT',
        'etablissement_id' => $schoolB->id,
    ])->assertCreated()->assertJsonPath('data.utilisateur.doit_changer_mot_de_passe', true);
});

test('rattacher refuses mismatched names and unknown phones', function (): void {
    $tenantA = mtaTenant('mta-ratt-garde-a');
    $tenantB = mtaTenant('mta-ratt-garde-b');
    mtaUser($tenantA, '+243831000006', 'ENSEIGNANT', mtaSchool($tenantA));
    $schoolB = mtaSchool($tenantB, 'École B');
    $adminB = mtaAdmin($tenantB);

    $this->withToken(mtaToken($adminB))->postJson('/api/v1/utilisateurs/rattacher', [
        'telephone' => '+243831000006',
        'nom' => 'Usurpateur',
        'role' => 'ENSEIGNANT',
        'etablissement_id' => $schoolB->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['nom']);

    $this->withToken(mtaToken($adminB))->postJson('/api/v1/utilisateurs/rattacher', [
        'telephone' => '+243831000098',
        'nom' => 'Inconnu',
        'role' => 'ENSEIGNANT',
        'etablissement_id' => $schoolB->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['telephone']);

    $this->assertDatabaseMissing('utilisateurs', ['tenant_id' => $tenantB->id, 'telephone_normalise' => '+243831000006']);
});

test('rattacher in the same space attaches the missing role instead', function (): void {
    $tenant = mtaTenant('mta-ratt-meme');
    $school = mtaSchool($tenant);
    mtaRole('ENSEIGNANT');
    $user = mtaUser($tenant, '+243831000007', 'TITULAIRE', $school);
    $admin = mtaAdmin($tenant);

    $this->withToken(mtaToken($admin))->postJson('/api/v1/utilisateurs/rattacher', [
        'telephone' => '+243831000007',
        'nom' => 'Enseignant',
        'role' => 'ENSEIGNANT',
        'etablissement_id' => $school->id,
    ])->assertOk()->assertJsonPath('data.compte_cree', false);

    expect($user->fresh()?->roles()->pluck('code')->sort()->values()->all())
        ->toBe(['ENSEIGNANT', 'TITULAIRE']);
    expect(Utilisateur::query()->where('tenant_id', $tenant->id)->where('telephone_normalise', '+243831000007')->count())->toBe(1);
});

test('rechercher exposes only a masked hint', function (): void {
    $tenantA = mtaTenant('mta-search-a');
    $tenantB = mtaTenant('mta-search-b');
    mtaUser($tenantA, '+243831000008', 'ENSEIGNANT', mtaSchool($tenantA));
    $adminB = mtaAdmin($tenantB);

    $this->withToken(mtaToken($adminB))->getJson('/api/v1/utilisateurs/rechercher?telephone='.urlencode('+243831000008'))
        ->assertOk()
        ->assertJsonPath('data.existe', true)
        ->assertJsonPath('data.dans_espace_courant', false)
        ->assertJsonPath('data.nom_masque', 'En***');

    $this->withToken(mtaToken($adminB))->getJson('/api/v1/utilisateurs/rechercher?telephone='.urlencode('+243831000097'))
        ->assertOk()->assertJsonPath('data.existe', false);
});
