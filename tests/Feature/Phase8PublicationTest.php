<?php

use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Domains\Bulletin\Services\ProclamationService;
use App\Domains\Bulletin\Services\PublishBulletinCalculationService;
use App\Domains\Bulletin\Services\RankBulletinsService;
use App\Domains\Bulletin\Services\ResolveMentionDecisionService;
use App\Models\AnneeScolaire;
use App\Models\AuditLog;
use App\Models\BaremeMention;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\Proclamation;
use App\Models\ProclamationLigne;
use App\Models\Publication;
use App\Models\Rectification;
use App\Models\ResultatBulletin;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $cycleId = DB::table('ref_cycles')->insertGetId([
        'code' => 'HUMANITES',
        'nom' => 'Humanités',
        'ordre' => 4,
    ]);

    $degreId = DB::table('ref_degres')->insertGetId([
        'cycle_id' => $cycleId,
        'code' => 'HUMANITES_GENERALES',
        'nom' => 'Humanités générales',
        'ordre' => 1,
    ]);

    $niveauId = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId,
        'degre_id' => $degreId,
        'code' => 'HUM_1',
        'nom' => '1ère Humanités',
        'numero' => 1,
        'ordre' => 1,
    ]);

    $layoutId = DB::table('system_layouts_bulletin')->insertGetId([
        'code' => 'TEST_LAYOUT_8',
        'nom' => 'Layout Test Phase 8',
        'vue_blade' => 'test',
        'actif' => true,
    ]);

    $templateId = DB::table('system_templates_bulletin')->insertGetId([
        'code' => 'TEST_TEMPLATE_PHASE8',
        'nom' => 'Template Phase 8 Test',
        'cycle_id' => $cycleId,
        'degre_id' => $degreId,
        'layout_id' => $layoutId,
        'type_decoupage' => 'TRIMESTRIEL',
        'total_general_maxima' => 20.00,
        'actif' => true,
    ]);

    $this->tenant = Tenant::create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École Test',
        'slug' => 'ecole-test-phase8',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $this->etablissement = Etablissement::create([
        'tenant_id' => $this->tenant->id,
        'nom' => 'Complexe Scolaire Test',
        'code_ministere' => 'CST8',
        'province' => 'Kinshasa',
        'ville' => 'Kinshasa',
    ]);

    $this->role = Role::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'ADMIN',
        'nom' => 'Administrateur',
    ]);

    $this->user = Utilisateur::create([
        'tenant_id' => $this->tenant->id,
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Admin',
        'postnom' => 'Test',
        'prenom' => 'User',
        'telephone' => '+243000000002',
        'email' => 'admin-phase8@test.com',
        'mot_de_passe' => Hash::make('password123'),
        'actif' => true,
    ]);

    $this->user->roles()->attach($this->role->id);

    $calendrierId = DB::table('calendriers_scolaires')->insertGetId([
        'code' => 'RDC_TEST_PHASE8',
        'libelle' => 'Calendrier Test Phase 8',
        'pays' => 'RDC',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'officiel' => true,
        'actif' => true,
    ]);

    $this->anneeScolaire = AnneeScolaire::create([
        'calendrier_scolaire_id' => $calendrierId,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
    ]);

    $this->classe = Classe::create([
        'tenant_id' => $this->tenant->id,
        'etablissement_id' => $this->etablissement->id,
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'cycle_id' => $cycleId,
        'niveau_id' => $niveauId,
        'degre_id' => $degreId,
        'system_template_id' => $templateId,
        'nom' => '1ère Commerciale Gestion',
        'active' => true,
    ]);

    $this->modeleBulletin = ModeleBulletin::create([
        'tenant_id' => $this->tenant->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'system_template_id' => $templateId,
        'layout_id' => $layoutId,
        'nom' => 'Modèle Annuel',
        'type_decoupage' => 'TRIMESTRIEL',
        'total_general_maxima' => 20.00,
        'verrouille' => false,
        'actif' => true,
    ]);

    $systemPeriodeId = DB::table('system_template_periodes')->insertGetId([
        'template_id' => $templateId,
        'code' => 'T1',
        'nom' => 'Trimestre 1',
        'type_periode' => 'TRIMESTRE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => true,
        'actif' => true,
    ]);

    $this->periode = ModelePeriode::create([
        'tenant_id' => $this->tenant->id,
        'modele_bulletin_id' => $this->modeleBulletin->id,
        'system_periode_id' => $systemPeriodeId,
        'code' => 'T1',
        'nom' => 'Trimestre 1',
        'type_periode' => 'TRIMESTRE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => true,
        'actif' => true,
    ]);

    $systemNodeId = DB::table('system_template_nodes')->insertGetId([
        'template_id' => $templateId,
        'type_node' => 'MATIERE',
        'libelle' => 'Mathématiques',
        'ordre' => 1,
        'obligatoire' => true,
    ]);

    $this->node = ModeleNode::create([
        'tenant_id' => $this->tenant->id,
        'modele_bulletin_id' => $this->modeleBulletin->id,
        'system_node_id' => $systemNodeId,
        'type_node' => 'MATIERE',
        'libelle' => 'Mathématiques',
        'ordre' => 1,
        'obligatoire' => true,
    ]);

    $this->maximum = ModeleMaximum::create([
        'tenant_id' => $this->tenant->id,
        'modele_bulletin_id' => $this->modeleBulletin->id,
        'node_id' => $this->node->id,
        'periode_id' => $this->periode->id,
        'maxima' => 20.00,
        'coefficient' => 1.00,
    ]);

    $this->eleve1 = Eleve::create([
        'tenant_id' => $this->tenant->id,
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Dupont',
        'postnom' => 'Jean',
        'prenom' => 'Marie',
        'sexe' => 'F',
        'date_naissance' => '2008-01-15',
        'lieu_naissance' => 'Kinshasa',
    ]);

    $this->eleve2 = Eleve::create([
        'tenant_id' => $this->tenant->id,
        'etablissement_id' => $this->etablissement->id,
        'nom' => 'Martin',
        'postnom' => 'Pierre',
        'prenom' => 'Paul',
        'sexe' => 'M',
        'date_naissance' => '2008-03-20',
        'lieu_naissance' => 'Lubumbashi',
    ]);

    $this->inscription1 = Inscription::create([
        'tenant_id' => $this->tenant->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'eleve_id' => $this->eleve1->id,
        'statut' => 'ACTIF',
    ]);

    $this->inscription2 = Inscription::create([
        'tenant_id' => $this->tenant->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'eleve_id' => $this->eleve2->id,
        'statut' => 'ACTIF',
    ]);

    $this->bulletin1 = Bulletin::create([
        'tenant_id' => $this->tenant->id,
        'inscription_id' => $this->inscription1->id,
        'classe_id' => $this->classe->id,
        'modele_bulletin_id' => $this->modeleBulletin->id,
    ]);

    $this->bulletin2 = Bulletin::create([
        'tenant_id' => $this->tenant->id,
        'inscription_id' => $this->inscription2->id,
        'classe_id' => $this->classe->id,
        'modele_bulletin_id' => $this->modeleBulletin->id,
    ]);

    $this->note1 = Note::create([
        'tenant_id' => $this->tenant->id,
        'bulletin_id' => $this->bulletin1->id,
        'node_id' => $this->node->id,
        'periode_id' => $this->periode->id,
        'valeur' => 16.00,
        'statut_resultat' => 'SAISIE',
    ]);

    $this->note2 = Note::create([
        'tenant_id' => $this->tenant->id,
        'bulletin_id' => $this->bulletin2->id,
        'node_id' => $this->node->id,
        'periode_id' => $this->periode->id,
        'valeur' => 12.00,
        'statut_resultat' => 'SAISIE',
    ]);

    BaremeMention::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $cycleId,
        'system_template_id' => $templateId,
        'min_pourcentage' => 0.00,
        'max_pourcentage' => 49.99,
        'mention' => 'INSUFFISANT',
        'decision_defaut' => 'ECHOUE',
        'ordre' => 1,
        'actif' => true,
    ]);

    BaremeMention::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $cycleId,
        'system_template_id' => $templateId,
        'min_pourcentage' => 50.00,
        'max_pourcentage' => 100.00,
        'mention' => 'PASSABLE',
        'decision_defaut' => 'PASSE',
        'ordre' => 2,
        'actif' => true,
    ]);
});

