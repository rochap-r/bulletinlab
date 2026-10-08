<?php

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\RefCycle;
use App\Models\RefDegre;
use App\Models\RefMatiere;
use App\Models\RefNiveau;
use App\Models\RefOption;
use App\Models\RolePlateforme;
use App\Models\SystemLayoutBulletin;
use App\Models\SystemTemplateBulletin;
use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makePlatformAdminToken(): string
{
    RolePlateforme::query()->updateOrCreate(
        ['code' => 'ADMIN_PLATEFORME'],
        ['nom' => 'Administrateur plateforme'],
    );

    $utilisateur = UtilisateurPlateforme::factory()->create([
        'email' => 'referentiel-admin@plateforme.test',
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);

    $utilisateur->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);

    return $utilisateur->createToken('referentiel-test', ['context:platform'])->plainTextToken;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeCycleForTests(array $attributes = []): RefCycle
{
    return RefCycle::query()->create([
        'code' => 'CYCLE_'.Str::upper(Str::random(6)),
        'nom' => 'Cycle test',
        'ordre' => 1,
        'actif' => true,
        ...$attributes,
    ]);
}

function makeReferencedClasseForTests(RefCycle $cycle, RefNiveau $niveau): Classe
{
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'École Conflit',
        'slug' => 'ecole-conflit-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $etablissement = Etablissement::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Établissement Conflit',
        'actif' => true,
    ]);

    $annee = AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);

    $layout = SystemLayoutBulletin::query()->create([
        'code' => 'LAYOUT_'.Str::upper(Str::random(6)),
        'nom' => 'Layout test',
        'actif' => true,
    ]);

    $template = SystemTemplateBulletin::query()->create([
        'code' => 'TPL_'.Str::upper(Str::random(6)),
        'nom' => 'Modèle test',
        'cycle_id' => $cycle->id,
        'layout_id' => $layout->id,
        'type_decoupage' => 'SEMESTRIEL',
        'version' => '2026-2027',
        'actif' => true,
    ]);

    return Classe::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement->id,
        'annee_scolaire_id' => $annee->id,
        'cycle_id' => $cycle->id,
        'niveau_id' => $niveau->id,
        'system_template_id' => $template->id,
        'nom' => 'Classe conflictuelle',
        'active' => true,
    ]);
}

test('the platform referential can list sections with their children', function () {
    $token = makePlatformAdminToken();
    $cycle = makeCycleForTests(['code' => 'PRIMAIRE']);
    $cycle->degres()->create(['code' => 'D1', 'nom' => 'Premier degré', 'ordre' => 1]);
    $cycle->niveaux()->create(['code' => 'F1', 'nom' => 'Forme 1', 'numero' => 1, 'ordre' => 1]);
    $cycle->options()->create(['code' => 'GEN', 'nom' => 'Général', 'actif' => true]);

    $this->withToken($token)
        ->getJson('/api/v1/platform/referentiel/cycles')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'PRIMAIRE')
        ->assertJsonStructure([
            'data' => [[
                'id', 'code', 'nom', 'ordre', 'actif',
                'degres' => [['id', 'code', 'nom', 'ordre']],
                'niveaux' => [['id', 'code', 'nom', 'numero']],
                'options' => [['id', 'code', 'nom', 'actif']],
            ]],
        ]);
});

