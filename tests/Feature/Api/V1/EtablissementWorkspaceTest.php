<?php

use App\Models\Etablissement;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createTenantForWorkspace(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
}

function workspaceRole(string $code): Role
{
    return Role::query()->firstOrCreate(
        ['tenant_id' => null, 'name' => $code],
        ['code' => $code, 'nom' => Str::headline($code), 'guard_name' => 'web', 'system_role' => true],
    );
}

function workspaceUser(Tenant $tenant, string $role, ?Etablissement $etablissement = null): Utilisateur
{
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement?->id,
        'nom' => 'Acteur',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);
    $user->assignRole(workspaceRole($role));

    return $user;
}

function workspaceSchool(Tenant $tenant, string $nom, string $type): Etablissement
{
    return Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => $nom,
        'type_etablissement' => $type,
    ]);
}

function workspaceToken(Utilisateur $user, array $headers = []): array
{
    return array_merge(['Authorization' => 'Bearer '.$user->createToken('workspace', ["tenant:{$user->tenant_id}"])->plainTextToken], $headers);
}

test('an administrator selects a school via header and sees only its invitations', function (): void {
    $tenant = createTenantForWorkspace('workspace-scope');
    $schoolA = workspaceSchool($tenant, 'École A', 'PRIMAIRE');
    $schoolB = workspaceSchool($tenant, 'École B', 'SECONDAIRE');
    $admin = workspaceUser($tenant, 'ADMIN_TENANT');
    workspaceRole('ENSEIGNANT');

    foreach ([[$schoolA, 'a@test.test'], [$schoolB, 'b@test.test']] as [$school, $email]) {
        Invitation::query()->create([
            'tenant_id' => $tenant->id,
            'etablissement_id' => $school->id,
            'email' => $email,
            'role_id' => workspaceRole('ENSEIGNANT')->id,
            'token_hash' => hash('sha256', $email),
            'expire_at' => now()->addDays(7),
            'created_by' => $admin->id,
        ]);
    }

    $this->withHeaders(workspaceToken($admin, ['X-Etablissement' => (string) $schoolA->id]))
        ->getJson('/api/v1/invitations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'a@test.test');
});

test('an administrator without school selection keeps the previous behavior', function (): void {
    $tenant = createTenantForWorkspace('workspace-noscope');
    $admin = workspaceUser($tenant, 'ADMIN_TENANT');

    $this->withHeaders(workspaceToken($admin))
        ->getJson('/api/v1/invitations')
        ->assertNotFound();
});

test('a non-administrator cannot forge another school context', function (): void {
    $tenant = createTenantForWorkspace('workspace-forge');
    $schoolA = workspaceSchool($tenant, 'École A', 'PRIMAIRE');
    $schoolB = workspaceSchool($tenant, 'École B', 'SECONDAIRE');
    $manager = workspaceUser($tenant, 'GESTIONNAIRE_ECOLE', $schoolA);

    $this->withHeaders(workspaceToken($manager, ['X-Etablissement' => (string) $schoolB->id]))
        ->getJson('/api/v1/invitations')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['etablissement_id']);
});

test('a non-administrator keeps working when the header matches their own school', function (): void {
    $tenant = createTenantForWorkspace('workspace-self');
    $school = workspaceSchool($tenant, 'École A', 'PRIMAIRE');
    $manager = workspaceUser($tenant, 'GESTIONNAIRE_ECOLE', $school);

    $this->withHeaders(workspaceToken($manager, ['X-Etablissement' => (string) $school->id]))
        ->getJson('/api/v1/referentiels')
        ->assertOk()
        ->assertJsonStructure(['data' => ['annees_scolaires', 'cycles', 'modeles']]);
});

test('an invalid school header is rejected', function (): void {
    $tenant = createTenantForWorkspace('workspace-invalid');
    workspaceSchool($tenant, 'École A', 'PRIMAIRE');
    $admin = workspaceUser($tenant, 'ADMIN_TENANT');

    $this->withHeaders(workspaceToken($admin, ['X-Etablissement' => 'abc']))
        ->getJson('/api/v1/invitations')
        ->assertUnprocessable();

    $this->withHeaders(workspaceToken($admin, ['X-Etablissement' => '999999']))
        ->getJson('/api/v1/invitations')
        ->assertNotFound();
});

test('a school workspace exposes its detail, members and statistics', function (): void {
    $tenant = createTenantForWorkspace('workspace-detail');
    $school = workspaceSchool($tenant, 'École A', 'PRIMAIRE');
    $other = workspaceSchool($tenant, 'École B', 'SECONDAIRE');
    $admin = workspaceUser($tenant, 'ADMIN_TENANT');
    $manager = workspaceUser($tenant, 'GESTIONNAIRE_ECOLE', $school);
    $teacher = workspaceUser($tenant, 'ENSEIGNANT', $school);
    $teacher->update(['nom' => 'Enseignant', 'telephone' => '+243811000777']);

    $this->withHeaders(workspaceToken($admin))
        ->getJson("/api/v1/etablissements/{$school->public_id}")
        ->assertOk()
        ->assertJsonPath('data.nom', 'École A')
        ->assertJsonPath('data.type_etablissement', 'PRIMAIRE');

    $this->withHeaders(workspaceToken($admin))
        ->getJson("/api/v1/etablissements/{$school->public_id}/membres")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.nom', 'Enseignant')
        ->assertJsonPath('data.1.roles.0', 'ENSEIGNANT');

    $this->withHeaders(workspaceToken($admin))
        ->getJson("/api/v1/etablissements/{$school->public_id}/statistiques")
        ->assertOk()
        ->assertJsonPath('data.classes', 0)
        ->assertJsonPath('data.eleves', 0)
        ->assertJsonPath('data.enseignants', 1);

    // Le gestionnaire voit son école mais pas celle d'à côté.
    auth()->forgetGuards();

    $this->withHeaders(workspaceToken($manager))
        ->getJson("/api/v1/etablissements/{$school->public_id}/membres")
        ->assertOk();

    auth()->forgetGuards();

    $this->withHeaders(workspaceToken($manager))
        ->getJson("/api/v1/etablissements/{$other->public_id}/membres")
        ->assertForbidden();

    auth()->forgetGuards();

    // Un enseignant ne liste pas les membres.
    $this->withHeaders(workspaceToken($teacher))
        ->getJson("/api/v1/etablissements/{$school->public_id}/membres")
        ->assertForbidden();

    auth()->forgetGuards();

    auth()->forgetGuards();

    // École inconnue, identifiant numérique devinable ou d'un autre espace.
    $this->withHeaders(workspaceToken($admin))
        ->getJson('/api/v1/etablissements/999999')
        ->assertNotFound();

    $this->withHeaders(workspaceToken($admin))
        ->getJson("/api/v1/etablissements/{$school->id}")
        ->assertNotFound();

    auth()->forgetGuards();

    $foreign = createTenantForWorkspace('workspace-foreign');
    $foreignSchool = workspaceSchool($foreign, 'École X', 'PRIMAIRE');

    $this->withHeaders(workspaceToken($admin))
        ->getJson("/api/v1/etablissements/{$foreignSchool->public_id}")
        ->assertNotFound();
});
