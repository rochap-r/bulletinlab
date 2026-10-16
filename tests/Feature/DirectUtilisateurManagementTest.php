<?php

use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createTenantForDirectUsers(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
}

function globalRoleForDirectUsers(string $code): Role
{
    return Role::query()->firstOrCreate(
        ['tenant_id' => null, 'code' => $code],
        ['nom' => Str::headline($code), 'system_role' => true],
    );
}

function userForDirectUsers(Tenant $tenant, string $role, ?Etablissement $etablissement = null): Utilisateur
{
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement?->id,
        'nom' => 'Acteur',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);
    $user->roles()->attach(globalRoleForDirectUsers($role));

    return $user;
}

function tokenForDirectUsers(Utilisateur $user): string
{
    return $user->createToken('direct-users', ["tenant:{$user->tenant_id}"])->plainTextToken;
}

test('a tenant administrator creates schools and managers with a one-time password', function (): void {
    $tenant = createTenantForDirectUsers('direct-admin');
    $admin = userForDirectUsers($tenant, 'ADMIN_ECOLE');
    globalRoleForDirectUsers('GESTIONNAIRE_ECOLE');

    $primary = $this->withToken(tokenForDirectUsers($admin))->postJson('/api/v1/etablissements', [
        'nom' => 'École Primaire A',
        'type_etablissement' => 'PRIMAIRE',
        'code_interne' => 'PRI-A',
    ])->assertCreated()->json('data');

    $secondary = $this->withToken(tokenForDirectUsers($admin))->postJson('/api/v1/etablissements', [
        'nom' => 'Institut B',
        'type_etablissement' => 'SECONDAIRE',
        'code_interne' => 'SEC-B',
    ])->assertCreated()->json('data');

    foreach ([[$primary['id'], '+243811000101'], [$secondary['id'], '+243811000102']] as [$schoolId, $phone]) {
        $response = $this->withToken(tokenForDirectUsers($admin))->postJson('/api/v1/utilisateurs', [
            'etablissement_id' => $schoolId,
            'nom' => 'Gestionnaire',
            'telephone' => $phone,
            'role' => 'GESTIONNAIRE_ECOLE',
        ])->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.utilisateur.doit_changer_mot_de_passe', true);

        $temporaryPassword = $response->json('data.mot_de_passe_temporaire');
        expect($temporaryPassword)->toBeString()->and(Str::length($temporaryPassword))->toBe(12);

        $created = Utilisateur::query()->where('telephone_normalise', $phone)->firstOrFail();
        expect(Hash::check($temporaryPassword, $created->mot_de_passe))->toBeTrue()
            ->and($created->doit_changer_mot_de_passe)->toBeTrue();
    }

    expect(Etablissement::query()->where('tenant_id', $tenant->id)->count())->toBe(2);
    $this->assertDatabaseCount('audit_logs', 4);
    expect(collect(DB::table('audit_logs')->pluck('nouvelles_valeurs'))->join(' '))->not->toContain('mot_de_passe');
});

test('a school manager creates teachers only in their own school', function (): void {
    $tenant = createTenantForDirectUsers('direct-manager');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École A', 'type_etablissement' => 'PRIMAIRE']);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    globalRoleForDirectUsers('ENSEIGNANT');

    $this->withToken(tokenForDirectUsers($manager))
        ->getJson('/api/v1/etablissements')
        ->assertForbidden();

    $this->withToken(tokenForDirectUsers($manager))->postJson('/api/v1/utilisateurs', [
        'nom' => 'Enseignant A',
        'telephone' => '+243811000201',
        'role' => 'ENSEIGNANT',
    ])->assertCreated()->assertJsonPath('data.utilisateur.etablissement.id', $school->id);

    $this->withToken(tokenForDirectUsers($manager))->postJson('/api/v1/utilisateurs', [
        'etablissement_id' => $school->id,
        'nom' => 'Contexte forgé',
        'telephone' => '+243811000202',
        'role' => 'ENSEIGNANT',
    ])->assertUnprocessable()->assertJsonValidationErrors(['etablissement_id']);

    $this->withToken(tokenForDirectUsers($manager))->postJson('/api/v1/utilisateurs', [
        'nom' => 'Gestionnaire interdit',
        'telephone' => '+243811000203',
        'role' => 'GESTIONNAIRE_ECOLE',
    ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
});

test('an administrator cannot select an establishment from another tenant', function (): void {
    $tenant = createTenantForDirectUsers('direct-isolation-a');
    $otherTenant = createTenantForDirectUsers('direct-isolation-b');
    $admin = userForDirectUsers($tenant, 'ADMIN_ECOLE');
    globalRoleForDirectUsers('GESTIONNAIRE_ECOLE');
    $foreignSchool = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $otherTenant->id, 'nom' => 'École étrangère']);

    $this->withToken(tokenForDirectUsers($admin))->postJson('/api/v1/utilisateurs', [
        'etablissement_id' => $foreignSchool->id,
        'nom' => 'Intrus',
        'telephone' => '+243811000301',
        'role' => 'GESTIONNAIRE_ECOLE',
    ])->assertUnprocessable()->assertJsonValidationErrors(['etablissement_id']);

    $this->assertDatabaseMissing('utilisateurs', ['telephone_normalise' => '+243811000301']);
});

test('a duplicate phone in the tenant returns an actionable validation error', function (): void {
    $tenant = createTenantForDirectUsers('direct-duplicate');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École']);
    $admin = userForDirectUsers($tenant, 'ADMIN_ECOLE');
    globalRoleForDirectUsers('ENSEIGNANT');
    userForDirectUsers($tenant, 'ENSEIGNANT', $school)->update(['telephone' => '+243811000401']);

    $this->withToken(tokenForDirectUsers($admin))->postJson('/api/v1/utilisateurs', [
        'etablissement_id' => $school->id,
        'nom' => 'Doublon',
        'telephone' => '+243811000401',
        'role' => 'ENSEIGNANT',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['telephone'])
        ->assertJsonPath('errors.telephone.0', 'Ce numéro appartient déjà à cet espace. Rattachez un rôle au compte existant.');
});
