<?php

use App\Models\Etablissement;
use App\Models\Role;
use App\Models\RolePlateforme;
use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

if (! function_exists('makePlatformTenantAdminToken')) {
    function makePlatformTenantAdminToken(): string
    {
        RolePlateforme::query()->updateOrCreate(
            ['code' => 'ADMIN_PLATEFORME'],
            ['nom' => 'Administrateur plateforme'],
        );

        $utilisateur = UtilisateurPlateforme::factory()->create([
            'email' => 'tenants-admin@plateforme.test',
            'mot_de_passe' => Hash::make('secret-password'),
            'actif' => true,
        ]);

        $utilisateur->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);

        return $utilisateur->createToken('tenants-test', ['context:platform'])->plainTextToken;
    }
}

test('the platform can list tenants with their establishment count', function () {
    $token = makePlatformTenantAdminToken();
    Tenant::query()->create([
        'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'nom_espace' => 'Groupe scolaire Alpha',
        'slug' => 'groupe-scolaire-alpha',
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
    $tenant = Tenant::query()->create([
        'uuid' => '550e8400-e29b-41d4-a716-446655440001',
        'nom_espace' => 'Complexe Béta',
        'slug' => 'complexe-beta',
        'type_tenant' => 'ECOLE',
        'statut' => 'ESSAI',
    ]);
    Etablissement::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'École primaire Béta 1',
        'actif' => true,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/platform/tenants')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.nom_espace', 'Complexe Béta')
        ->assertJsonPath('data.0.etablissements_count', 1)
        ->assertJsonStructure([
            'data' => [[
                'id', 'uuid', 'nom_espace', 'slug', 'type_tenant', 'statut', 'etablissements_count',
            ]],
        ]);
});

test('the platform can create a tenant in trial status by default', function () {
    $token = makePlatformTenantAdminToken();

    $this->withToken($token)
        ->postJson('/api/v1/platform/tenants', [
            'nom_espace' => 'Groupe scolaire Gamma',
            'type_tenant' => 'GROUPE_ECOLES',
        ])
        ->assertCreated()
        ->assertJsonPath('data.nom_espace', 'Groupe scolaire Gamma')
        ->assertJsonPath('data.type_tenant', 'GROUPE_ECOLES')
        ->assertJsonPath('data.statut', 'ESSAI')
        ->assertJsonPath('data.etablissements_count', 0);

    $this->assertDatabaseHas('tenants', [
        'nom_espace' => 'Groupe scolaire Gamma',
        'slug' => 'groupe-scolaire-gamma',
        'statut' => 'ESSAI',
    ]);
});

test('duplicate slugs are disambiguated with a numeric suffix', function () {
    $token = makePlatformTenantAdminToken();
    Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Groupe scolaire Gamma',
        'slug' => 'groupe-scolaire-gamma',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/platform/tenants', [
            'nom_espace' => 'Groupe scolaire Gamma',
            'type_tenant' => 'ECOLE',
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'groupe-scolaire-gamma-1');
});

