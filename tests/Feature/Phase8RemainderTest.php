<?php

use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Domains\Bulletin\Services\PromotionService;
use App\Domains\Bulletin\Services\PublishClassBulletinsService;
use App\Domains\Bulletin\Services\ValidateClassDeliberationService;
use App\Domains\SchoolYear\Services\CloturerAnneeScolaireService;
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
use App\Models\PromotionAnnuelle;
use App\Models\PromotionEleve;
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
        'code' => 'HUMANITES_REMAINDER',
        'nom' => 'Humanités Remainder',
        'ordre' => 5,
    ]);

    $niveauId = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId,
        'degre_id' => null,
        'code' => 'HUM_1_REMAINDER',
        'nom' => '1ère Humanités Remainder',
        'numero' => 1,
        'ordre' => 1,
    ]);

    $layoutId = DB::table('system_layouts_bulletin')->insertGetId([
        'code' => 'TEST_LAYOUT_REMAINDER',
        'nom' => 'Layout Test Phase 8 Remainder',
        'vue_blade' => 'test',
        'actif' => true,
    ]);

    $templateId = DB::table('system_templates_bulletin')->insertGetId([
        'code' => 'TEST_TEMPLATE_PHASE8_REMAINDER',
        'nom' => 'Template Phase 8 Remainder',
        'cycle_id' => $cycleId,
        'layout_id' => $layoutId,
        'type_decoupage' => 'TRIMESTRIEL',
        'total_general_maxima' => 20.00,
        'actif' => true,
    ]);

    $this->tenant = Tenant::create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École Test Remainder',
        'slug' => 'ecole-test-phase8-remainder',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $this->etablissement = Etablissement::create([
        'tenant_id' => $this->tenant->id,
        'nom' => 'Complexe Scolaire Remainder',
        'code_ministere' => 'CSR8',
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
        'telephone' => '+243000000003',
        'email' => 'admin-phase8-remainder@test.com',
        'mot_de_passe' => Hash::make('password123'),
        'actif' => true,
    ]);

    $this->user->roles()->attach($this->role->id);

    $calendrierId = DB::table('calendriers_scolaires')->insertGetId([
        'code' => 'RDC_TEST_PHASE8_REMAINDER',
        'libelle' => 'Calendrier Test Phase 8 Remainder',
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
        'system_template_id' => $templateId,
        'nom' => '1ère Commerciale Remainder',
        'active' => true,
    ]);

    $this->modeleBulletin = ModeleBulletin::create([
        'tenant_id' => $this->tenant->id,
        'classe_id' => $this->classe->id,
        'annee_scolaire_id' => $this->anneeScolaire->id,
        'system_template_id' => $templateId,
        'layout_id' => $layoutId,
        'nom' => 'Modèle Remainder',
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

it('publishes all class bulletins at once', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $service = app(PublishClassBulletinsService::class);
    $result = $service->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    expect($result['classe_id'])->toBe($this->classe->getKey());
    expect($result['periode_id'])->toBe($this->periode->getKey());
    expect($result['published_count'])->toBe(2);
    expect($result['already_published_count'])->toBe(0);
    expect($result['failed_count'])->toBe(0);
    expect($result['results'])->toHaveCount(2);

    foreach ($result['results'] as $r) {
        expect($r['status'])->toBe('published');
        expect($r['snapshot_version'])->toBeGreaterThan(0);
    }

    $this->bulletin1->refresh();
    $this->bulletin2->refresh();

    expect($this->bulletin1->decision_statut)->toBe('EN_COURS');
    expect($this->bulletin2->decision_statut)->toBe('EN_COURS');
});

it('is idempotent when re-publishing already published bulletins', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $service = app(PublishClassBulletinsService::class);

    $first = $service->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    expect($first['published_count'])->toBe(2);

    $second = $service->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    expect($second['published_count'])->toBe(0);
    expect($second['already_published_count'])->toBe(2);
    expect($second['failed_count'])->toBe(0);
});

