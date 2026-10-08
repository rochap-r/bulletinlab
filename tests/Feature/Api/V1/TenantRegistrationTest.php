<?php

use App\Models\Etablissement;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    cache()->flush();
});

function tenantRegistrationPayloadForTenantTests(array $overrides = []): array
{
    return [
        'nom_espace' => 'Complexe Scolaire Lumière',
        'type_tenant' => 'ECOLE',
        'etablissement_nom' => 'Institut Lumière',
        'etablissement_code_ministere' => 'MIN-2026-001',
        'etablissement_province_educationnelle' => 'Kinshasa, éducation',
        'etablissement_ville' => 'Kinshasa',
        'etablissement_commune_territoire' => 'Gombe',
        'etablissement_telephone' => '+243810000001',
        'etablissement_email' => 'contact@institut-lumiere.test',
        'admin_nom' => 'Mbala',
        'admin_postnom' => 'Kasongo',
        'admin_prenom' => 'Grâce',
        'admin_email' => 'admin.lumiere@example.test',
        'admin_telephone' => '+243811000001',
        'mot_de_passe' => 'secret-password',
        ...$overrides,
    ];
}

test('a tenant can self-register and is created in pending status', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests())
        ->assertCreated()
        ->assertJsonPath('data.tenant.nom_espace', 'Complexe Scolaire Lumière')
        ->assertJsonPath('data.tenant.type_tenant', 'ECOLE')
        ->assertJsonPath('data.tenant.statut', 'EN_ATTENTE')
        ->assertJsonPath('data.etablissement.nom', 'Institut Lumière')
        ->assertJsonPath('data.administrateur.email', 'admin.lumiere@example.test');

    $this->assertDatabaseHas('tenants', [
        'nom_espace' => 'Complexe Scolaire Lumière',
        'slug' => 'complexe-scolaire-lumiere',
        'statut' => 'EN_ATTENTE',
    ]);

    $tenant = Tenant::query()->where('slug', 'complexe-scolaire-lumiere')->firstOrFail();

    $this->assertDatabaseHas('etablissements', [
        'tenant_id' => $tenant->id,
        'nom' => 'Institut Lumière',
        'ville' => 'Kinshasa',
        'actif' => true,
    ]);

    $this->assertDatabaseHas('utilisateurs', [
        'tenant_id' => $tenant->id,
        'email' => 'admin.lumiere@example.test',
        'telephone' => '+243811000001',
        'actif' => true,
    ]);

    $utilisateur = Utilisateur::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $this->assertTrue($utilisateur->roles()->where('code', 'ADMIN_ECOLE')->exists());

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'action' => 'TENANT_INSCRIT_EN_ATTENTE',
    ]);
});

test('a registered tenant slug is disambiguated with a numeric suffix', function () {
    Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Complexe Scolaire Lumière',
        'slug' => 'complexe-scolaire-lumiere',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests())
        ->assertCreated()
        ->assertJsonPath('data.tenant.slug', 'complexe-scolaire-lumiere-1');
});

test('the tenant name is required for self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'nom_espace' => '',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['nom_espace']);
});

test('the tenant type must be a known type for self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'type_tenant' => 'UNIVERSITE',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['type_tenant']);
});

test('the establishment name is required for self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'etablissement_nom' => '',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['etablissement_nom']);
});

test('the administrator email must be valid for self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'admin_email' => 'not-an-email',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['admin_email']);
});

test('a weak password is rejected during self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'mot_de_passe' => 'short',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe']);
});

test('an unknown field is rejected during self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'quota_eleves' => 999,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['quota_eleves']);
});

test('self-registration does not expose the administrateur role on the first establishment', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests())
        ->assertCreated();

    $tenant = Tenant::query()->where('slug', 'complexe-scolaire-lumiere')->firstOrFail();

    expect(Etablissement::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});
