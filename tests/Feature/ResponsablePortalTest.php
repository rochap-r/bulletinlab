<?php

use App\Domains\Responsable\Exceptions\ResponsableAuthenticationException;
use App\Enums\StatutLienResponsable;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\LienResponsableEleve;
use App\Models\Responsable;
use App\Models\Tenant;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function responsablePortalTenant(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
}

function responsablePortalStudent(
    Tenant $tenant,
    string $name,
    string $parentPhone,
): Eleve {
    $school = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->getKey(),
        'nom' => 'École '.$name,
        'type_etablissement' => 'SECONDAIRE',
        'actif' => true,
    ]);

    return Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->getKey(),
        'etablissement_id' => $school->getKey(),
        'nom' => $name,
        'nom_pere' => 'Parent '.$name,
        'telephone_parent' => $parentPhone,
        'actif' => true,
    ]);
}

test('the student phone automatically creates one global responsible account and verified links', function (): void {
    $tenantA = responsablePortalTenant('parent-portal-a');
    $tenantB = responsablePortalTenant('parent-portal-b');

    responsablePortalStudent($tenantA, 'Enfant A', '0812 345 678');
    responsablePortalStudent($tenantB, 'Enfant B', '+243812345678');

    $responsable = Responsable::query()->sole();

    expect($responsable->telephone_normalise)->toBe('+243812345678')
        ->and(Hash::check('123456', $responsable->mot_de_passe))->toBeTrue()
        ->and($responsable->doit_changer_mot_de_passe)->toBeTrue()
        ->and($responsable->telephone_verifie_at)->toBeNull()
        ->and(LienResponsableEleve::withoutGlobalScopes()->count())->toBe(2)
        ->and(
            LienResponsableEleve::withoutGlobalScopes()
                ->where('statut', StatutLienResponsable::Verifie)
                ->count(),
        )->toBe(2);
});

test('the default password session cannot read children before changing the password', function (): void {
    $tenant = responsablePortalTenant('parent-first-login');
    responsablePortalStudent($tenant, 'Enfant', '+243812345679');

    $login = $this->postJson('/api/v1/responsables/auth/login', [
        'telephone' => '0812 345 679',
        'mot_de_passe' => '123456',
    ])->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.responsable.doit_changer_mot_de_passe', true)
        ->assertJsonMissingPath('data.responsable.id');

    $token = $login->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/v1/responsables/me')
        ->assertOk()
        ->assertJsonPath('data.telephone', '+243812345679');

    $this->withToken($token)
        ->getJson('/api/v1/responsables/enfants')
        ->assertForbidden()
        ->assertJsonPath('detail', 'PASSWORD_CHANGE_REQUIRED');
});

