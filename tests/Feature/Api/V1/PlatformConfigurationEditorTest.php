<?php

use App\Models\RefCycle;
use App\Models\RefDegre;
use App\Models\RefMatiere;
use App\Models\RefNiveau;
use App\Models\RolePlateforme;
use App\Models\SystemBlocSpecial;
use App\Models\SystemLayoutBulletin;
use App\Models\SystemTemplateBulletin;
use App\Models\SystemTemplateNode;
use App\Models\SystemTemplatePeriode;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function makeEditorToken(): string
{
    RolePlateforme::query()->updateOrCreate(
        ['code' => 'ADMIN_PLATEFORME'],
        ['nom' => 'Administrateur plateforme'],
    );

    $utilisateur = UtilisateurPlateforme::factory()->create([
        'email' => 'editor-admin@plateforme.test',
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);

    $utilisateur->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);

    return $utilisateur->createToken('editor-test', ['context:platform'])->plainTextToken;
}

/**
 * À dessein, aucune matière/période n’est ajoutée : chaque test compose sa configuration.
 *
 * @return array{template: SystemTemplateBulletin, cycle: RefCycle, layout: SystemLayoutBulletin, matiere: RefMatiere}
 */
function makeEditorFixture(): array
{
    $cycle = RefCycle::query()->create([
        'code' => 'EDITOR_EB',
        'nom' => 'Éducation de base',
        'ordre' => 1,
        'actif' => true,
    ]);

    $degre = RefDegre::query()->create([
        'cycle_id' => $cycle->id,
        'code' => 'EDITOR_CTEB',
        'nom' => 'CTEB',
        'ordre' => 1,
    ]);

    $niveau = RefNiveau::query()->create([
        'cycle_id' => $cycle->id,
        'degre_id' => $degre->id,
        'code' => 'EDITOR_EB_7',
        'nom' => '7ème',
        'numero' => 7,
        'ordre' => 1,
    ]);

    $matiere = RefMatiere::query()->create([
        'code' => 'EDITOR_MATH',
        'nom' => 'Mathématiques',
        'actif' => true,
    ]);

    $layout = SystemLayoutBulletin::query()->create([
        'code' => 'EDITOR_LAYOUT',
        'nom' => 'Layout éditeur',
        'actif' => true,
    ]);

    $template = SystemTemplateBulletin::query()->create([
        'code' => 'EDITOR_CONFIG',
        'nom' => 'Configuration éditeur',
        'cycle_id' => $cycle->id,
        'degre_id' => $degre->id,
        'niveau_min_id' => $niveau->id,
        'niveau_max_id' => $niveau->id,
        'layout_id' => $layout->id,
        'type_decoupage' => 'SEMESTRIEL',
        'total_general_maxima' => 3360,
        'version' => '2026-2027',
        'actif' => false,
    ]);

    return compact('template', 'cycle', 'layout', 'matiere');
}

test('a platform admin can build the node tree of a configuration', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $domaine = $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes", [
            'type_node' => 'DOMAINE',
            'libelle' => 'Domaine des compétences',
            'ordre' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.statut', 'BROUILLON')
        ->json('data');

    $domaineId = $domaine['nodes'][0]['id'];

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes", [
            'type_node' => 'SOUS_DOMAINE',
            'parent_id' => $domaineId,
            'libelle' => 'Outils de base',
            'ordre' => 2,
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data.nodes');

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes", [
            'type_node' => 'MATIERE',
            'parent_id' => $domaineId,
            'matiere_id' => $fixture['matiere']->id,
            'libelle' => 'Mathématiques',
            'ordre' => 3,
        ])
        ->assertOk()
        ->assertJsonCount(3, 'data.nodes')
        ->assertJsonPath('data.matieres_count', 1)
        ->assertJsonPath('data.nodes.2.matiere.code', 'EDITOR_MATH');

    $this->assertDatabaseCount('system_template_nodes', 3);
});

test('node validation is strict', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes", [
            'type_node' => 'MATIERE',
            'libelle' => 'Matière sans matière',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['matiere_id']);

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes", [
            'type_node' => 'DOMAINE',
            'matiere_id' => $fixture['matiere']->id,
            'libelle' => 'Matière sur un domaine',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['matiere_id']);

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes", [
            'type_node' => 'INCONNU',
            'libelle' => 'Type inconnu',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type_node']);

    $this->assertDatabaseCount('system_template_nodes', 0);
});

