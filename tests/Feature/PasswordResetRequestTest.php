<?php

use App\Models\DemandeReinitialisationMotDePasse;
use App\Models\Etablissement;
use App\Models\Role;
use App\Models\RolePlateforme;
use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function tenantPourDemandeMotDePasse(string $suffixe): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'École '.$suffixe,
        'slug' => 'ecole-'.$suffixe,
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
}

function utilisateurPourDemandeMotDePasse(Tenant $tenant, string $role = 'ADMIN_TENANT', string $telephone = '+243811000901'): Utilisateur
{
    $utilisateur = Utilisateur::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'nom' => 'Kalala',
        'telephone' => $telephone,
        'mot_de_passe' => Hash::make('Ancien1'),
        'actif' => true,
    ]);

    $roleModel = Role::query()->firstOrCreate(
        ['tenant_id' => null, 'name' => $role],
        [
            'code' => $role,
            'nom' => $role,
            'guard_name' => 'web',
            'system_role' => true,
        ],
    );
    $utilisateur->assignRole($roleModel);

    return $utilisateur;
}

function jetonPlateformePourDemandeMotDePasse(): array
{
    RolePlateforme::query()->firstOrCreate(
        ['code' => 'ADMIN_PLATEFORME'],
        ['nom' => 'Administrateur plateforme'],
    );
    $acteur = UtilisateurPlateforme::factory()->create([
        'email' => 'reset@plateforme.test',
        'mot_de_passe' => Hash::make('Secret1'),
    ]);
    $acteur->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);

    return [$acteur, $acteur->createToken('password-reset', ['context:platform'])->plainTextToken];
}

test('un administrateur ecole peut demander une reinitialisation sans divulgation de compte', function (): void {
    $tenant = tenantPourDemandeMotDePasse('demande');
    utilisateurPourDemandeMotDePasse($tenant);

    $this->postJson('/api/v1/auth/password-reset-requests', [
        'telephone' => '+243811000901',
    ])->assertAccepted()
        ->assertJsonPath(
            'message',
            'Si ce numéro correspond à un compte actif, la demande a été transmise au responsable habilité.',
        );

    $this->postJson('/api/v1/auth/password-reset-requests', [
        'telephone' => '+243899999999',
    ])->assertAccepted()
        ->assertJsonPath(
            'message',
            'Si ce numéro correspond à un compte actif, la demande a été transmise au responsable habilité.',
        );

    expect(DemandeReinitialisationMotDePasse::query()->count())->toBe(1);
});

test('une demande en attente est unique par compte administrateur', function (): void {
    $tenant = tenantPourDemandeMotDePasse('unique');
    utilisateurPourDemandeMotDePasse($tenant);

    foreach (range(1, 2) as $attempt) {
        $this->postJson('/api/v1/auth/password-reset-requests', [
            'telephone' => '+243811000901',
        ])->assertAccepted();
    }

    expect(DemandeReinitialisationMotDePasse::query()->count())->toBe(1);
});

test('un compte sans role administrateur envoie la demande a son ecole', function (): void {
    $tenant = tenantPourDemandeMotDePasse('enseignant');
    utilisateurPourDemandeMotDePasse($tenant, 'ENSEIGNANT');

    $this->postJson('/api/v1/auth/password-reset-requests', [
        'telephone' => '+243811000901',
    ])->assertAccepted();

    expect(DemandeReinitialisationMotDePasse::query()->count())->toBe(1)
        ->and(DemandeReinitialisationMotDePasse::query()->firstOrFail()->destinataire)->toBe('ECOLE');

    [, $token] = jetonPlateformePourDemandeMotDePasse();
    $this->withToken($token)->getJson('/api/v1/platform/password-reset-requests')
        ->assertOk()->assertJsonCount(0, 'data');
});