test('resolve mention decision service resolves correctly', function () {
    $bulletin = $this->bulletin1;
    $bulletin->update(['pourcentage' => 80.00]);

    $service = new ResolveMentionDecisionService;
    $result = $service->resolve(
        $this->classe,
        $this->modeleBulletin,
        $bulletin,
    );

    expect($result['mention'])->toBe('PASSABLE');
    expect($result['decision'])->toBe('PASSE');
});

test('resolve mention decision returns null for null percentage', function () {
    $service = new ResolveMentionDecisionService;
    $result = $service->resolve(
        $this->classe,
        $this->modeleBulletin,
        $this->bulletin1,
    );

    expect($result['mention'])->toBeNull();
    expect($result['decision'])->toBeNull();
});

test('rank bulletins service ranks correctly with ex-aequo', function () {
    $this->bulletin1->update(['pourcentage' => 80.00]);
    $this->bulletin2->update(['pourcentage' => 80.00]);

    $service = new RankBulletinsService;
    $rankings = $service->rank($this->classe, $this->periode);

    expect($rankings)->toHaveCount(2);
    expect($rankings[$this->bulletin1->getKey()])->toBe(1);
    expect($rankings[$this->bulletin2->getKey()])->toBe(1);

    $this->bulletin1->refresh();
    $this->bulletin2->refresh();

    expect($this->bulletin1->rang)->toBe(1);
    expect($this->bulletin2->rang)->toBe(1);
    expect($this->bulletin1->nombre_eleves_classe)->toBe(2);
});