it('deliberates a class after publishing', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $publishService = app(PublishClassBulletinsService::class);
    $publishService->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $service = app(ValidateClassDeliberationService::class);
    $result = $service->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
    );

    expect($result['classe_id'])->toBe($this->classe->getKey());
    expect($result['periode_id'])->toBe($this->periode->getKey());
    expect($result['validated_count'])->toBe(2);
    expect($result['skipped_count'])->toBe(0);
    expect($result['details'])->toHaveCount(2);

    foreach ($result['details'] as $detail) {
        expect($detail['status'])->toBe('validated');
        expect($detail['decision'])->toBeIn(['PASSE', 'DOUBLE', 'ECHOUE']);
    }

    $this->bulletin1->refresh();
    $this->bulletin2->refresh();

    expect($this->bulletin1->decision_statut)->not->toBe('EN_COURS');
    expect($this->bulletin1->lieu_deliberation)->toBe('Salle de conseil');
    expect($this->bulletin1->date_deliberation->format('Y-m-d'))->toBe('2026-09-15');
    expect($this->bulletin2->decision_statut)->not->toBe('EN_COURS');
    expect($this->bulletin2->lieu_deliberation)->toBe('Salle de conseil');
});

it('applies overridden decisions during deliberation', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $publishService = app(PublishClassBulletinsService::class);
    $publishService->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $service = app(ValidateClassDeliberationService::class);
    $result = $service->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
        [$this->bulletin1->getKey() => 'DOUBLE'],
    );

    $this->bulletin1->refresh();

    expect($this->bulletin1->decision_statut)->toBe('DOUBLE');

    $detail = collect($result['details'])->firstWhere('bulletin_id', $this->bulletin1->getKey());
    expect($detail['decision'])->toBe('DOUBLE');
});

it('is idempotent when deliberating already deliberated bulletins', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $publishService = app(PublishClassBulletinsService::class);
    $publishService->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $service = app(ValidateClassDeliberationService::class);

    $first = $service->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
    );

    expect($first['validated_count'])->toBe(2);

    $second = $service->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
    );

    expect($second['validated_count'])->toBe(0);
    expect($second['skipped_count'])->toBe(2);
});

it('creates audit log entries for deliberated bulletins', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $publishService = app(PublishClassBulletinsService::class);
    $publishService->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $service = app(ValidateClassDeliberationService::class);
    $service->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
    );

    $auditLogs = AuditLog::query()
        ->where('action', 'BULLETIN_DELIBERE')
        ->get();

    expect($auditLogs)->toHaveCount(2);

    foreach ($auditLogs as $log) {
        expect($log->tenant_id)->toBe($this->tenant->id);
        expect($log->utilisateur_id)->toBe($this->user->getKey());
        expect($log->table_cible)->toBe('bulletins');
        expect($log->anciennes_valeurs)->toHaveKey('decision_statut');
        expect($log->anciennes_valeurs['decision_statut'])->toBe('EN_COURS');
        expect($log->nouvelles_valeurs)->toHaveKey('decision_statut');
        expect($log->nouvelles_valeurs['decision_statut'])->not->toBe('EN_COURS');
    }
});

it('throws when deliberating without published publications', function () {
    $service = app(ValidateClassDeliberationService::class);

    expect(
        fn () => $service->deliberate(
            $this->etablissement,
            $this->classe,
            $this->periode,
            $this->user,
            'Salle de conseil',
            '2026-09-15',
        ),
    )->toThrow(BulletinException::class);
});

it('generates a promotion from deliberated publications', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $publishService = app(PublishClassBulletinsService::class);
    $publishService->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $deliberationService = app(ValidateClassDeliberationService::class);
    $deliberationService->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
    );

    $service = app(PromotionService::class);
    $promotion = $service->generate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    expect($promotion)->toBeInstanceOf(PromotionAnnuelle::class);
    expect($promotion->statut)->toBe('BROUILLON');
    expect($promotion->annee_source_id)->toBe($this->anneeScolaire->id);
    expect($promotion->tenant_id)->toBe($this->tenant->id);
    expect($promotion->publication_id)->not->toBeNull();
    expect($promotion->configuration)->toBeArray();
    expect($promotion->configuration['etablissement_id'])->toBe($this->etablissement->getKey());
    expect($promotion->configuration['classe_id'])->toBe($this->classe->getKey());
    expect($promotion->configuration['periode_id'])->toBe($this->periode->getKey());

    $eleves = PromotionEleve::query()
        ->where('promotion_id', $promotion->getKey())
        ->get();

    expect($eleves)->toHaveCount(2);

    $decisions = $eleves->pluck('decision')->toArray();
    foreach ($decisions as $decision) {
        expect($decision)->toBeIn(['PASSE', 'DOUBLE', 'TRANSFERE', 'ABANDON']);
    }
});