test('a tenant type is required and must be a known type', function () {
    $token = makePlatformTenantAdminToken();

    $this->withToken($token)
        ->postJson('/api/v1/platform/tenants', [
            'nom_espace' => 'Complexe Delta',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type_tenant']);

    $this->withToken($token)
        ->postJson('/api/v1/platform/tenants', [
            'nom_espace' => 'Complexe Delta',
            'type_tenant' => 'UNIVERSITE',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type_tenant']);
});

test('an unknown tenant field is rejected', function () {
    $token = makePlatformTenantAdminToken();

    $this->withToken($token)
        ->postJson('/api/v1/platform/tenants', [
            'nom_espace' => 'Complexe Delta',
            'type_tenant' => 'ECOLE',
            'quota_max' => 99,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quota_max']);
});

test('a tenant can be updated', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Epsilon',
        'slug' => 'complexe-epsilon',
        'type_tenant' => 'ECOLE',
        'statut' => 'ESSAI',
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/tenants/{$tenant->id}", [
            'nom_espace' => 'Complexe Epsilon Révisé',
            'type_tenant' => 'GROUPE_ECOLES',
            'statut' => 'SUSPENDU',
            'espace_parents_actif' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.nom_espace', 'Complexe Epsilon Révisé')
        ->assertJsonPath('data.type_tenant', 'GROUPE_ECOLES')
        ->assertJsonPath('data.statut', 'SUSPENDU')
        ->assertJsonPath('data.espace_parents_actif', false);

    expect($tenant->fresh()->espace_parents_actif)->toBeFalse();
});

test('updating a tenant cannot bypass the validation flow', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Bypass',
        'slug' => 'complexe-bypass',
        'type_tenant' => 'ECOLE',
        'statut' => 'ESSAI',
    ]);

    foreach (['EN_ATTENTE', 'ACTIF', 'REFUSE'] as $statut) {
        $this->withToken($token)
            ->putJson("/api/v1/platform/tenants/{$tenant->id}", [
                'nom_espace' => 'Complexe Bypass',
                'type_tenant' => 'ECOLE',
                'statut' => $statut,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statut']);
    }

    expect($tenant->fresh()->statut)->toBe('ESSAI');
});

test('a tenant detail returns its establishments', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Zéta',
        'slug' => 'complexe-zeta',
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
    Etablissement::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Collège Zéta A',
        'ville' => 'Kinshasa',
        'actif' => true,
    ]);
    Etablissement::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'École primaire Zéta B',
        'ville' => 'Kinshasa',
        'actif' => true,
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/platform/tenants/{$tenant->id}")
        ->assertOk()
        ->assertJsonPath('data.nom_espace', 'Complexe Zéta')
        ->assertJsonPath('data.etablissements.0.nom', 'Collège Zéta A')
        ->assertJsonPath('data.etablissements_count', 2);
});

test('the platform can create an establishment under a tenant', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Êta',
        'slug' => 'complexe-eta',
        'type_tenant' => 'ECOLE',
        'statut' => 'ESSAI',
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/etablissements", [
            'nom' => 'Institution Êta',
            'code_ministere' => 'MIN-001',
            'province' => 'Kinshasa',
            'ville' => 'Kinshasa',
            'telephone' => '+243800000001',
        ])
        ->assertCreated()
        ->assertJsonPath('data.nom', 'Institution Êta')
        ->assertJsonPath('data.tenant_id', $tenant->id)
        ->assertJsonPath('data.actif', true)
        ->assertJsonStructure(['data' => ['id', 'public_id', 'tenant_id', 'nom']]);

    $this->assertDatabaseHas('etablissements', [
        'tenant_id' => $tenant->id,
        'nom' => 'Institution Êta',
        'code_ministere' => 'MIN-001',
        'actif' => true,
    ]);
});

test('an establishment can be renamed and disabled', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Thêta',
        'slug' => 'complexe-theta',
        'type_tenant' => 'ECOLE',
        'statut' => 'ESSAI',
    ]);
    $etablissement = Etablissement::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'École Thêta',
        'actif' => true,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/etablissements/{$etablissement->id}", [
            'nom' => 'École Thêta Bilingue',
            'actif' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.nom', 'École Thêta Bilingue')
        ->assertJsonPath('data.actif', false);

    $this->assertDatabaseHas('etablissements', [
        'id' => $etablissement->id,
        'actif' => false,
    ]);
});

test('an establishment requires a name', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Iota',
        'slug' => 'complexe-iota',
        'type_tenant' => 'ECOLE',
        'statut' => 'ESSAI',
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/etablissements", [
            'ville' => 'Kinshasa',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nom']);
});

test('the tenant endpoints require a platform context', function () {
    $this->getJson('/api/v1/platform/tenants')
        ->assertUnauthorized();

    $this->postJson('/api/v1/platform/tenants', [
        'nom_espace' => 'Sans accès',
        'type_tenant' => 'ECOLE',
    ])->assertUnauthorized();
});

if (! function_exists('createPendingTenantForAccessTests')) {
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
}

test('the platform can activate a pending tenant', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = createPendingTenantForAccessTests();

    $response = $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/activation")
        ->assertOk()
        ->assertJsonPath('data.statut', 'ACTIF')
        ->assertJsonPath('data.motif_refus', null);

    expect($response->json('data.valide_at'))->not->toBeNull();

    $fresh = $tenant->fresh();
    expect($fresh->statut)->toBe('ACTIF');
    expect($fresh->valide_at)->not->toBeNull();
    expect($fresh->valide_par_id)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'action' => 'TENANT_VALIDE',
    ]);
});