test('a node cannot be attached to another configuration', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $otherTemplate = SystemTemplateBulletin::query()->create([
        'code' => 'EDITOR_OTHER',
        'nom' => 'Autre configuration',
        'cycle_id' => $fixture['cycle']->id,
        'layout_id' => $fixture['layout']->id,
        'type_decoupage' => 'TRIMESTRIEL',
        'version' => '2026-2027',
        'actif' => false,
    ]);

    $foreignNode = SystemTemplateNode::query()->create([
        'template_id' => $otherTemplate->id,
        'type_node' => 'DOMAINE',
        'libelle' => 'Domaine étranger',
        'ordre' => 1,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$fixture['template']->id}/nodes", [
            'type_node' => 'SOUS_DOMAINE',
            'parent_id' => $foreignNode->id,
            'libelle' => 'Sous-domaine étranger',
            'ordre' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('a platform admin can update and reorder a node', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $node = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes/{$node->id}", [
            'type_node' => 'MATIERE',
            'matiere_id' => $fixture['matiere']->id,
            'libelle' => 'Math profondes',
            'ordre' => 5,
        ])
        ->assertOk()
        ->assertJsonPath('data.nodes.0.libelle', 'Math profondes')
        ->assertJsonPath('data.nodes.0.ordre', 5);
});

test('a node cannot be moved under its own descendant', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $parent = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'DOMAINE',
        'libelle' => 'Racine',
        'ordre' => 1,
    ]);

    $child = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'parent_id' => $parent->id,
        'type_node' => 'SOUS_DOMAINE',
        'libelle' => 'Enfant',
        'ordre' => 2,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes/{$parent->id}", [
            'type_node' => 'DOMAINE',
            'parent_id' => $child->id,
            'libelle' => 'Racine',
            'ordre' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('a platform admin can delete a node and its subtree', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $parent = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'DOMAINE',
        'libelle' => 'Racine',
        'ordre' => 1,
    ]);

    SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'parent_id' => $parent->id,
        'type_node' => 'SOUS_DOMAINE',
        'libelle' => 'Enfant',
        'ordre' => 2,
    ]);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes/{$parent->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.nodes');

    $this->assertDatabaseCount('system_template_nodes', 0);
});

test('a platform admin can build the period hierarchy', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $semestre = $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/periodes", [
            'code' => 'SEMESTRE_1',
            'nom' => 'Premier semestre',
            'type_periode' => 'SEMESTRE',
            'ordre' => 1,
            'saisissable' => false,
            'calculable' => true,
        ])
        ->assertOk()
        ->json('data');

    $semestreId = $semestre['periodes'][0]['id'];

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/periodes", [
            'code' => 'PERIODE_1',
            'nom' => 'Première période',
            'type_periode' => 'PERIODE',
            'parent_id' => $semestreId,
            'ordre' => 2,
            'saisissable' => true,
            'calculable' => false,
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data.periodes')
        ->assertJsonPath('data.periodes.1.parent_id', $semestreId);

    $this->assertDatabaseCount('system_template_periodes', 2);
});

test('period validation enforces unique codes and parent type', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/periodes", [
            'code' => 'PERIODE_1',
            'nom' => 'Première période',
            'type_periode' => 'PERIODE',
            'ordre' => 1,
        ])
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/periodes", [
            'code' => 'PERIODE_1',
            'nom' => 'Doublon',
            'type_periode' => 'PERIODE',
            'ordre' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/periodes", [
            'code' => 'PERIODE_2',
            'nom' => 'Deuxième période',
            'type_periode' => 'PERIODE',
            'ordre' => 2,
            'parent_id' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('a platform admin can update and delete a period', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $periode = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Première période',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/periodes/{$periode->id}", [
            'code' => 'PERIODE_1',
            'nom' => 'Premier relevé',
            'type_periode' => 'PERIODE',
            'ordre' => 1,
            'saisissable' => true,
            'calculable' => false,
            'actif' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.periodes.0.nom', 'Premier relevé')
        ->assertJsonPath('data.periodes.0.actif', false);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$template->id}/periodes/{$periode->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.periodes');

    $this->assertDatabaseCount('system_template_periodes', 0);
});

