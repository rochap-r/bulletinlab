<?php

use App\Enums\TemplateStatut;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\ModeleBulletin;
use App\Models\RefCycle;
use App\Models\RefDegre;
use App\Models\RefMatiere;
use App\Models\RefNiveau;
use App\Models\RefOption;
use App\Models\RolePlateforme;
use App\Models\SystemBlocSpecial;
use App\Models\SystemLayoutBulletin;
use App\Models\SystemTemplateBloc;
use App\Models\SystemTemplateBulletin;
use App\Models\SystemTemplateMaximum;
use App\Models\SystemTemplateNode;
use App\Models\SystemTemplatePeriode;
use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makePlatformConfigurationToken(): string
{
    RolePlateforme::query()->updateOrCreate(
        ['code' => 'ADMIN_PLATEFORME'],
        ['nom' => 'Administrateur plateforme'],
    );

    $utilisateur = UtilisateurPlateforme::factory()->create([
        'email' => 'catalogue-admin@plateforme.test',
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);

    $utilisateur->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);

    return $utilisateur->createToken('catalogue-test', ['context:platform'])->plainTextToken;
}

/**
 * @return array<string, mixed>
 */
function makeConfigurationFixture(): array
{
    $cycle = RefCycle::query()->create([
        'code' => 'EB',
        'nom' => 'Éducation de base',
        'ordre' => 1,
        'actif' => true,
    ]);

    $degre = RefDegre::query()->create([
        'cycle_id' => $cycle->id,
        'code' => 'CTEB',
        'nom' => 'CTEB',
        'ordre' => 1,
    ]);

    $niveauA = RefNiveau::query()->create([
        'cycle_id' => $cycle->id,
        'degre_id' => $degre->id,
        'code' => 'EB_7',
        'nom' => '7ème',
        'numero' => 7,
        'ordre' => 1,
    ]);

    $niveauB = RefNiveau::query()->create([
        'cycle_id' => $cycle->id,
        'degre_id' => $degre->id,
        'code' => 'EB_8',
        'nom' => '8ème',
        'numero' => 8,
        'ordre' => 2,
    ]);

    $mathematiques = RefMatiere::query()->create([
        'code' => 'MATH',
        'nom' => 'Mathématiques',
        'actif' => true,
    ]);

    $francais = RefMatiere::query()->create([
        'code' => 'FR',
        'nom' => 'Français',
        'actif' => true,
    ]);

    $layout = SystemLayoutBulletin::query()->create([
        'code' => 'RDC_CTEB_DOMAINES',
        'nom' => 'CTEB domaines',
        'actif' => true,
    ]);

    $template = SystemTemplateBulletin::query()->create([
        'code' => 'CTEB_7_FIXTURE',
        'nom' => '7ème EB CTEB',
        'cycle_id' => $cycle->id,
        'degre_id' => $degre->id,
        'niveau_min_id' => $niveauA->id,
        'niveau_max_id' => $niveauA->id,
        'layout_id' => $layout->id,
        'type_decoupage' => 'SEMESTRIEL',
        'total_general_maxima' => 3360,
        'version' => '2026-2027',
        'actif' => true,
    ]);

    $matieresNode = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'DOMAINE',
        'libelle' => 'Domaine des compétences',
        'ordre' => 1,
    ]);

    $sousDomaine = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'parent_id' => $matieresNode->id,
        'type_node' => 'SOUS_DOMAINE',
        'libelle' => 'Outils de base',
        'ordre' => 2,
    ]);

    $mathematiquesNode = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'parent_id' => $sousDomaine->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $mathematiques->id,
        'libelle' => 'Mathématiques',
        'ordre' => 3,
    ]);

    $francaisNode = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'parent_id' => $sousDomaine->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $francais->id,
        'libelle' => 'Français',
        'ordre' => 4,
    ]);

    $semestre = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'SEMESTRE_1',
        'nom' => 'Premier semestre',
        'type_periode' => 'SEMESTRE',
        'ordre' => 1,
        'saisissable' => false,
        'calculable' => true,
    ]);

    $periodeA = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'parent_id' => $semestre->id,
        'code' => 'PERIODE_1',
        'nom' => 'Première période',
        'type_periode' => 'PERIODE',
        'ordre' => 2,
        'saisissable' => true,
        'calculable' => false,
    ]);

    $periodeB = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'parent_id' => $semestre->id,
        'code' => 'PERIODE_2',
        'nom' => 'Deuxième période',
        'type_periode' => 'PERIODE',
        'ordre' => 3,
        'saisissable' => true,
        'calculable' => false,
    ]);

    foreach ([$periodeA, $periodeB] as $periode) {
        SystemTemplateMaximum::query()->create([
            'template_id' => $template->id,
            'node_id' => $mathematiquesNode->id,
            'periode_id' => $periode->id,
            'maxima' => 20,
            'coefficient' => 2,
        ]);
    }

    $signatures = SystemBlocSpecial::query()->create([
        'code' => 'SIGNATURES',
        'nom' => 'Signatures',
    ]);

    SystemTemplateBloc::query()->create([
        'template_id' => $template->id,
        'bloc_id' => $signatures->id,
        'ordre' => 1,
        'actif' => true,
        'configuration' => ['afficher_nom_cours' => true],
    ]);

    return compact(
        'template',
        'cycle',
        'degre',
        'niveauA',
        'niveauB',
        'layout',
        'mathematiques',
        'francais',
        'matieresNode',
        'sousDomaine',
        'mathematiquesNode',
        'francaisNode',
        'semestre',
        'periodeA',
        'periodeB',
    );
}