test('rank bulletins service ranks correctly with different percentages', function () {
    $this->bulletin1->update(['pourcentage' => 80.00]);
    $this->bulletin2->update(['pourcentage' => 60.00]);

    $service = new RankBulletinsService;
    $rankings = $service->rank($this->classe, $this->periode);

    expect($rankings[$this->bulletin1->getKey()])->toBe(1);
    expect($rankings[$this->bulletin2->getKey()])->toBe(2);
});

test('publish bulletin service persists calculation results', function () {
    $service = app(PublishBulletinCalculationService::class);

    $result = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $this->bulletin1->refresh();

    expect($this->bulletin1->total_obtenu)->not->toBeNull();
    expect($this->bulletin1->total_maxima)->not->toBeNull();
    expect($this->bulletin1->pourcentage)->not->toBeNull();
    expect($this->bulletin1->calcule_at)->not->toBeNull();
    expect($this->bulletin1->verrouille)->toBeTrue();
    expect($this->bulletin1->mention)->toBe('PASSABLE');
    expect($this->bulletin1->decision_proposee)->toBe('PASSE');
    expect($this->bulletin1->decision_statut)->toBe('EN_COURS');
    expect($this->bulletin1->rang)->toBe(1);

    expect($result->configurationHash)->not->toBeEmpty();
    expect($result->inputHash)->not->toBeEmpty();
    expect($result->resultHash)->not->toBeEmpty();
});

test('publish bulletin service creates audit log', function () {
    $service = app(PublishBulletinCalculationService::class);

    $result = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $auditLog = AuditLog::query()
        ->where('action', 'BULLETIN_PUBLIE')
        ->where('table_cible', 'publications')
        ->where('ligne_cible_id', $result->publicationId)
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->tenant_id)->toBe($this->tenant->id);
    expect($auditLog->utilisateur_id)->toBe($this->user->getKey());
    expect($auditLog->nouvelles_valeurs)->toHaveKeys([
        'total_obtenu', 'total_maxima', 'pourcentage',
        'mention', 'decision_proposee', 'configuration_hash',
        'input_hash', 'result_hash', 'engine_version',
    ]);
});

test('publish service stores an immutable snapshot and an official publication', function () {
    $service = app(PublishBulletinCalculationService::class);

    $result = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $snapshot = ResultatBulletin::query()
        ->where('bulletin_id', $this->bulletin1->getKey())
        ->first();
    $publication = Publication::query()->first();

    expect($snapshot)->not->toBeNull();
    expect($publication)->not->toBeNull();
    expect($result->publicationId)->toBe((int) $publication->getKey());
    expect($snapshot->version)->toBe(1);
    expect($snapshot->result_hash)->toBe($result->resultHash);
    expect($snapshot->payload['input'])->toBeArray();
    expect($snapshot->payload['result']['decision_statut'])->toBe('EN_COURS');
    expect($snapshot->decision)->toBe('PASSE');
    expect($publication->checksum)->toBe($result->resultHash);
    expect($publication->statut)->toBe('PUBLIEE');
    expect($publication->publiee_par)->toBe($this->user->getKey());
});