test('les ecoles liees voient la demande et elle disparait partout apres une reinitialisation', function (): void {
    $premierTenant = tenantPourDemandeMotDePasse('liee-a');
    $secondTenant = tenantPourDemandeMotDePasse('liee-b');
    $premierEnseignant = utilisateurPourDemandeMotDePasse($premierTenant, 'ENSEIGNANT');
    $secondEnseignant = utilisateurPourDemandeMotDePasse($secondTenant, 'ENSEIGNANT');
    $premierAdmin = utilisateurPourDemandeMotDePasse($premierTenant, 'ADMIN_TENANT', '+243811000902');
    $secondAdmin = utilisateurPourDemandeMotDePasse($secondTenant, 'ADMIN_TENANT', '+243811000903');

    $this->postJson('/api/v1/auth/password-reset-requests', [
        'telephone' => '+243811000901',
    ])->assertAccepted();

    $demandes = DemandeReinitialisationMotDePasse::query()->where('destinataire', 'ECOLE')->get();
    expect($demandes)->toHaveCount(2);

    $this->withToken(tokenForDirectUsers($premierAdmin))
        ->getJson('/api/v1/password-reset-requests')
        ->assertOk()->assertJsonCount(1, 'data');
    $this->withToken(tokenForDirectUsers($secondAdmin))
        ->getJson('/api/v1/password-reset-requests')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->withToken(tokenForDirectUsers($premierAdmin))
        ->postJson('/api/v1/password-reset-requests/'.$demandes->first()->getKey().'/reset')
        ->assertOk()
        ->assertJsonPath('meta.mot_de_passe_temporaire', '123456');

    expect(DemandeReinitialisationMotDePasse::query()->enAttente()->count())->toBe(0)
        ->and(Hash::check('123456', $premierEnseignant->fresh()->mot_de_passe))->toBeTrue()
        ->and(Hash::check('123456', $secondEnseignant->fresh()->mot_de_passe))->toBeTrue();

    $this->withToken(tokenForDirectUsers($secondAdmin))
        ->getJson('/api/v1/password-reset-requests')
        ->assertOk()->assertJsonCount(0, 'data');
});

test('un prefet ne voit que les demandes de son etablissement et aucun administrateur', function (): void {
    $tenant = tenantPourDemandeMotDePasse('prefet');
    $ecole = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->getKey(), 'nom' => 'École A', 'type_etablissement' => 'SECONDAIRE',
    ]);
    $autreEcole = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->getKey(), 'nom' => 'École B', 'type_etablissement' => 'PRIMAIRE',
    ]);
    $prefet = utilisateurPourDemandeMotDePasse($tenant, 'GESTIONNAIRE_ECOLE', '+243811000904');
    $prefet->update(['etablissement_id' => $ecole->getKey()]);
    $enseignant = utilisateurPourDemandeMotDePasse($tenant, 'ENSEIGNANT');
    $enseignant->update(['etablissement_id' => $ecole->getKey()]);
    $autreEnseignant = Utilisateur::factory()->create([
        'tenant_id' => $tenant->getKey(), 'etablissement_id' => $autreEcole->getKey(),
        'telephone' => '+243811000905', 'actif' => true,
    ]);
    $autreEnseignant->assignRole(Role::query()->where('code', 'ENSEIGNANT')->firstOrFail());
    $admin = utilisateurPourDemandeMotDePasse($tenant, 'ADMIN_TENANT', '+243811000906');
    $demandes = collect([$enseignant, $autreEnseignant, $admin])->map(fn (Utilisateur $utilisateur) => DemandeReinitialisationMotDePasse::query()->create([
        'tenant_id' => $tenant->getKey(), 'utilisateur_id' => $utilisateur->getKey(),
        'destinataire' => $utilisateur->is($admin) ? 'PLATEFORME' : 'ECOLE',
    ]));

    $token = tokenForDirectUsers($prefet);
    $this->withToken($token)->getJson('/api/v1/password-reset-requests')->assertOk()->assertJsonCount(1, 'data');
    $this->withToken($token)->postJson('/api/v1/password-reset-requests/'.$demandes[1]->getKey().'/reset')->assertNotFound();
    $this->withToken($token)->postJson('/api/v1/password-reset-requests/'.$demandes[2]->getKey().'/reset')->assertNotFound();
    $this->withToken($token)->postJson('/api/v1/password-reset-requests/'.$demandes[0]->getKey().'/reset')
        ->assertOk()->assertJsonPath('meta.mot_de_passe_temporaire', '123456');
    expect(Hash::check('123456', $enseignant->fresh()->mot_de_passe))->toBeTrue();
});

test('le changement du mot de passe temporaire synchronise les comptes enseignants lies', function (): void {
    $premierTenant = tenantPourDemandeMotDePasse('changement-a');
    $secondTenant = tenantPourDemandeMotDePasse('changement-b');
    $premier = utilisateurPourDemandeMotDePasse($premierTenant, 'ENSEIGNANT');
    $second = utilisateurPourDemandeMotDePasse($secondTenant, 'ENSEIGNANT');
    $admin = utilisateurPourDemandeMotDePasse($premierTenant, 'ADMIN_TENANT', '+243811000902');

    $this->postJson('/api/v1/auth/password-reset-requests', ['telephone' => '+243811000901'])->assertAccepted();
    $demande = DemandeReinitialisationMotDePasse::query()->where('tenant_id', $premierTenant->getKey())->firstOrFail();
    $this->withToken(tokenForDirectUsers($admin))
        ->postJson('/api/v1/password-reset-requests/'.$demande->getKey().'/reset')
        ->assertOk();

    $this->flushHeaders();
    Auth::forgetGuards();
    $login = $this->postJson('/api/v1/auth/login', [
        'identifiant' => '+243811000901',
        'password' => '123456',
        'tenant_id' => $premierTenant->getKey(),
    ])->assertOk()->assertJsonPath('data.user.doit_changer_mot_de_passe', true);

    Auth::forgetGuards();
    $this->withToken($login->json('data.access_token'))
        ->postJson('/api/v1/me/password-initial', [
            'mot_de_passe' => 'Nouveau1',
            'mot_de_passe_confirmation' => 'Nouveau1',
        ])->assertOk();

    expect(Hash::check('Nouveau1', $premier->fresh()->mot_de_passe))->toBeTrue()
        ->and(Hash::check('Nouveau1', $second->fresh()->mot_de_passe))->toBeTrue()
        ->and($second->fresh()->doit_changer_mot_de_passe)->toBeFalse();
});

