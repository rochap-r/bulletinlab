<?php

use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createPwdTenant(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
}

function pwdRole(string $code): Role
{
    return Role::query()->firstOrCreate(
        ['tenant_id' => null, 'code' => $code],
        ['nom' => Str::headline($code), 'system_role' => true],
    );
}

function createPwdUser(Tenant $tenant, string $phone, bool $mustChange, ?Etablissement $school = null): Utilisateur
{
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $school?->id,
        'nom' => 'Enseignant',
        'telephone' => $phone,
        'mot_de_passe' => Hash::make('ancien-mot-de-passe'),
        'doit_changer_mot_de_passe' => $mustChange,
        'actif' => true,
    ]);
    $user->roles()->attach(pwdRole('ENSEIGNANT'));

    return $user;
}

function pwdToken(Utilisateur $user): string
{
    return $user->createToken('pwd', ["tenant:{$user->tenant_id}"])->plainTextToken;
}

test('a forced user is blocked from business routes but keeps me and logout', function (): void {
    $tenant = createPwdTenant('pwd-blocked');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École', 'actif' => true]);
    $user = createPwdUser($tenant, '+243821000001', true, $school);
    $token = pwdToken($user);

    $this->withToken($token)->getJson('/api/v1/etablissements')->assertForbidden()
        ->assertJsonPath('detail', 'PASSWORD_CHANGE_REQUIRED');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.doit_changer_mot_de_passe', true);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
});

test('a forced user sets the initial password without the current one', function (): void {
    $tenant = createPwdTenant('pwd-initial');
    $user = createPwdUser($tenant, '+243821000010', true);
    $token = pwdToken($user);

    $this->withToken($token)->postJson('/api/v1/me/password-initial', [
        'mot_de_passe' => '123456',
        'mot_de_passe_confirmation' => '123456',
    ])->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe']);

    $this->withToken($token)->postJson('/api/v1/me/password-initial', [
        'mot_de_passe' => 'nouveau-mot-de-passe-sûr',
        'mot_de_passe_confirmation' => 'nouveau-mot-de-passe-sûr',
    ])->assertOk()->assertJsonPath('data.doit_changer_mot_de_passe', false);

    expect($user->fresh()?->doit_changer_mot_de_passe)->toBeFalse()
        ->and(Hash::check('nouveau-mot-de-passe-sûr', $user->fresh()?->mot_de_passe))->toBeTrue();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.doit_changer_mot_de_passe', false);
});

test('the initial password endpoint refuses users without a pending change', function (): void {
    $tenant = createPwdTenant('pwd-initial-refused');
    $user = createPwdUser($tenant, '+243821000011', false);
    $token = pwdToken($user);

    $this->withToken($token)->postJson('/api/v1/me/password-initial', [
        'mot_de_passe' => 'nouveau-mot-de-passe-sûr',
        'mot_de_passe_confirmation' => 'nouveau-mot-de-passe-sûr',
    ])->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe']);
});
test('a wrong current password is rejected', function (): void {
    $tenant = createPwdTenant('pwd-wrong');
    $user = createPwdUser($tenant, '+243821000002', true);
    $token = pwdToken($user);

    $this->withToken($token)->postJson('/api/v1/me/password', [
        'mot_de_passe_actuel' => 'mauvais-mot-de-passe',
        'mot_de_passe' => 'nouveau-mot-de-passe-sûr',
        'mot_de_passe_confirmation' => 'nouveau-mot-de-passe-sûr',
    ])->assertUnprocessable()->assertJsonValidationErrors(['mot_de_passe_actuel']);
});

test('a password change propagates to sibling tenants and unlocks business routes', function (): void {
    $tenantA = createPwdTenant('pwd-prop-a');
    $tenantB = createPwdTenant('pwd-prop-b');
    $schoolA = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenantA->id, 'nom' => 'École A', 'actif' => true]);
    Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenantB->id, 'nom' => 'École B', 'actif' => true]);

    $userA = createPwdUser($tenantA, '+243821000003', true, $schoolA);
    $userB = createPwdUser($tenantB, '+243821000003', false);

    $this->withToken(pwdToken($userA))->postJson('/api/v1/me/password', [
        'mot_de_passe_actuel' => 'ancien-mot-de-passe',
        'mot_de_passe' => 'nouveau-mot-de-passe-sûr',
        'mot_de_passe_confirmation' => 'nouveau-mot-de-passe-sûr',
    ])->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    expect(Hash::check('nouveau-mot-de-passe-sûr', $userB->fresh()?->mot_de_passe))->toBeTrue()
        ->and($userB->fresh()?->doit_changer_mot_de_passe)->toBeFalse()
        ->and($userA->fresh()?->doit_changer_mot_de_passe)->toBeFalse()
        ->and($userA->fresh()?->derniere_connexion_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', ['action' => 'MDP_CHANGE_FORCE', 'tenant_id' => $tenantA->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'MDP_CHANGE_FORCE', 'tenant_id' => $tenantB->id]);

    $this->app['auth']->forgetGuards();
    $this->withToken(pwdToken($userA->fresh()))->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.doit_changer_mot_de_passe', false);

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenantB->slug,
        'identifiant' => '+243821000003',
        'password' => 'nouveau-mot-de-passe-sûr',
    ])->assertOk();
});

test('a successful login stamps the last connection', function (): void {
    $tenant = createPwdTenant('pwd-stamp');
    $user = createPwdUser($tenant, '+243821000004', false);

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'identifiant' => '+243821000004',
        'password' => 'ancien-mot-de-passe',
    ])->assertOk();

    expect($user->fresh()?->derniere_connexion_at)->not->toBeNull();
});
