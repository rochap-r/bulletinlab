<?php

use App\Models\Etablissement;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('un gestionnaire met à jour la fiche exhaustive d’un enseignant', function (): void {
    $tenant = createTenantForDirectUsers('enseignants-update');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École A', 'type_etablissement' => 'PRIMAIRE']);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    foreach (['ENSEIGNANT', 'TITULAIRE'] as $code) {
        globalRoleForDirectUsers($code);
    }

    $created = $this->withToken(tokenForDirectUsers($manager))->postJson('/api/v1/utilisateurs', [
        'nom' => 'Ilunga',
        'postnom' => 'Mbuyi',
        'prenom' => 'Sara',
        'sexe' => 'F',
        'telephone' => '+243811000501',
        'role' => 'ENSEIGNANT',
        'qualification' => 'Graduée en pédagogie',
        'specialite' => 'Mathématiques',
        'matricule' => 'ENS-001',
    ])->assertCreated()->json('data.utilisateur.id');

    $this->withToken(tokenForDirectUsers($manager))->putJson("/api/v1/utilisateurs/{$created}", [
        'qualification' => 'Licenciée en mathématiques',
        'specialite' => 'Physique',
        'adresse' => 'Av. de la Paix 12, Kinshasa',
        'role' => 'TITULAIRE',
    ])->assertOk()
        ->assertJsonPath('data.qualification', 'Licenciée en mathématiques')
        ->assertJsonPath('data.specialite', 'Physique')
        ->assertJsonPath('data.adresse', 'Av. de la Paix 12, Kinshasa');

    $this->withToken(tokenForDirectUsers($manager))->putJson("/api/v1/utilisateurs/{$created}", [
        'actif' => false,
    ])->assertOk()->assertJsonPath('data.actif', false);

    expect(Utilisateur::query()->whereKey($created)->firstOrFail()->matricule)->toBe('ENS-001');
});

test('un gestionnaire importe des enseignants avec rapport ligne par ligne', function (): void {
    $tenant = createTenantForDirectUsers('enseignants-import');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École B', 'type_etablissement' => 'PRIMAIRE']);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    foreach (['ENSEIGNANT', 'TITULAIRE'] as $code) {
        globalRoleForDirectUsers($code);
    }

    $response = $this->withToken(tokenForDirectUsers($manager))->postJson('/api/v1/utilisateurs/import-enseignants', [
        'enseignants' => [
            ['nom' => 'Kabasele', 'prenom' => 'Jean', 'telephone' => '+243811000601', 'sexe' => 'M', 'matricule' => 'ENS-101', 'qualification' => 'D6 Pédagogie'],
            ['nom' => '', 'telephone' => 'mauvais', 'role' => 'ENSEIGNANT'],
            ['nom' => 'Mbuyi', 'prenom' => 'Aline', 'telephone' => '+243811000601', 'role' => 'TITULAIRE'],
        ],
    ])->assertCreated()->json('data');

    expect($response['crees_count'])->toBe(1)
        ->and($response['erreurs_count'])->toBe(2)
        ->and($response['crees'][0]['mot_de_passe_temporaire'])->toBe('123456');

    $this->assertDatabaseHas('utilisateurs', ['tenant_id' => $tenant->id, 'matricule' => 'ENS-101']);
});

test('un enseignant créé reçoit le mot de passe 123456 et doit le changer à la première connexion', function (): void {
    $tenant = createTenantForDirectUsers('enseignants-mdp-defaut');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École F', 'type_etablissement' => 'PRIMAIRE']);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    foreach (['ENSEIGNANT', 'TITULAIRE'] as $code) {
        globalRoleForDirectUsers($code);
    }

    $createdId = $this->withToken(tokenForDirectUsers($manager))->postJson('/api/v1/utilisateurs', [
        'nom' => 'Ilunga',
        'telephone' => '+243811000501',
        'role' => 'ENSEIGNANT',
    ])->assertCreated()->json('data.utilisateur.id');

    $teacher = Utilisateur::query()->whereKey($createdId)->firstOrFail();
    expect(Hash::check('123456', $teacher->mot_de_passe))->toBeTrue()
        ->and($teacher->doit_changer_mot_de_passe)->toBeTrue();

    $this->app['auth']->forgetGuards();
    $token = $teacher->createToken('mdp-defaut', ["tenant:{$tenant->id}"])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/etablissements')->assertForbidden()
        ->assertJsonPath('detail', 'PASSWORD_CHANGE_REQUIRED');

    $this->withToken($token)->postJson('/api/v1/me/password', [
        'mot_de_passe_actuel' => '123456',
        'mot_de_passe' => 'nouveau-mot-de-passe-sur',
        'mot_de_passe_confirmation' => 'nouveau-mot-de-passe-sur',
    ])->assertOk();

    expect($teacher->fresh()?->doit_changer_mot_de_passe)->toBeFalse();
});