test('the platform configuration catalog lists configurations with their eligibility', function () {
    $token = makePlatformConfigurationToken();
    makeConfigurationFixture();

    $this->withToken($token)
        ->getJson('/api/v1/platform/configurations-bulletin')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'CTEB_7_FIXTURE')
        ->assertJsonPath('data.0.cycle.nom', 'Éducation de base')
        ->assertJsonPath('data.0.degre.code', 'CTEB')
        ->assertJsonPath('data.0.layout.code', 'RDC_CTEB_DOMAINES')
        ->assertJsonPath('data.0.nodes_count', 4)
        ->assertJsonPath('data.0.periodes_count', 3)
        ->assertJsonPath('data.0.matieres_count', 2)
        ->assertJsonStructure([
            'data' => [[
                'id',
                'code',
                'nom',
                'version',
                'type_decoupage',
                'actif',
                'total_general_maxima',
                'cycle' => ['id', 'code', 'nom'],
                'degre' => ['id', 'code', 'nom'],
                'option',
                'niveau_min',
                'niveau_max',
                'layout' => ['id', 'code', 'nom'],
                'nodes_count',
                'periodes_count',
                'matieres_count',
            ]],
        ]);
});

test('the platform configuration catalog exposes the full composition in detail', function () {
    $token = makePlatformConfigurationToken();
    $fixture = makeConfigurationFixture();
    $template = $fixture['template'];

    $this->withToken($token)
        ->getJson("/api/v1/platform/configurations-bulletin/{$template->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('data.code', 'CTEB_7_FIXTURE')
        ->assertJsonPath('data.niveau_min.nom', '7ème')
        ->assertJsonPath('data.niveau_max.nom', '7ème')
        ->assertJsonCount(4, 'data.nodes')
        ->assertJsonCount(3, 'data.periodes')
        ->assertJsonCount(2, 'data.slots')
        ->assertJsonCount(1, 'data.blocs')
        ->assertJsonPath('data.nodes.0.type_node', 'DOMAINE')
        ->assertJsonPath('data.nodes.2.matiere.code', 'MATH')
        ->assertJsonPath('data.periodes.0.type_periode', 'SEMESTRE')
        ->assertJsonPath('data.periodes.1.parent_id', $fixture['semestre']->id)
        ->assertJsonPath('data.blocs.0.bloc.code', 'SIGNATURES')
        ->assertJsonPath('data.blocs.0.configuration.afficher_nom_cours', true)
        ->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'nom',
                'version',
                'type_decoupage',
                'actif',
                'total_general_maxima',
                'cycle',
                'degre',
                'option',
                'niveau_min',
                'niveau_max',
                'layout' => ['id', 'code', 'nom'],
                'nodes' => [[
                    'id',
                    'parent_id',
                    'type_node',
                    'matiere_id',
                    'libelle',
                    'ordre',
                    'afficher_ligne',
                    'calculer_sous_total',
                    'obligatoire',
                    'matiere',
                ]],
                'periodes' => [[
                    'id',
                    'parent_id',
                    'code',
                    'nom',
                    'type_periode',
                    'ordre',
                    'saisissable',
                    'calculable',
                    'actif',
                ]],
                'slots' => [[
                    'node_id',
                    'periode_id',
                    'maxima',
                    'coefficient',
                ]],
                'blocs' => [[
                    'id',
                    'ordre',
                    'actif',
                    'configuration',
                    'bloc',
                ]],
            ],
        ]);
});

