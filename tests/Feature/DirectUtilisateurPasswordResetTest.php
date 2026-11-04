<?php

use App\Models\Etablissement;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('a tenant administrator can reset a manager password to the default', function (): void {
    $tenant = createTenantForDirectUsers('reset-admin');
    $admin = userForDirectUsers($tenant, 'ADMIN_ECOLE');
    globalRoleForDirectUsers('GESTIONNAIRE_ECOLE');

    $school = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'École A',
        'type_etablissement' => 'PRIMAIRE',
    ]);

    $created = $this->withToken(tokenForDirectUsers($admin))->postJson('/api/v1/utilisateurs', [
        'etablissement_id' => $school->id,
        'nom' => 'Gestionnaire',
        'telephone' => '+243811000501',
        'role' => 'GESTIONNAIRE_ECOLE',
    ])->assertCreated()->json('data.utilisateur');

    $response = $this->withToken(tokenForDirectUsers($admin))
        ->postJson("/api/v1/utilisateurs/{$created['id']}/reset-password")
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $response->assertJsonPath('data.mot_de_passe_temporaire', '123456')
        ->assertJsonPath('data.doit_changer_mot_de_passe', true)
        ->assertJsonPath('data.telephone', '+243811000501');

    $user = Utilisateur::query()->where('telephone_normalise', '+243811000501')->firstOrFail();
    expect(Hash::check('123456', $user->mot_de_passe))->toBeTrue()
        ->and($user->doit_changer_mot_de_passe)->toBeTrue();
});

test('a school manager can reset a teacher password', function (): void {
    $tenant = createTenantForDirectUsers('reset-manager');
    $school = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'École B',
        'type_etablissement' => 'PRIMAIRE',
    ]);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    globalRoleForDirectUsers('ENSEIGNANT');

    $created = $this->withToken(tokenForDirectUsers($manager))->postJson('/api/v1/utilisateurs', [
        'nom' => 'Enseignant',
        'telephone' => '+243811000502',
        'role' => 'ENSEIGNANT',
    ])->assertCreated()->json('data.utilisateur');

    $this->withToken(tokenForDirectUsers($manager))
        ->postJson("/api/v1/utilisateurs/{$created['id']}/reset-password")
        ->assertOk()
        ->assertJsonPath('data.mot_de_passe_temporaire', '123456')
        ->assertJsonPath('data.doit_changer_mot_de_passe', true);

    $user = Utilisateur::query()->where('telephone_normalise', '+243811000502')->firstOrFail();
    expect(Hash::check('123456', $user->mot_de_passe))->toBeTrue()
        ->and($user->doit_changer_mot_de_passe)->toBeTrue();
});

test('a manager cannot reset another administrator password', function (): void {
    $tenant = createTenantForDirectUsers('reset-manager-cannot-admin');
    $school = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'École C',
        'type_etablissement' => 'PRIMAIRE',
    ]);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    globalRoleForDirectUsers('ADMIN_ECOLE');

    $admin = userForDirectUsers($tenant, 'ADMIN_ECOLE', $school);

    $this->withToken(tokenForDirectUsers($manager))
        ->postJson("/api/v1/utilisateurs/{$admin->id}/reset-password")
        ->assertUnprocessable();
});

test('cannot reset a user password from another tenant', function (): void {
    $tenant = createTenantForDirectUsers('reset-isolation');
    $otherTenant = createTenantForDirectUsers('reset-isolation-other');
    $admin = userForDirectUsers($tenant, 'ADMIN_ECOLE');
    globalRoleForDirectUsers('GESTIONNAIRE_ECOLE');

    $foreignUser = userForDirectUsers($otherTenant, 'ENSEIGNANT');

    $this->withToken(tokenForDirectUsers($admin))
        ->postJson("/api/v1/utilisateurs/{$foreignUser->id}/reset-password")
        ->assertUnprocessable();
});

test('an administrator cannot reset their own password via the reset endpoint', function (): void {
    $tenant = createTenantForDirectUsers('reset-self');
    $admin = userForDirectUsers($tenant, 'ADMIN_ECOLE');
    globalRoleForDirectUsers('GESTIONNAIRE_ECOLE');

    $this->withToken(tokenForDirectUsers($admin))
        ->postJson("/api/v1/utilisateurs/{$admin->id}/reset-password")
        ->assertUnprocessable();
});

test('reset password writes an audit log', function (): void {
    $tenant = createTenantForDirectUsers('reset-audit');
    $school = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'École D',
        'type_etablissement' => 'PRIMAIRE',
    ]);
    $admin = userForDirectUsers($tenant, 'ADMIN_ECOLE', $school);
    globalRoleForDirectUsers('ENSEIGNANT');

    $target = userForDirectUsers($tenant, 'ENSEIGNANT', $school);

    $this->withToken(tokenForDirectUsers($admin))
        ->postJson("/api/v1/utilisateurs/{$target->id}/reset-password")
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'MDP_REINITIALISE_PAR_ADMIN',
        'utilisateur_id' => $admin->id,
        'ligne_cible_id' => $target->id,
    ]);
});
