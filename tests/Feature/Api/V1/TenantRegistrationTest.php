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
        'mot_de_passe' => 'SecurePassword1',
        'mot_de_passe_confirmation' => 'SecurePassword1',
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
        'telephone_normalise' => '+243811000001',
        'etablissement_id' => null,
        'actif' => true,
    ]);

    $utilisateur = Utilisateur::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $this->assertTrue($utilisateur->roles()->where('code', 'ADMIN_TENANT')->exists());

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'action' => 'TENANT_INSCRIT_EN_ATTENTE',
    ]);
});

test('the administrator phone is normalized to E.164 during self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'admin_telephone' => '00243 811 000 001',
    ]))->assertCreated();

    $this->assertDatabaseHas('utilisateurs', [
        'telephone' => '+243811000001',
        'telephone_normalise' => '+243811000001',
    ]);
});

test('a national trunk zero phone is normalized during self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'admin_telephone' => '0811000001',
    ]))->assertCreated();

    $this->assertDatabaseHas('utilisateurs', [
        'telephone' => '+243811000001',
        'telephone_normalise' => '+243811000001',
    ]);
});

test('a non E.164 administrator phone is rejected during self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'admin_telephone' => '0811',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['admin_telephone']);
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

test('a tenant can self-register without creating an establishment', function () {
    $payload = tenantRegistrationPayloadForTenantTests([
        'etablissement_nom' => null,
        'etablissement_code_ministere' => null,
        'etablissement_province_educationnelle' => null,
        'etablissement_ville' => null,
        'etablissement_commune_territoire' => null,
        'etablissement_telephone' => null,
        'etablissement_email' => null,
    ]);

    $this->postJson('/api/v1/public/tenants', $payload)
        ->assertCreated()
        ->assertJsonPath('data.etablissement', null);

    $tenant = Tenant::query()->where('slug', 'complexe-scolaire-lumiere')->firstOrFail();

    expect(Etablissement::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(Utilisateur::query()->where('tenant_id', $tenant->id)->value('etablissement_id'))->toBeNull();
});

test('orphan establishment fields are rejected without an establishment name', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'etablissement_nom' => null,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['etablissement_nom']);
});

test('the administrator email must be valid for self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'admin_email' => 'not-an-email',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['admin_email']);
});

test('the administrator email is optional for self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'admin_email' => null,
    ]))->assertCreated();
});

test('password confirmation is required for self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'mot_de_passe_confirmation' => 'different-password',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe']);
});

test('a weak password is rejected during self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'mot_de_passe' => 'short',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe']);
});

test('a six character password is rejected during self-registration', function () {
    $this->postJson('/api/v1/public/tenants', tenantRegistrationPayloadForTenantTests([
        'mot_de_passe' => 'abcdef',
        'mot_de_passe_confirmation' => 'abcdef',
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