test('activating a non-pending tenant conflicts', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Kappa',
        'slug' => 'complexe-kappa',
        'type_tenant' => 'ECOLE',
        'statut' => 'ESSAI',
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/activation")
        ->assertStatus(409);

    expect($tenant->fresh()->statut)->toBe('ESSAI');
});

test('the platform can refuse a pending tenant with a motif', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = createPendingTenantForAccessTests();

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/refus", [
            'motif' => 'Code ministériel invérifiable.',
        ])
        ->assertOk()
        ->assertJsonPath('data.statut', 'REFUSE')
        ->assertJsonPath('data.motif_refus', 'Code ministériel invérifiable.');

    $fresh = $tenant->fresh();
    expect($fresh->statut)->toBe('REFUSE');
    expect($fresh->motif_refus)->toBe('Code ministériel invérifiable.');

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'action' => 'TENANT_REFUSE',
    ]);
});

test('refusing a tenant requires a motif', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = createPendingTenantForAccessTests();

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/refus")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['motif']);
});

test('refusing a non-pending tenant conflicts', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Lambda',
        'slug' => 'complexe-lambda',
        'type_tenant' => 'ECOLE',
        'statut' => 'REFUSE',
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/refus", [
            'motif' => 'Nouveau motif',
        ])
        ->assertStatus(409);
});

test('the platform can filter tenants by status for the pending queue', function () {
    $token = makePlatformTenantAdminToken();
    createPendingTenantForAccessTests();
    Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Filtre Actif',
        'slug' => 'complexe-filtre-actif',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/platform/tenants?statut=EN_ATTENTE')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.statut', 'EN_ATTENTE');

    $this->withToken($token)
        ->getJson('/api/v1/platform/tenants?statut=INCONNU')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['statut']);
});

test('the platform can send a refused tenant back for review', function () {
    $token = makePlatformTenantAdminToken();
    $platformUserId = UtilisateurPlateforme::query()
        ->where('email', 'tenants-admin@plateforme.test')
        ->valueOrFail('id');
    $tenant = createPendingTenantForAccessTests('REFUSE');
    $tenant->update([
        'motif_refus' => 'Dossier incomplet.',
        'valide_at' => now(),
        'valide_par_id' => $platformUserId,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/reexamen")
        ->assertOk()
        ->assertJsonPath('data.statut', 'EN_ATTENTE')
        ->assertJsonPath('data.motif_refus', null);

    $fresh = $tenant->fresh();
    expect($fresh->statut)->toBe('EN_ATTENTE');
    expect($fresh->motif_refus)->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'action' => 'TENANT_REEXAMEN',
    ]);
});

test('reexamining a non-refused tenant conflicts', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = createPendingTenantForAccessTests();

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/reexamen")
        ->assertStatus(409);

    expect($tenant->fresh()->statut)->toBe('EN_ATTENTE');
});

test('a tenant detail exposes its administrator, validator and history', function () {
    $token = makePlatformTenantAdminToken();
    $tenant = createPendingTenantForAccessTests();
    $admin = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Directeur',
        'telephone' => '+243811000099',
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);
    $role = Role::query()->firstOrCreate(
        ['tenant_id' => null, 'name' => 'ADMIN_TENANT'],
        ['code' => 'ADMIN_TENANT', 'nom' => 'Administrateur tenant', 'guard_name' => 'web', 'system_role' => true],
    );
    $admin->assignRole($role);

    $this->withToken($token)
        ->postJson("/api/v1/platform/tenants/{$tenant->id}/activation")
        ->assertOk();

    $this->withToken($token)
        ->getJson("/api/v1/platform/tenants/{$tenant->id}")
        ->assertOk()
        ->assertJsonPath('data.administrateur.telephone', '+243811000099')
        ->assertJsonPath('data.administrateur.roles.0', 'ADMIN_TENANT')
        ->assertJsonPath('data.valide_par.email', 'tenants-admin@plateforme.test')
        ->assertJsonPath('data.audits.0.action', 'TENANT_VALIDE');
});