test('the first password change requires six characters and unlocks children from every school', function (): void {
    $tenantA = responsablePortalTenant('parent-password-a');
    $tenantB = responsablePortalTenant('parent-password-b');
    responsablePortalStudent($tenantA, 'Enfant A', '+243812345680');
    responsablePortalStudent($tenantB, 'Enfant B', '+243812345680');

    $login = $this->postJson('/api/v1/responsables/auth/login', [
        'telephone' => '+243812345680',
        'mot_de_passe' => '123456',
    ])->assertOk();

    $initialToken = $login->json('data.access_token');

    $this->withToken($initialToken)
        ->postJson('/api/v1/responsables/me/password-initial', [
            'mot_de_passe' => 'court',
            'mot_de_passe_confirmation' => 'court',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['mot_de_passe']);

    $this->withToken($initialToken)
        ->postJson('/api/v1/responsables/me/password-initial', [
            'mot_de_passe' => '123456',
            'mot_de_passe_confirmation' => '123456',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['mot_de_passe']);

    $changed = $this->withToken($initialToken)
        ->postJson('/api/v1/responsables/me/password-initial', [
            'mot_de_passe' => 'secret-parent',
            'mot_de_passe_confirmation' => 'secret-parent',
        ])->assertOk()
        ->assertJsonPath('data.responsable.doit_changer_mot_de_passe', false);

    $newToken = $changed->json('data.access_token');

    $this->withToken($initialToken)
        ->getJson('/api/v1/responsables/enfants')
        ->assertUnauthorized();

    $this->withToken($newToken)
        ->getJson('/api/v1/responsables/enfants')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.permissions.notes', true)
        ->assertJsonMissingPath('data.0.enfant.id')
        ->assertJsonMissingPath('data.0.ecole.id');

    $responsable = Responsable::query()->sole();
    expect(Hash::check('secret-parent', $responsable->mot_de_passe))->toBeTrue()
        ->and($responsable->doit_changer_mot_de_passe)->toBeFalse();
});

test('changing the phone in the student record revokes the obsolete automatic link', function (): void {
    $tenant = responsablePortalTenant('parent-phone-change');
    $student = responsablePortalStudent($tenant, 'Enfant', '+243812345681');
    $formerResponsable = Responsable::query()
        ->where('telephone_normalise', '+243812345681')
        ->firstOrFail();

    $student->update(['telephone_parent' => '+243812345682']);

    expect($formerResponsable->liensEleves()->firstOrFail()->fresh()->statut)
        ->toBe(StatutLienResponsable::Revoque)
        ->and(
            Responsable::query()
                ->where('telephone_normalise', '+243812345682')
                ->exists(),
        )->toBeTrue();
});

test('the parent module can be disabled and reenabled per tenant', function (): void {
    $tenantA = responsablePortalTenant('parent-feature-a');
    $tenantB = responsablePortalTenant('parent-feature-b');
    $studentA = responsablePortalStudent($tenantA, 'Enfant A', '+243812345683');
    $studentB = responsablePortalStudent($tenantB, 'Enfant B', '+243812345683');

    $linkB = LienResponsableEleve::withoutGlobalScopes()
        ->where('eleve_id', $studentB->getKey())
        ->firstOrFail();

    $tenantB->forceFill(['espace_parents_actif' => false])->save();

    $login = $this->postJson('/api/v1/responsables/auth/login', [
        'telephone' => '+243812345683',
        'mot_de_passe' => '123456',
    ])->assertOk();

    $changed = $this->withToken($login->json('data.access_token'))
        ->postJson('/api/v1/responsables/me/password-initial', [
            'mot_de_passe' => 'secret-parent',
            'mot_de_passe_confirmation' => 'secret-parent',
        ])->assertOk();

    $token = $changed->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/v1/responsables/enfants')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ecole.nom', 'École Enfant A');

    $this->withToken($token)
        ->getJson('/api/v1/responsables/enfants/'.$linkB->public_id.'/evaluations')
        ->assertNotFound();

    $tenantB->forceFill(['espace_parents_actif' => true])->save();

    $this->withToken($token)
        ->getJson('/api/v1/responsables/enfants')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('responsable login uses a generic error and is rate limited', function (): void {
    expect(new ResponsableAuthenticationException('Invalid credentials'))
        ->toBeInstanceOf(ShouldntReport::class);

    $payload = [
        'telephone' => '+243812349999',
        'mot_de_passe' => 'incorrect',
    ];

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/responsables/auth/login', $payload)
            ->assertUnauthorized()
            ->assertJsonPath('detail', 'Invalid credentials');
    }

    $this->postJson('/api/v1/responsables/auth/login', $payload)
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('parent routes reject client managed school context', function (): void {
    $tenant = responsablePortalTenant('parent-managed-context');
    responsablePortalStudent($tenant, 'Enfant', '+243812345699');

    $this->postJson('/api/v1/responsables/auth/login', [
        'telephone' => '+243812345699',
        'mot_de_passe' => '123456',
        'tenant_id' => $tenant->getKey(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_id']);

    $login = $this->postJson('/api/v1/responsables/auth/login', [
        'telephone' => '+243812345699',
        'mot_de_passe' => '123456',
    ])->assertOk();

    $changed = $this->withToken($login->json('data.access_token'))
        ->postJson('/api/v1/responsables/me/password-initial', [
            'mot_de_passe' => 'secret-parent',
            'mot_de_passe_confirmation' => 'secret-parent',
        ])->assertOk();

    $this->withToken($changed->json('data.access_token'))
        ->getJson('/api/v1/responsables/enfants?etablissement_id=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['etablissement_id']);
});
