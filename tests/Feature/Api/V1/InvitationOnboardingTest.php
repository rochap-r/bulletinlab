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

beforeEach(function (): void {
    cache()->flush();
});

function createTenantForInvitationTests(array $attributes = []): Tenant
{
    return Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'Tenant Invitation',
        'slug' => 'invite-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
        ...$attributes,
    ]);
}

function createEstablishmentForInvitationTests(Tenant $tenant, array $attributes = []): Etablissement
{
    return Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Établissement Invitation',
        'actif' => true,
        ...$attributes,
    ]);
}

function createUserForInvitationTests(Tenant $tenant, array $attributes = []): Utilisateur
{
    return Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => null,
        'nom' => 'Invitation',
        'postnom' => 'User',
        'prenom' => 'Test',
        'email' => 'donor-'.Str::lower(Str::random(8)).'@example.test',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
        ...$attributes,
    ]);
}

function tokenForInvitationTests(Utilisateur $user, Tenant $tenant): string
{
    return $user->createToken(
        'invitation-suite',
        ["tenant:{$tenant->id}"],
    )->plainTextToken;
}

function grantInvitationManagementRole(Utilisateur $user, Tenant $tenant): void
{
    $role = Role::query()->firstOrCreate(
        ['tenant_id' => $tenant->id, 'code' => 'ADMIN_ECOLE'],
        ['nom' => 'Administrateur école', 'system_role' => false],
    );

    $user->roles()->syncWithoutDetaching($role);
}

function createManagerForInvitationTests(Tenant $tenant, Etablissement $establishment): Utilisateur
{
    $user = createUserForInvitationTests($tenant, [
        'etablissement_id' => $establishment->id,
    ]);

    grantInvitationManagementRole($user, $tenant);

    return $user;
}

function invitedRoleForInvitationTests(Tenant $tenant, string $code = 'PROFESSEUR'): Role
{
    return Role::query()->create([
        'tenant_id' => $tenant->id,
        'code' => $code,
        'nom' => 'Professeur',
        'system_role' => false,
    ]);
}

function createPendingInvitationForInvitationTests(
    Tenant $tenant,
    Etablissement $establishment,
    Role $role,
    array $attributes = [],
): array {
    $token = bin2hex(random_bytes(32));

    $invitation = Invitation::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'email' => 'invitee-'.Str::lower(Str::random(8)).'@example.test',
        'role_id' => $role->id,
        'token_hash' => hash('sha256', $token),
        'expire_at' => now()->addDays(7),
        'created_by' => null,
        ...$attributes,
    ]);

    return [$invitation, $token];
}

function acceptancePayloadForInvitationTests(string $token, array $overrides = []): array
{
    return [
        'token' => $token,
        'nom' => 'Mbala',
        'postnom' => 'Kasongo',
        'prenom' => 'Grâce',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => 'secret-password',
        ...$overrides,
    ];
}

test('an establishment manager creates an invitation with a one-time token', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    $response = $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->postJson('/api/v1/invitations', [
            'email' => 'Jonas.Kabongo@Example.Test',
            'role_id' => $role->id,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'jonas.kabongo@example.test')
        ->assertJsonPath('data.role.code', 'PROFESSEUR')
        ->assertJsonPath('data.role.nom', 'Professeur')
        ->assertJsonPath('data.statut', 'EN_ATTENTE')
        ->assertJsonStructure(['data' => ['id', 'email', 'token', 'expire_le']]);

    $token = $response->json('data.token');

    expect($token)->toBeString()
        ->and(strlen((string) $token))->toBe(64);

    $this->assertDatabaseHas('invitations', [
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'email' => 'jonas.kabongo@example.test',
        'role_id' => $role->id,
        'token_hash' => hash('sha256', $token),
    ]);

    $this->assertDatabaseMissing('invitations', ['token_hash' => $token]);

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'action' => 'INVITATION_ENVOYEE',
    ]);
});

test('the raw invitation token is never exposed again', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    $token = $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->postJson('/api/v1/invitations', [
            'email' => 'once@example.test',
            'role_id' => $role->id,
        ])
        ->json('data.token');

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->getJson('/api/v1/invitations')
        ->assertOk()
        ->assertJsonPath('data.0.statut', 'EN_ATTENTE')
        ->assertJsonMissingPath('data.0.token')
        ->assertJsonMissingPath('data.0.token_hash');
});