test('re-publishing unchanged results is idempotent and writes no extra audit', function () {
    $service = app(PublishBulletinCalculationService::class);

    $first = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );
    $second = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    expect($second->publicationId)->toBe($first->publicationId);
    expect($second->snapshotVersion)->toBe($first->snapshotVersion);
    expect(ResultatBulletin::query()->count())->toBe(1);
    expect(Publication::query()->count())->toBe(1);
    expect(
        AuditLog::query()->where('action', 'BULLETIN_PUBLIE')->count(),
    )->toBe(1);
});

test('rectification preserves the original publication and records the correction', function () {
    $service = app(PublishBulletinCalculationService::class);

    $original = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );
    $rectified = $service->rectify(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
        'Correction suite au conseil de classe.',
    );

    expect($rectified->publicationId)->not->toBe($original->publicationId);
    expect($rectified->snapshotVersion)->toBe(2);

    $originalPublication = Publication::query()->find($original->publicationId);
    $newPublication = Publication::query()->find($rectified->publicationId);
    $rectification = Rectification::query()->first();

    expect($originalPublication?->statut)->toBe('RECTIFIEE');
    expect($newPublication?->statut)->toBe('PUBLIEE');
    expect($rectification?->publication_id)->toBe($original->publicationId);
    expect($rectification?->nouvelle_publication_id)->toBe($rectified->publicationId);
    expect($rectification?->motif)->toBe('Correction suite au conseil de classe.');
    expect(
        AuditLog::query()->where('action', 'BULLETIN_RECTIFIE')->count(),
    )->toBe(1);
});

test('rectification fails when no publication exists yet', function () {
    $service = app(PublishBulletinCalculationService::class);

    expect(
        fn () => $service->rectify(
            $this->bulletin1,
            $this->classe,
            $this->periode,
            $this->user,
            'Sans publication initiale.',
        ),
    )->toThrow(BulletinException::class);
});

test('publish bulletin service ranks all class bulletins', function () {
    $service = app(PublishBulletinCalculationService::class);

    $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $service->publish(
        $this->bulletin2,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $this->bulletin1->refresh();
    $this->bulletin2->refresh();

    expect($this->bulletin1->rang)->not->toBeNull();
    expect($this->bulletin2->rang)->not->toBeNull();
    expect($this->bulletin1->nombre_eleves_classe)->toBe(2);
    expect($this->bulletin2->nombre_eleves_classe)->toBe(2);
});

test('proclamation service generates proclamation from publications', function () {
    $publishService = app(PublishBulletinCalculationService::class);
    $publishService->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );
    $publishService->publish(
        $this->bulletin2,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $service = app(ProclamationService::class);

    $proclamation = $service->generate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    expect($proclamation)->toBeInstanceOf(Proclamation::class);
    expect($proclamation->statut)->toBe('GENEREE');
    expect($proclamation->type)->toBe('TRIMESTRE');
    expect($proclamation->classe_id)->toBe($this->classe->getKey());
    expect($proclamation->publication_id)->not->toBeNull();

    $lignes = ProclamationLigne::query()
        ->where('proclamation_id', $proclamation->getKey())
        ->get();

    expect($lignes)->toHaveCount(2);
    expect($lignes->first()->decision)->toBe('PASSE');
    expect($lignes->first()->publication_id)->not->toBeNull();
});

test('proclamation service is idempotent', function () {
    $publishService = app(PublishBulletinCalculationService::class);
    $publishService->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $service = app(ProclamationService::class);

    $first = $service->generate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $second = $service->generate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    expect($first->getKey())->toBe($second->getKey());
});

test('proclamation service creates audit log', function () {
    $publishService = app(PublishBulletinCalculationService::class);
    $publishService->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $service = app(ProclamationService::class);

    $proclamation = $service->generate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $auditLog = AuditLog::query()
        ->where('action', 'PROCLAMATION_GENEREE')
        ->where('ligne_cible_id', $proclamation->getKey())
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->tenant_id)->toBe($this->tenant->id);
    expect($auditLog->nouvelles_valeurs['nombre_lignes'])->toBe(1);
});