test('a platform admin can replace the maxima grid in one operation', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $node = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $periodeA = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Période 1',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $periodeB = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_2',
        'nom' => 'Période 2',
        'type_periode' => 'PERIODE',
        'ordre' => 2,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/maxima", [
            'slots' => [
                ['node_id' => $node->id, 'periode_id' => $periodeA->id, 'maxima' => 20, 'coefficient' => 2],
                ['node_id' => $node->id, 'periode_id' => $periodeB->id, 'maxima' => 30, 'coefficient' => 2],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data.slots');

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/maxima", [
            'slots' => [
                ['node_id' => $node->id, 'periode_id' => $periodeA->id, 'maxima' => 25, 'coefficient' => 3],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data.slots')
        ->assertJsonPath('data.slots.0.maxima', 25);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/maxima", [
            'slots' => [
                ['node_id' => $node->id, 'periode_id' => $periodeA->id, 'maxima' => null],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(0, 'data.slots');

    $this->assertDatabaseCount('system_template_maxima', 0);
});

test('maxima validation rejects foreign nodes and duplicates', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $otherTemplate = SystemTemplateBulletin::query()->create([
        'code' => 'EDITOR_OTHER',
        'nom' => 'Autre configuration',
        'cycle_id' => $fixture['cycle']->id,
        'layout_id' => $fixture['layout']->id,
        'type_decoupage' => 'SEMESTRIEL',
    ]);

    $foreignNode = SystemTemplateNode::query()->create([
        'template_id' => $otherTemplate->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Matière d’ailleurs',
        'ordre' => 1,
    ]);

    $node = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $periode = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Période 1',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/maxima", [
            'slots' => [
                ['node_id' => $foreignNode->id, 'periode_id' => $periode->id, 'maxima' => 20, 'coefficient' => 2],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slots.0.node_id']);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/maxima", [
            'slots' => [
                ['node_id' => $node->id, 'periode_id' => $periode->id, 'maxima' => 20, 'coefficient' => 1],
                ['node_id' => $node->id, 'periode_id' => $periode->id, 'maxima' => 18, 'coefficient' => 1],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slots']);
});

test('a platform admin can attach, configure and detach special blocs', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $signatures = SystemBlocSpecial::query()->create([
        'code' => 'SIGNATURES',
        'nom' => 'Signatures',
    ]);

    $attached = $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/blocs", [
            'bloc_id' => $signatures->id,
            'ordre' => 1,
            'actif' => true,
            'configuration' => ['afficher_nom_cours' => true],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data.blocs')
        ->assertJsonPath('data.blocs.0.bloc.code', 'SIGNATURES')
        ->json('data');

    $templateBlocId = $attached['blocs'][0]['id'];

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/blocs", [
            'bloc_id' => $signatures->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bloc_id']);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/blocs/{$templateBlocId}", [
            'ordre' => 3,
            'actif' => false,
            'configuration' => ['afficher_nom_cours' => false],
        ])
        ->assertOk()
        ->assertJsonPath('data.blocs.0.actif', false);

    $this->withToken($token)
        ->deleteJson("/api/v1/platform/configurations-bulletin/{$template->id}/blocs/{$templateBlocId}")
        ->assertOk()
        ->assertJsonCount(0, 'data.blocs');

    $this->assertDatabaseCount('system_template_blocs', 0);
});

test('special blocs are listed for the configuration editor', function () {
    $token = makeEditorToken();

    SystemBlocSpecial::query()->create(['code' => 'REPECHAGE', 'nom' => 'Repêchage']);
    SystemBlocSpecial::query()->create(['code' => 'JURY', 'nom' => 'Jury']);

    $this->withToken($token)
        ->getJson('/api/v1/platform/blocs-speciaux')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [[
                'id',
                'code',
                'nom',
            ]],
        ]);
});

test('an empty configuration cannot be validated', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/validation")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['composition']);

    $this->assertDatabaseHas('system_templates_bulletin', [
        'id' => $template->id,
        'statut' => 'BROUILLON',
    ]);
});

test('a complete configuration can be validated but not yet published', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $node = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $periode = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Période 1',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $template->maxima()->create([
        'node_id' => $node->id,
        'periode_id' => $periode->id,
        'maxima' => 20,
        'coefficient' => 1,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/publication")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/validation")
        ->assertOk()
        ->assertJsonPath('data.statut', 'VALIDE');
});

test('a missing maxima slot is reported as a blocker', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Période 1',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/validation")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['composition'])
        ->json('errors.composition');

    expect($response)->toContain('Barème incomplet pour la matière « Mathématiques » (périodes : Période 1).');
});

test('a validated configuration can be published', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $node = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $periode = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Période 1',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $template->maxima()->create([
        'node_id' => $node->id,
        'periode_id' => $periode->id,
        'maxima' => 20,
        'coefficient' => 1,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/validation")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/publication")
        ->assertOk()
        ->assertJsonPath('data.statut', 'PUBLIE')
        ->assertJsonPath('data.actif', true)
        ->assertJsonPath('data.publie_a', fn (string $date): bool => $date !== null);

    $this->assertDatabaseHas('system_templates_bulletin', [
        'id' => $template->id,
        'statut' => 'PUBLIE',
        'actif' => true,
    ]);
});