test('a duplicate active invitation for the same email conflicts', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    [$invitation] = createPendingInvitationForInvitationTests($tenant, $establishment, $role);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->postJson('/api/v1/invitations', [
            'email' => $invitation->email,
            'role_id' => $role->id,
        ])
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('an invitation can be created again once the previous one is revoked', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    [$revokedInvitation, $revokedToken] = createPendingInvitationForInvitationTests($tenant, $establishment, $role, [
        'revoked_at' => now()->subMinute(),
    ]);

    $response = $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->postJson('/api/v1/invitations', [
            'email' => $revokedInvitation->email,
            'role_id' => $role->id,
        ])
        ->assertCreated();

    $newToken = $response->json('data.token');

    expect($newToken)->not->toBe($revokedToken)
        ->and(Invitation::query()
            ->where('email', $revokedInvitation->email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->count())->toBe(1);
});

test('the super admin role cannot be invited', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    Role::query()->create([
        'tenant_id' => $tenant->id,
        'code' => 'SUPER_ADMIN',
        'nom' => 'Super administrateur',
        'system_role' => true,
    ]);

    $role = Role::query()->where('code', 'SUPER_ADMIN')->first();

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->postJson('/api/v1/invitations', [
            'email' => 'admin@example.test',
            'role_id' => $role->id,
        ])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('a role from another tenant is not resolvable', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $otherTenant = createTenantForInvitationTests();
    $foreignRole = invitedRoleForInvitationTests($otherTenant);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->postJson('/api/v1/invitations', [
            'email' => 'cross@example.test',
            'role_id' => $foreignRole->id,
        ])
        ->assertNotFound();
});

test('a non-manager member cannot create invitations', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);
    $teacher = createUserForInvitationTests($tenant, [
        'etablissement_id' => $establishment->id,
    ]);
    $teacher->roles()->attach($role);

    $this->withToken(tokenForInvitationTests($teacher, $tenant))
        ->postJson('/api/v1/invitations', [
            'email' => 'teacher@example.test',
            'role_id' => $role->id,
        ])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('invitation creation requires authentication', function () {
    $this->postJson('/api/v1/invitations', [
        'email' => 'anon@example.test',
        'role_id' => 1,
    ])->assertUnauthorized();
});

test('invitation creation rejects client managed context identifiers', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->postJson('/api/v1/invitations', [
            'tenant_id' => $tenant->id,
            'etablissement_id' => $establishment->id,
            'email' => 'forged@example.test',
            'role_id' => $role->id,
        ])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonValidationErrors(['tenant_id', 'etablissement_id']);

    $this->assertDatabaseMissing('invitations', ['email' => 'forged@example.test']);
});

test('invitation creation validates its payload', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->postJson('/api/v1/invitations', [
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'role_id']);
});

test('invitations are listed per establishment without exposing the token', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    createPendingInvitationForInvitationTests($tenant, $establishment, $role, ['email' => 'one@example.test']);
    createPendingInvitationForInvitationTests($tenant, $establishment, $role, ['email' => 'two@example.test']);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->getJson('/api/v1/invitations')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.token');
});

test('invitations of another establishment are invisible', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $sisterEstablishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    createPendingInvitationForInvitationTests($tenant, $sisterEstablishment, $role);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->getJson('/api/v1/invitations')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a manager revokes a pending invitation', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    [$invitation] = createPendingInvitationForInvitationTests($tenant, $establishment, $role);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->deleteJson("/api/v1/invitations/{$invitation->id}")
        ->assertOk()
        ->assertJsonPath('data.statut', 'REVOQUEE')
        ->assertJsonPath('data.id', $invitation->id);

    expect($invitation->fresh()->revoked_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->id,
        'action' => 'INVITATION_REVOQUEE',
    ]);
});

test('revoking an already revoked invitation is idempotent', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    [$invitation] = createPendingInvitationForInvitationTests($tenant, $establishment, $role, [
        'revoked_at' => now()->subHour(),
    ]);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->deleteJson("/api/v1/invitations/{$invitation->id}")
        ->assertOk()
        ->assertJsonPath('data.statut', 'REVOQUEE');
});

test('an accepted invitation cannot be revoked', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $role = invitedRoleForInvitationTests($tenant);

    [$invitation] = createPendingInvitationForInvitationTests($tenant, $establishment, $role, [
        'accepted_at' => now()->subHour(),
    ]);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->deleteJson("/api/v1/invitations/{$invitation->id}")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('revocation of an invitation from another tenant is not found', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $manager = createManagerForInvitationTests($tenant, $establishment);
    $otherTenant = createTenantForInvitationTests();
    $otherEstablishment = createEstablishmentForInvitationTests($otherTenant);
    $otherRole = invitedRoleForInvitationTests($otherTenant);

    [$foreignInvitation] = createPendingInvitationForInvitationTests($otherTenant, $otherEstablishment, $otherRole);

    $this->withToken(tokenForInvitationTests($manager, $tenant))
        ->deleteJson("/api/v1/invitations/{$foreignInvitation->id}")
        ->assertNotFound();
});