test('la plateforme voit les demandes en attente', function (): void {
    $tenant = tenantPourDemandeMotDePasse('liste');
    $utilisateur = utilisateurPourDemandeMotDePasse($tenant);
    DemandeReinitialisationMotDePasse::query()->create([
        'tenant_id' => $tenant->getKey(),
        'utilisateur_id' => $utilisateur->getKey(),
    ]);
    [, $token] = jetonPlateformePourDemandeMotDePasse();

    $this->withToken($token)
        ->getJson('/api/v1/platform/password-reset-requests')
        ->assertOk()
        ->assertJsonPath('meta.en_attente', 1)
        ->assertJsonPath('data.0.administrateur.telephone', '+243811000901')
        ->assertJsonPath('data.0.tenant.nom_espace', 'École liste')
        ->assertJsonPath('data.0.statut', 'EN_ATTENTE');
});

test('la reinitialisation plateforme impose le mot de passe temporaire et revoque les sessions', function (): void {
    $tenant = tenantPourDemandeMotDePasse('traitement');
    $utilisateur = utilisateurPourDemandeMotDePasse($tenant);
    $ancienToken = $utilisateur->createToken('ancienne-session', ["tenant:{$tenant->getKey()}"]);
    $demande = DemandeReinitialisationMotDePasse::query()->create([
        'tenant_id' => $tenant->getKey(),
        'utilisateur_id' => $utilisateur->getKey(),
    ]);
    [$acteur, $token] = jetonPlateformePourDemandeMotDePasse();

    $this->withToken($token)
        ->postJson("/api/v1/platform/password-reset-requests/{$demande->getKey()}/reset")
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.statut', 'TRAITEE')
        ->assertJsonPath('meta.mot_de_passe_temporaire', '123456');

    $utilisateur->refresh();
    $demande->refresh();

    expect(Hash::check('123456', $utilisateur->mot_de_passe))->toBeTrue()
        ->and($utilisateur->doit_changer_mot_de_passe)->toBeTrue()
        ->and($demande->traitee_par_id)->toBe($acteur->getKey())
        ->and($demande->traitee_at)->not->toBeNull();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $ancienToken->accessToken->getKey(),
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $tenant->getKey(),
        'action' => 'MDP_ADMIN_REINITIALISE_PAR_PLATEFORME',
        'ligne_cible_id' => $utilisateur->getKey(),
    ]);
});

test('le mot de passe temporaire conduit au changement obligatoire avant acces', function (): void {
    $tenant = tenantPourDemandeMotDePasse('parcours');
    $utilisateur = utilisateurPourDemandeMotDePasse($tenant);
    $demande = DemandeReinitialisationMotDePasse::query()->create([
        'tenant_id' => $tenant->getKey(),
        'utilisateur_id' => $utilisateur->getKey(),
    ]);
    [, $platformToken] = jetonPlateformePourDemandeMotDePasse();

    $this->withToken($platformToken)
        ->postJson("/api/v1/platform/password-reset-requests/{$demande->getKey()}/reset")
        ->assertOk();

    $this->flushHeaders();
    Auth::forgetGuards();

    $login = $this->postJson('/api/v1/auth/login', [
        'identifiant' => '+243811000901',
        'password' => '123456',
    ])->assertOk()
        ->assertJsonPath('data.user.doit_changer_mot_de_passe', true);

    Auth::forgetGuards();

    $this->withToken($login->json('data.access_token'))
        ->postJson('/api/v1/me/password-initial', [
            'mot_de_passe' => 'Nouveau1',
            'mot_de_passe_confirmation' => 'Nouveau1',
        ])->assertOk()
        ->assertJsonPath('data.doit_changer_mot_de_passe', false);

    $utilisateur->refresh();
    expect(Hash::check('Nouveau1', $utilisateur->mot_de_passe))->toBeTrue()
        ->and($utilisateur->doit_changer_mot_de_passe)->toBeFalse();
});

test('les routes de traitement exigent une session plateforme', function (): void {
    $this->getJson('/api/v1/platform/password-reset-requests')->assertUnauthorized();
    $this->postJson('/api/v1/platform/password-reset-requests/1/reset')->assertUnauthorized();
});
