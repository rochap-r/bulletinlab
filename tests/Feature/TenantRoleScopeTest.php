<?php

use App\Models\Etablissement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function scopeTenant(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
}

function scopeRole(string $code, ?int $tenantId = null): Role
{
    return Role::query()->firstOrCreate(
        ['tenant_id' => $tenantId, 'name' => $code],
        ['code' => $code, 'nom' => Str::headline($code), 'guard_name' => 'web', 'system_role' => $tenantId === null],
    );
}

function scopeUser(Tenant $tenant, string $role, ?Etablissement $etablissement = null): Utilisateur
{
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement?->id,
        'nom' => 'Acteur',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);
    $user->assignRole(scopeRole($role));

    return $user;
}

function scopeToken(Utilisateur $user): string
{
    return $user->createToken('scope', ["tenant:{$user->tenant_id}"])->plainTextToken;
}

test('spatie roles are verified within the tenant scope', function (): void {
    $tenantA = scopeTenant('scope-a');
    $tenantB = scopeTenant('scope-b');
    $adminA = scopeUser($tenantA, 'ADMIN_TENANT');

    // Rôle système global utilisable dans son tenant.
    expect($adminA->hasTenantRole('ADMIN_TENANT'))->toBeTrue()
        ->and($adminA->hasRole('ADMIN_TENANT'))->toBeTrue();

    // Rôle d'un autre tenant ignoré même si le pivot existe (défense en profondeur).
    $foreign = scopeRole('FOREIGN_ADMIN', $tenantB->id);
    $adminA->roles()->attach($foreign->getKey());

    expect($adminA->fresh()->hasTenantRole('FOREIGN_ADMIN'))->toBeFalse();
});

test('spatie permissions inherit the tenant scope', function (): void {
    $tenant = scopeTenant('scope-perm');
    $permission = Permission::findOrCreate('teachers.manage', 'web');
    $role = scopeRole('ADMIN_TENANT');
    $role->givePermissionTo($permission);

    $admin = scopeUser($tenant, 'ADMIN_TENANT');

    setPermissionsTeamId($tenant->getKey());

    expect($admin->hasTenantPermission('teachers.manage'))->toBeTrue()
        ->and($admin->hasPermissionTo('teachers.manage'))->toBeTrue();

    setPermissionsTeamId(null);
});

test('a manager cannot manage establishments outside its tenant scope', function (): void {
    $tenant = scopeTenant('scope-etab');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École A', 'type_etablissement' => 'PRIMAIRE']);
    $manager = scopeUser($tenant, 'GESTIONNAIRE_ECOLE', $school);
    $admin = scopeUser($tenant, 'ADMIN_TENANT');

    // Gestionnaire sans rôle admin : 403 sur la liste des écoles.
    $this->withToken(scopeToken($manager))->getJson('/api/v1/etablissements')->assertForbidden();

    // Le guard Sanctum met en cache l'utilisateur résolu entre deux requêtes
    // d'un même test : on purge pour que le 2e Bearer soit résolu à nouveau.
    app('auth')->forgetGuards();

    // Admin du même tenant : 200, preuve que le rôle Spatie + scope tenant autorise.
    $this->withToken(scopeToken($admin))->getJson('/api/v1/etablissements')->assertOk();
});