test('a single invitation grants a working membership', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);

    [$invitation, $token] = createPendingInvitationForInvitationTests($tenant, $establishment, $role);

    $response = $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests($token));

    $response
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('data.nom', 'Mbala')
        ->assertJsonPath('data.roles.0', 'PROFESSEUR')
        ->assertJsonPath('data.actif', true)
        ->assertJsonMissingPath('data.tenant_id');

    $this->assertDatabaseHas('utilisateurs', [
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'email' => $invitation->email,
        'nom' => 'Mbala',
        'telephone' => $response->json('data.telephone'),
        'actif' => true,
    ]);

    $onboarded = Utilisateur::query()->where('tenant_id', $tenant->id)->where('email', $invitation->email)->first();

    expect(Hash::check('secret-password', $onboarded->mot_de_passe))->toBeTrue()
        ->and($onboarded->roles()->pluck('roles.code')->all())->toBe(['PROFESSEUR']);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->postJson('/api/v1/auth/login', [
        'tenant_slug' => $tenant->slug,
        'identifiant' => $response->json('data.telephone'),
        'password' => 'secret-password',
    ])->assertOk()->assertJsonPath('data.user.nom', 'Mbala');
});

test('onboarding derives the email from the invitation, never from the payload', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);

    [$invitation, $token] = createPendingInvitationForInvitationTests($tenant, $establishment, $role);

    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests($token, [
        'email' => 'spoofed@example.test',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->assertDatabaseMissing('utilisateurs', ['email' => 'spoofed@example.test']);
});

test('accepting an unknown token is not found', function () {
    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests(
        hash('sha256', 'never-issued'),
    ))->assertNotFound()->assertHeader('Content-Type', 'application/problem+json');
});

test('an already accepted token cannot be reused', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);

    [,$token] = createPendingInvitationForInvitationTests($tenant, $establishment, $role);

    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests($token))
        ->assertCreated();

    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests($token))
        ->assertStatus(409);
});

test('a revoked token cannot be accepted', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);

    [,$token] = createPendingInvitationForInvitationTests($tenant, $establishment, $role, [
        'revoked_at' => now()->subHour(),
    ]);

    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests($token))
        ->assertStatus(409);
});

test('an expired token cannot be accepted', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);

    [,$token] = createPendingInvitationForInvitationTests($tenant, $establishment, $role, [
        'expire_at' => now()->subDay(),
    ]);

    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests($token))
        ->assertStatus(409);
});

test('onboarding conflicts when the email already has a membership in the tenant', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);

    [$invitation, $token] = createPendingInvitationForInvitationTests($tenant, $establishment, $role);

    createUserForInvitationTests($tenant, ['email' => $invitation->email]);

    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests($token))
        ->assertStatus(409);
});

test('onboarding rejects a weak password', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);

    [,$token] = createPendingInvitationForInvitationTests($tenant, $establishment, $role);

    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests($token, [
        'mot_de_passe' => 'short',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mot_de_passe']);
});

test('acceptance is throttled per token', function () {
    $tenant = createTenantForInvitationTests();
    $establishment = createEstablishmentForInvitationTests($tenant);
    $role = invitedRoleForInvitationTests($tenant);

    [,$token] = createPendingInvitationForInvitationTests($tenant, $establishment, $role);

    $payload = acceptancePayloadForInvitationTests($token);

    $this->postJson('/api/v1/public/invitations/acceptation', $payload)->assertCreated();

    foreach (range(2, 5) as $attempt) {
        $this->postJson('/api/v1/public/invitations/acceptation', $payload)
            ->assertStatus(409);
    }

    $this->postJson('/api/v1/public/invitations/acceptation', $payload)
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('acceptance is throttled per ip even with distinct tokens', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests(
            hash('sha256', "distinct-{$attempt}"),
        ))->assertNotFound();
    }

    $this->postJson('/api/v1/public/invitations/acceptation', acceptancePayloadForInvitationTests(
        hash('sha256', 'distinct-11'),
    ))->assertTooManyRequests();
});