test('an inactive reference is preserved in the configuration catalogue', function () {
    $token = makePlatformConfigurationToken();
    $layout = SystemLayoutBulletin::query()->create([
        'code' => 'RDC_DEPRECIEE',
        'nom' => 'Layout déprécié',
        'actif' => false,
    ]);
    $cycle = RefCycle::query()->create([
        'code' => 'PRIMAIRE',
        'nom' => 'Primaire',
        'ordre' => 1,
        'actif' => true,
    ]);
    SystemTemplateBulletin::query()->create([
        'code' => 'PRIMAIRE_1_V2',
        'nom' => 'Primaire 1ère',
        'cycle_id' => $cycle->id,
        'layout_id' => $layout->id,
        'type_decoupage' => 'TRIMESTRIEL',
        'version' => '2026-2027',
        'actif' => true,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/platform/configurations-bulletin')
        ->assertOk()
        ->assertJsonPath('data.0.layout.nom', 'Layout déprécié');
});

test('unknown and unauthorized callers cannot read the configuration catalog', function () {
    $token = makePlatformConfigurationToken();
    makeConfigurationFixture();

    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'École',
        'slug' => 'ecole-catalogue-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $tenantUser = Utilisateur::factory()->create([
        'tenant_id' => $tenant->id,
        'actif' => true,
    ]);
    $tenantToken = $tenantUser->createToken('tenant-catalogue', ["tenant:{$tenant->id}"])->plainTextToken;

    $this->withToken($tenantToken)
        ->getJson('/api/v1/platform/configurations-bulletin')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->getJson('/api/v1/platform/configurations-bulletin')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->getJson('/api/v1/platform/configurations-bulletin/999999')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function makeConfigurationPayloadForTests(array $attributes = []): array
{
    $cycle = RefCycle::query()->create([
        'code' => 'CYCLE_'.Str::upper(Str::random(4)),
        'nom' => 'Cycle de création',
        'ordre' => 1,
        'actif' => true,
    ]);

    $degre = RefDegre::query()->create([
        'cycle_id' => $cycle->id,
        'code' => 'DEGRE_'.Str::upper(Str::random(4)),
        'nom' => 'Degré de création',
        'ordre' => 1,
    ]);

    $option = RefOption::query()->create([
        'cycle_id' => $cycle->id,
        'code' => 'OPT_'.Str::upper(Str::random(4)),
        'nom' => 'Option de création',
        'actif' => true,
    ]);

    $niveauMin = RefNiveau::query()->create([
        'cycle_id' => $cycle->id,
        'degre_id' => $degre->id,
        'code' => 'NIV_7_'.Str::upper(Str::random(2)),
        'nom' => '7ème création',
        'numero' => 7,
        'ordre' => 1,
    ]);

    $niveauMax = RefNiveau::query()->create([
        'cycle_id' => $cycle->id,
        'degre_id' => $degre->id,
        'code' => 'NIV_8_'.Str::upper(Str::random(2)),
        'nom' => '8ème création',
        'numero' => 8,
        'ordre' => 2,
    ]);

    $layout = SystemLayoutBulletin::query()->create([
        'code' => 'LAYOUT_'.Str::upper(Str::random(4)),
        'nom' => 'Layout de création',
        'actif' => true,
    ]);

    return [
        'code' => 'CFG_'.Str::upper(Str::random(6)),
        'nom' => 'Configuration de création',
        'cycle_id' => $cycle->id,
        'degre_id' => $degre->id,
        'option_id' => $option->id,
        'niveau_min_id' => $niveauMin->id,
        'niveau_max_id' => $niveauMax->id,
        'layout_id' => $layout->id,
        'type_decoupage' => 'SEMESTRIEL',
        'version' => '2026-2027',
        'actif' => true,
        'total_general_maxima' => 3360,
        ...$attributes,
    ];
}

function makeClasseReferencingTemplateForTests(SystemTemplateBulletin $template, RefCycle $cycle, RefNiveau $niveau): Classe
{
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'École Référence',
        'slug' => 'ecole-reference-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $etablissement = Etablissement::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Établissement Référence',
        'actif' => true,
    ]);

    $annee = AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);

    return Classe::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement->id,
        'annee_scolaire_id' => $annee->id,
        'cycle_id' => $cycle->id,
        'niveau_id' => $niveau->id,
        'system_template_id' => $template->id,
        'nom' => 'Classe référence',
        'active' => true,
    ]);
}