test('a published configuration is immutable', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $node = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $periode = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Période 1',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $template->maxima()->create([
        'node_id' => $node->id,
        'periode_id' => $periode->id,
        'maxima' => 20,
        'coefficient' => 1,
    ]);

    $template->update(['statut' => 'VALIDE']);
    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/publication")
        ->assertOk();

    $bloc = SystemBlocSpecial::query()->create([
        'code' => 'REPECHAGE',
        'nom' => 'Repêchage',
    ]);

    $base = "/api/v1/platform/configurations-bulletin/{$template->id}";

    $this->withToken($token)
        ->postJson("{$base}/nodes", ['type_node' => 'DOMAINE', 'libelle' => 'Bloqué', 'ordre' => 9])
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->withToken($token)
        ->postJson("{$base}/periodes", [
            'code' => 'PERIODE_2', 'nom' => 'Bloquée', 'type_periode' => 'PERIODE', 'ordre' => 9,
        ])
        ->assertStatus(409);

    $this->withToken($token)
        ->putJson("{$base}/maxima", [
            'slots' => [
                ['node_id' => $node->id, 'periode_id' => $periode->id, 'maxima' => 20, 'coefficient' => 1],
            ],
        ])
        ->assertStatus(409);

    $this->withToken($token)
        ->postJson("{$base}/blocs", ['bloc_id' => $bloc->id])
        ->assertStatus(409);

    $this->withToken($token)
        ->putJson($base, [
            ...$template->only(['code', 'nom', 'cycle_id', 'layout_id', 'type_decoupage']),
            'code' => 'EDITOR_CONFIG_V2',
            'nom' => 'Renommée',
        ])
        ->assertStatus(409);

    $this->withToken($token)
        ->postJson("{$base}/validation")
        ->assertStatus(409);
});

test('a published configuration can be deprecated and reopened', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $node = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $periode = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Période 1',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $template->maxima()->create([
        'node_id' => $node->id,
        'periode_id' => $periode->id,
        'maxima' => 20,
        'coefficient' => 1,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/validation")
        ->assertOk();
    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/publication")
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/depreciation")
        ->assertOk()
        ->assertJsonPath('data.statut', 'DEPRECIE')
        ->assertJsonPath('data.actif', false);

    $this->assertDatabaseHas('system_templates_bulletin', [
        'id' => $template->id,
        'statut' => 'DEPRECIE',
        'actif' => false,
    ]);
});

test('a validated configuration can return to draft for revision', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $node = SystemTemplateNode::query()->create([
        'template_id' => $template->id,
        'type_node' => 'MATIERE',
        'matiere_id' => $fixture['matiere']->id,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $periode = SystemTemplatePeriode::query()->create([
        'template_id' => $template->id,
        'code' => 'PERIODE_1',
        'nom' => 'Période 1',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);

    $template->maxima()->create([
        'node_id' => $node->id,
        'periode_id' => $periode->id,
        'maxima' => 20,
        'coefficient' => 1,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/validation")
        ->assertOk()
        ->assertJsonPath('data.statut', 'VALIDE');

    $this->withToken($token)
        ->postJson("/api/v1/platform/configurations-bulletin/{$template->id}/retour-brouillon")
        ->assertOk()
        ->assertJsonPath('data.statut', 'BROUILLON');
});

test('a brouillon configuration is listed with its status', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();

    $this->withToken($token)
        ->getJson('/api/v1/platform/configurations-bulletin')
        ->assertOk()
        ->assertJsonPath('data.0.statut', 'BROUILLON')
        ->assertJsonStructure([
            'data' => [[
                'statut',
                'publie_a',
            ]],
        ]);
});

test('an editor cannot mutate another template through a node endpoint', function () {
    $token = makeEditorToken();
    $fixture = makeEditorFixture();
    $template = $fixture['template'];

    $other = SystemTemplateBulletin::query()->create([
        'code' => 'EDITOR_OWN',
        'nom' => 'Autre',
        'cycle_id' => $fixture['cycle']->id,
        'layout_id' => $fixture['layout']->id,
        'type_decoupage' => 'TRIMESTRIEL',
        'version' => '2026-2027',
        'actif' => false,
    ]);

    $foreignNode = SystemTemplateNode::query()->create([
        'template_id' => $other->id,
        'type_node' => 'DOMAINE',
        'libelle' => 'Domaine étranger',
        'ordre' => 1,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/configurations-bulletin/{$template->id}/nodes/{$foreignNode->id}", [
            'type_node' => 'DOMAINE',
            'libelle' => 'Intruse',
            'ordre' => 1,
        ])
        ->assertNotFound();
});