it('maps bulletin decisions to promotion decisions', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $publishService = app(PublishClassBulletinsService::class);
    $publishService->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $deliberationService = app(ValidateClassDeliberationService::class);
    $deliberationService->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
        [$this->bulletin1->getKey() => 'PASSE', $this->bulletin2->getKey() => 'DOUBLE'],
    );

    $service = app(PromotionService::class);
    $promotion = $service->generate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $eleves = PromotionEleve::query()
        ->where('promotion_id', $promotion->getKey())
        ->get();

    $bulletin1Decision = $eleves->firstWhere('inscription_source_id', $this->inscription1->id);
    $bulletin2Decision = $eleves->firstWhere('inscription_source_id', $this->inscription2->id);

    expect($bulletin1Decision->decision)->toBe('PASSE');
    expect($bulletin2Decision->decision)->toBe('DOUBLE');
});

it('is idempotent when generating an existing promotion', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $publishService = app(PublishClassBulletinsService::class);
    $publishService->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $deliberationService = app(ValidateClassDeliberationService::class);
    $deliberationService->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
    );

    $service = app(PromotionService::class);

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
    expect(PromotionEleve::query()->where('promotion_id', $first->getKey())->count())->toBe(2);
});

it('creates audit log for promotion generation', function () {
    $this->bulletin1->update(['verrouille' => false]);
    $this->bulletin2->update(['verrouille' => false]);

    $publishService = app(PublishClassBulletinsService::class);
    $publishService->publishAll(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $deliberationService = app(ValidateClassDeliberationService::class);
    $deliberationService->deliberate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
        'Salle de conseil',
        '2026-09-15',
    );

    $service = app(PromotionService::class);
    $promotion = $service->generate(
        $this->etablissement,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $auditLog = AuditLog::query()
        ->where('action', 'PROMOTION_GENEREE')
        ->where('table_cible', 'promotions_annuelles')
        ->where('ligne_cible_id', $promotion->getKey())
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->tenant_id)->toBe($this->tenant->id);
    expect($auditLog->utilisateur_id)->toBe($this->user->getKey());
    expect($auditLog->nouvelles_valeurs['nombre_eleves'])->toBe(2);
});

it('throws when generating promotion without deliberated publications', function () {
    $service = app(PromotionService::class);

    expect(
        fn () => $service->generate(
            $this->etablissement,
            $this->classe,
            $this->periode,
            $this->user,
        ),
    )->toThrow(BulletinException::class);
});

it('closes a school year', function () {
    $service = app(CloturerAnneeScolaireService::class);
    $result = $service->cloturer($this->anneeScolaire, $this->user, 'Fin de l\'année scolaire');

    expect($result->getKey())->toBe($this->anneeScolaire->getKey());
    expect($result->cloturee)->toBeTrue();

    $this->anneeScolaire->refresh();
    expect($this->anneeScolaire->cloturee)->toBeTrue();
});

it('is idempotent when closing an already closed school year', function () {
    $service = app(CloturerAnneeScolaireService::class);

    $service->cloturer($this->anneeScolaire, $this->user, 'Fin de l\'année scolaire');

    $auditCountBefore = AuditLog::query()
        ->where('action', 'ANNEE_CLOTUREE')
        ->count();

    $service->cloturer($this->anneeScolaire, $this->user, 'Tentative de double clôture');

    $auditCountAfter = AuditLog::query()
        ->where('action', 'ANNEE_CLOTUREE')
        ->count();

    expect($auditCountAfter)->toBe($auditCountBefore);
});

it('creates audit log for school year closure', function () {
    $service = app(CloturerAnneeScolaireService::class);
    $service->cloturer($this->anneeScolaire, $this->user, 'Fin de l\'année scolaire');

    $auditLog = AuditLog::query()
        ->where('action', 'ANNEE_CLOTUREE')
        ->where('table_cible', 'annees_scolaires')
        ->where('ligne_cible_id', $this->anneeScolaire->getKey())
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->tenant_id)->toBeNull();
    expect($auditLog->utilisateur_id)->toBe($this->user->getKey());
    expect($auditLog->anciennes_valeurs['cloturee'])->toBeFalse();
    expect($auditLog->nouvelles_valeurs['cloturee'])->toBeTrue();
    expect($auditLog->nouvelles_valeurs['motif'])->toBe('Fin de l\'année scolaire');
});

it('closes a school year without motif', function () {
    $service = app(CloturerAnneeScolaireService::class);
    $service->cloturer($this->anneeScolaire, $this->user);

    $auditLog = AuditLog::query()
        ->where('action', 'ANNEE_CLOTUREE')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->nouvelles_valeurs['motif'])->toBeNull();
});