test('a platform admin can create a configuration bulletin', function () {
    $token = makePlatformConfigurationToken();
    $payload = makeConfigurationPayloadForTests();

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', $payload)
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('data.code', $payload['code'])
        ->assertJsonPath('data.nom', 'Configuration de création')
        ->assertJsonPath('data.cycle.nom', 'Cycle de création')
        ->assertJsonPath('data.degre.nom', 'Degré de création')
        ->assertJsonPath('data.option.nom', 'Option de création')
        ->assertJsonPath('data.niveau_min.nom', '7ème création')
        ->assertJsonPath('data.niveau_max.nom', '8ème création')
        ->assertJsonPath('data.layout.nom', 'Layout de création')
        ->assertJsonPath('data.type_decoupage', 'SEMESTRIEL')
        ->assertJsonPath('data.actif', true)
        ->assertJsonPath('data.total_general_maxima', 3360)
        ->assertJsonPath('data.nodes_count', 0)
        ->assertJsonPath('data.periodes_count', 0)
        ->assertJsonPath('data.matieres_count', 0)
        ->assertJsonCount(0, 'data.blocs');

    $this->assertDatabaseHas('system_templates_bulletin', [
        'code' => $payload['code'],
        'nom' => 'Configuration de création',
    ]);
});

test('configuration creation is strictly validated', function () {
    $token = makePlatformConfigurationToken();
    $payload = makeConfigurationPayloadForTests();

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', [
            ...$payload,
            'code' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', [
            ...$payload,
            'type_decoupage' => 'BIMESTRIEL',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type_decoupage']);

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', [
            ...$payload,
            'cycle_id' => null,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cycle_id']);

    $otherCycle = RefCycle::query()->create([
        'code' => 'AUTRE_CYCLE',
        'nom' => 'Autre cycle',
        'ordre' => 2,
        'actif' => true,
    ]);
    $foreignDegre = RefDegre::query()->create([
        'cycle_id' => $otherCycle->id,
        'code' => 'DEGRE_ETRANGER',
        'nom' => 'Degré étranger',
        'ordre' => 1,
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', [
            ...$payload,
            'degre_id' => $foreignDegre->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['degre_id']);

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', [
            ...$payload,
            'champ_inconnu' => 'valeur',
        ])
        ->assertUnprocessable();

    $this->assertDatabaseCount('system_templates_bulletin', 0);
});