test('an authenticated platform user can create a referential section', function () {
    $token = makePlatformAdminToken();

    $this->withToken($token)
        ->postJson('/api/v1/platform/referentiel/cycles', [
            'code' => 'SECONDAIRE',
            'nom' => 'Secondaire',
            'ordre' => 2,
            'actif' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'SECONDAIRE')
        ->assertJsonPath('data.nom', 'Secondaire')
        ->assertJsonPath('data.actif', true);

    $this->assertDatabaseHas('ref_cycles', ['code' => 'SECONDAIRE']);
});

test('a duplicate section code is rejected', function () {
    $token = makePlatformAdminToken();
    makeCycleForTests(['code' => 'DUPLIQUE']);

    $this->withToken($token)
        ->postJson('/api/v1/platform/referentiel/cycles', [
            'code' => 'DUPLIQUE',
            'nom' => 'Dupliqué',
        ])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonValidationErrors(['code']);
});

test('unknown fields are rejected on referential mutations', function () {
    $token = makePlatformAdminToken();

    $this->withToken($token)
        ->postJson('/api/v1/platform/referentiel/cycles', [
            'code' => 'PRIMAIRE',
            'nom' => 'Primaire',
            'etiquettes_fantaisistes' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['etiquettes_fantaisistes']);
});

test('a referential section can be updated', function () {
    $token = makePlatformAdminToken();
    $cycle = makeCycleForTests(['code' => 'CIBLE']);

    $this->withToken($token)
        ->putJson("/api/v1/platform/referentiel/cycles/{$cycle->id}", [
            'code' => 'CIBLE',
            'nom' => 'Cycle cible renommé',
            'ordre' => 3,
        ])
        ->assertOk()
        ->assertJsonPath('data.nom', 'Cycle cible renommé');

    $this->assertDatabaseHas('ref_cycles', ['id' => $cycle->id, 'nom' => 'Cycle cible renommé']);
});

test('a referential section used by classes cannot be deleted', function () {
    $token = makePlatformAdminToken();
    $cycle = makeCycleForTests();
    $niveau = $cycle->niveaux()->create([
        'code' => 'F3',
        'nom' => 'Forme 3',
        'numero' => 3,
        'ordre' => 3,
    ]);
    makeReferencedClasseForTests($cycle, $niveau);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/referentiel/cycles/{$cycle->id}")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 409);

    $this->assertDatabaseHas('ref_cycles', ['id' => $cycle->id]);
});

test('a free referential section can be deleted', function () {
    $token = makePlatformAdminToken();
    $cycle = makeCycleForTests();

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/referentiel/cycles/{$cycle->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('ref_cycles', ['id' => $cycle->id]);
});

test('a niveau used by classes cannot be deleted', function () {
    $token = makePlatformAdminToken();
    $cycle = makeCycleForTests();
    $niveau = $cycle->niveaux()->create([
        'code' => 'F1',
        'nom' => 'Forme 1',
        'numero' => 1,
        'ordre' => 1,
    ]);
    makeReferencedClasseForTests($cycle, $niveau);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/niveaux/{$niveau->id}")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->assertDatabaseHas('ref_niveaux', ['id' => $niveau->id]);
});

test('degrees can be managed within their section', function () {
    $token = makePlatformAdminToken();
    $cycle = makeCycleForTests();

    $this->withToken($token)
        ->postJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/degres", [
            'code' => 'D1',
            'nom' => 'Premier degré',
            'ordre' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'D1')
        ->assertJsonPath('data.cycle_id', $cycle->id);

    $created = RefDegre::query()->where('code', 'D1')->firstOrFail();

    $this->withToken($token)
        ->getJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/degres")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($token)
        ->putJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/degres/{$created->id}", [
            'code' => 'D1',
            'nom' => 'Premier degré révisé',
            'ordre' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.nom', 'Premier degré révisé');

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/degres/{$created->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('ref_degres', ['id' => $created->id]);
});

test('a niveau can only reference a degree of its own section', function () {
    $token = makePlatformAdminToken();
    $cycleA = makeCycleForTests(['code' => 'SEC_A']);
    $cycleB = makeCycleForTests(['code' => 'SEC_B']);
    $foreignDegree = $cycleA->degres()->create([
        'code' => 'D1',
        'nom' => 'Premier degré',
        'ordre' => 1,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/referentiel/cycles/{$cycleB->id}/niveaux", [
            'degre_id' => $foreignDegree->id,
            'code' => 'F1',
            'nom' => 'Forme 1',
            'numero' => 1,
            'ordre' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['degre_id']);
});

test('niveaux can be managed within their section', function () {
    $token = makePlatformAdminToken();
    $cycle = makeCycleForTests();

    $this->withToken($token)
        ->postJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/niveaux", [
            'code' => 'F4',
            'nom' => 'Forme 4',
            'numero' => 4,
            'ordre' => 4,
        ])
        ->assertCreated()
        ->assertJsonPath('data.nom', 'Forme 4');

    $created = RefNiveau::query()->where('code', 'F4')->firstOrFail();

    $this->withToken($token)
        ->getJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/niveaux")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($token)
        ->putJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/niveaux/{$created->id}", [
            'code' => 'F4',
            'nom' => 'Forme 4 révisée',
            'numero' => 4,
            'ordre' => 5,
        ])
        ->assertOk()
        ->assertJsonPath('data.nom', 'Forme 4 révisée');
});

test('options can be managed within their section', function () {
    $token = makePlatformAdminToken();
    $cycle = makeCycleForTests();

    $this->withToken($token)
        ->postJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/options", [
            'code' => 'BIO',
            'nom' => 'Bio-chimie',
            'actif' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'BIO');

    $created = RefOption::query()->where('code', 'BIO')->firstOrFail();

    $this->withToken($token)
        ->getJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/options")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/referentiel/cycles/{$cycle->id}/options/{$created->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('ref_options', ['id' => $created->id]);
});

test('matieres can be managed globally', function () {
    $token = makePlatformAdminToken();

    $this->withToken($token)
        ->postJson('/api/v1/platform/referentiel/matieres', [
            'code' => 'MATH',
            'nom' => 'Mathématiques',
            'nom_court' => 'Math.',
            'actif' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'MATH');

    $created = RefMatiere::query()->where('code', 'MATH')->firstOrFail();

    $this->withToken($token)
        ->getJson('/api/v1/platform/referentiel/matieres')
        ->assertOk()
        ->assertJsonFragment(['id' => $created->id]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/referentiel/matieres/{$created->id}", [
            'code' => 'MATH',
            'nom' => 'Mathématiques renommées',
            'actif' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.actif', false);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/referentiel/matieres/{$created->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('ref_matieres', ['id' => $created->id]);
});

test('the referential endpoints reject tenant tokens and anonymous requests', function () {
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'École',
        'slug' => 'ecole-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $tenantUser = Utilisateur::factory()->create([
        'tenant_id' => $tenant->id,
        'actif' => true,
    ]);
    $tenantToken = $tenantUser->createToken('tenant', ["tenant:{$tenant->id}"])->plainTextToken;

    $this->withToken($tenantToken)
        ->getJson('/api/v1/platform/referentiel/cycles')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->getJson('/api/v1/platform/referentiel/cycles')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});