test('configuration creation rejects duplicate codes', function () {
    $token = makePlatformConfigurationToken();
    $payload = makeConfigurationPayloadForTests();

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', $payload)
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

test('configuration creation enforces the minimum and maximum level ordering', function () {
    $token = makePlatformConfigurationToken();
    $payload = makeConfigurationPayloadForTests();

    $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', [
            ...$payload,
            'niveau_min_id' => $payload['niveau_max_id'],
            'niveau_max_id' => $payload['niveau_min_id'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['niveau_max_id']);
});

test('a platform admin can update a configuration bulletin', function () {
    $token = makePlatformConfigurationToken();
    $createPayload = makeConfigurationPayloadForTests();

    $created = $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', $createPayload)
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$created['id']}", [
            ...$createPayload,
            'nom' => 'Configuration renommée',
            'actif' => false,
            'version' => '2027-2028',
            'total_general_maxima' => 3600,
        ])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('data.id', $created['id'])
        ->assertJsonPath('data.code', $createPayload['code'])
        ->assertJsonPath('data.nom', 'Configuration renommée')
        ->assertJsonPath('data.actif', false)
        ->assertJsonPath('data.version', '2027-2028')
        ->assertJsonPath('data.total_general_maxima', 3600)
        ->assertJsonPath('data.cycle.nom', 'Cycle de création');

    $this->assertDatabaseHas('system_templates_bulletin', [
        'id' => $created['id'],
        'nom' => 'Configuration renommée',
        'actif' => false,
        'version' => '2027-2028',
    ]);
});

test('a platform admin can delete an unreferenced configuration bulletin', function () {
    $token = makePlatformConfigurationToken();
    $payload = makeConfigurationPayloadForTests();

    $created = $this->withToken($token)
        ->postJson('/api/v1/platform/configurations-bulletin', $payload)
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$created['id']}")
        ->assertNoContent();

    $this->assertDatabaseMissing('system_templates_bulletin', ['id' => $created['id']]);
});

test('deleting a referenced configuration bulletin returns a conflict', function () {
    $token = makePlatformConfigurationToken();
    $fixture = makeConfigurationFixture();
    makeClasseReferencingTemplateForTests($fixture['template'], $fixture['cycle'], $fixture['niveauA']);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$fixture['template']->id}")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Conflict')
        ->assertJsonPath('status', 409);

    $this->assertDatabaseHas('system_templates_bulletin', ['id' => $fixture['template']->id]);
});

test('deleting a configuration used by a model returns a conflict', function () {
    $token = makePlatformConfigurationToken();
    $fixture = makeConfigurationFixture();
    $classe = makeClasseReferencingTemplateForTests($fixture['template'], $fixture['cycle'], $fixture['niveauA']);

    ModeleBulletin::query()->create([
        'tenant_id' => $classe->tenant_id,
        'classe_id' => $classe->id,
        'annee_scolaire_id' => $classe->annee_scolaire_id,
        'system_template_id' => $fixture['template']->id,
        'layout_id' => $fixture['layout']->id,
        'nom' => 'Modèle conflictuel',
        'type_decoupage' => 'SEMESTRIEL',
        'verrouille' => false,
        'actif' => true,
    ]);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$fixture['template']->id}")
        ->assertStatus(409)
        ->assertJsonPath('status', 409);

    $this->assertDatabaseHas('system_templates_bulletin', ['id' => $fixture['template']->id]);
});

test('deleting a published configuration bulletin returns a conflict', function () {
    $token = makePlatformConfigurationToken();
    $fixture = makeConfigurationFixture();
    $fixture['template']->update(['statut' => TemplateStatut::PUBLIE]);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$fixture['template']->id}")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Conflict')
        ->assertJsonPath('status', 409);

    $this->assertDatabaseHas('system_templates_bulletin', ['id' => $fixture['template']->id]);
});

test('deleting a deprecated configuration bulletin returns a conflict', function () {
    $token = makePlatformConfigurationToken();
    $fixture = makeConfigurationFixture();
    $fixture['template']->update(['statut' => TemplateStatut::DEPRECIE]);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$fixture['template']->id}")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Conflict')
        ->assertJsonPath('status', 409);

    $this->assertDatabaseHas('system_templates_bulletin', ['id' => $fixture['template']->id]);
});

test('purging a deprecated configuration bulletin removes it and its composition', function () {
    $token = makePlatformConfigurationToken();
    $fixture = makeConfigurationFixture();
    $fixture['template']->update(['statut' => TemplateStatut::DEPRECIE]);

    $templateId = $fixture['template']->id;

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$templateId}/purge")
        ->assertNoContent();

    $this->assertDatabaseMissing('system_templates_bulletin', ['id' => $templateId]);
    $this->assertDatabaseMissing('system_template_nodes', ['template_id' => $templateId]);
    $this->assertDatabaseMissing('system_template_periodes', ['template_id' => $templateId]);
    $this->assertDatabaseMissing('system_template_blocs', ['template_id' => $templateId]);
    $this->assertDatabaseMissing('system_template_maxima', ['template_id' => $templateId]);
});

test('purging a configuration that is not deprecated returns a conflict', function (string $statut) {
    $token = makePlatformConfigurationToken();
    $fixture = makeConfigurationFixture();
    $fixture['template']->update(['statut' => $statut]);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$fixture['template']->id}/purge")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Conflict')
        ->assertJsonPath('status', 409);

    $this->assertDatabaseHas('system_templates_bulletin', ['id' => $fixture['template']->id]);
})->with([
    TemplateStatut::BROUILLON->value,
    TemplateStatut::VALIDE->value,
    TemplateStatut::PUBLIE->value,
]);

test('purging a deprecated configuration referenced by a class returns a conflict', function () {
    $token = makePlatformConfigurationToken();
    $fixture = makeConfigurationFixture();
    $fixture['template']->update(['statut' => TemplateStatut::DEPRECIE]);

    makeClasseReferencingTemplateForTests($fixture['template'], $fixture['cycle'], $fixture['niveauA']);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$fixture['template']->id}/purge")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Conflict')
        ->assertJsonPath('status', 409);

    $this->assertDatabaseHas('system_templates_bulletin', ['id' => $fixture['template']->id]);
});

test('purging requires an authenticated platform context', function () {
    $fixture = makeConfigurationFixture();
    $fixture['template']->update(['statut' => TemplateStatut::DEPRECIE]);

    $this->deleteJson("/api/v1/platform/configurations-bulletin/{$fixture['template']->id}/purge")
        ->assertUnauthorized();

    $this->assertDatabaseHas('system_templates_bulletin', ['id' => $fixture['template']->id]);
});

test('platform layouts are listed for the configuration form', function () {
    $token = makePlatformConfigurationToken();

    SystemLayoutBulletin::query()->create([
        'code' => 'RDC_TRIMESTRIEL',
        'nom' => 'RDC trimestriel',
        'actif' => true,
    ]);
    SystemLayoutBulletin::query()->create([
        'code' => 'RDC_SEMESTRIEL',
        'nom' => 'RDC semestriel',
        'actif' => true,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/platform/layouts-bulletin')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [[
                'id',
                'code',
                'nom',
            ]],
        ]);
});

test('write operations on the configuration catalog require a platform context', function () {
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'École écriture',
        'slug' => 'ecole-ecriture-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $tenantUser = Utilisateur::factory()->create([
        'tenant_id' => $tenant->id,
        'actif' => true,
    ]);
    $tenantToken = $tenantUser->createToken('tenant-ecriture', ["tenant:{$tenant->id}"])->plainTextToken;

    $payload = makeConfigurationPayloadForTests();

    $this->withToken($tenantToken)
        ->postJson('/api/v1/platform/configurations-bulletin', $payload)
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->getJson('/api/v1/platform/configurations-bulletin')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});
