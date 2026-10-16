<?php

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\ModeleBulletin;
use App\Models\Role;
use App\Models\SystemTemplateMaximum;
use App\Models\SystemTemplateNode;
use App\Models\SystemTemplatePeriode;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedAssignableClassTemplate(array $catalog): void
{
    $parent = SystemTemplateNode::query()->create([
        'template_id' => $catalog['narrow'], 'type_node' => 'DOMAINE', 'libelle' => 'Sciences', 'ordre' => 1,
    ]);
    $node = SystemTemplateNode::query()->create([
        'template_id' => $catalog['narrow'], 'parent_id' => $parent->id,
        'type_node' => 'MATIERE', 'libelle' => 'Mathématiques', 'ordre' => 2,
    ]);
    $period = SystemTemplatePeriode::query()->create([
        'template_id' => $catalog['narrow'], 'code' => 'P1', 'nom' => 'Période 1',
        'type_periode' => 'PERIODE', 'ordre' => 1,
    ]);
    SystemTemplateMaximum::query()->create([
        'template_id' => $catalog['narrow'], 'node_id' => $node->id,
        'periode_id' => $period->id, 'maxima' => 40, 'coefficient' => 2,
    ]);
}

test('creating a class makes global courses periods and maxima available immediately', function () {
    $tenant = createTenantForClassTemplate('class-template-ready');
    [$school, $headers] = classTemplateAdmin($tenant);
    $catalog = seedClassTemplateCatalog();
    seedAssignableClassTemplate($catalog);
    $classId = $this->withHeaders($headers)->postJson('/api/v1/classes', classPayload($catalog))
        ->assertCreated()->json('data.id');

    $this->withHeaders($headers)->getJson("/api/v1/classes/{$classId}/cours")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.libelle', 'Mathématiques')
        ->assertJsonPath('data.0.attribuable', true);

    $model = ModeleBulletin::query()->where('classe_id', $classId)->firstOrFail();
    $course = $model->nodes()->where('type_node', 'MATIERE')->firstOrFail();
    expect($model->nodes()->findOrFail($course->parent_id)->libelle)->toBe('Sciences')
        ->and($model->periodes()->count())->toBe(1)
        ->and((float) $model->maxima()->firstOrFail()->maxima)->toBe(40.0);
});

test('direction initializes a legacy class once without overwriting its configuration', function (string $roleCode) {
    $tenant = createTenantForClassTemplate('class-template-legacy');
    [$school, $headers] = classTemplateAdmin($tenant);
    $catalog = seedClassTemplateCatalog();
    seedAssignableClassTemplate($catalog);
    $class = Classe::withoutGlobalScopes()->create([
        ...classPayload($catalog), 'tenant_id' => $tenant->id, 'etablissement_id' => $school->id,
        'system_template_id' => $catalog['narrow'], 'active' => true,
    ]);
    $uri = "/api/v1/classes/{$class->id}/configuration-cours";
    $this->withHeaders($headers)->postJson($uri)->assertForbidden();
    expect(ModeleBulletin::query()->where('classe_id', $class->id)->exists())->toBeFalse();

    $actor = Utilisateur::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $actor->update(['etablissement_id' => $school->id]);
    $role = Role::query()->firstOrCreate(
        ['tenant_id' => null, 'code' => $roleCode],
        ['nom' => $roleCode, 'guard_name' => 'web', 'system_role' => true],
    );
    $actor->syncRoles([$role]);
    $this->app['auth']->forgetGuards();
    $this->withHeaders($headers)->postJson($uri)->assertOk();
    $model = ModeleBulletin::query()->where('classe_id', $class->id)->firstOrFail();
    $maximum = $model->maxima()->firstOrFail();
    $maximum->update(['maxima' => 50]);
    $this->withHeaders($headers)->postJson($uri)->assertOk()
        ->assertJsonPath('data.modele_bulletin_id', $model->id);
    expect(ModeleBulletin::query()->where('classe_id', $class->id)->count())->toBe(1)
        ->and((float) $maximum->refresh()->maxima)->toBe(50.0)
        ->and($model->nodes()->count())->toBe(2);
    $this->withHeaders($headers)->getJson("/api/v1/classes/{$class->id}/cours")
        ->assertOk()->assertJsonPath('data.0.attribuable', true);

    $class->update(['active' => false]);
    $this->withHeaders($headers)->postJson($uri)->assertConflict();
    $class->update(['active' => true]);
    $class->anneeScolaire()->update(['cloturee' => true]);
    $this->withHeaders($headers)->postJson($uri)->assertConflict();
})->with(['GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES']);

function createTenantForClassTemplate(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
}

function classTemplateAdmin(Tenant $tenant): array
{
    $role = Role::query()->firstOrCreate(
        ['tenant_id' => null, 'name' => 'ADMIN_TENANT'],
        ['code' => 'ADMIN_TENANT', 'nom' => 'Administrateur tenant', 'guard_name' => 'web', 'system_role' => true],
    );
    $school = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Institut Test',
        'type_etablissement' => 'SECONDAIRE',
    ]);
    $admin = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Administrateur',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);
    $admin->assignRole($role);
    $token = $admin->createToken('class-template', ["tenant:{$tenant->id}"])->plainTextToken;

    return [$school, ['Authorization' => 'Bearer '.$token, 'X-Etablissement' => (string) $school->id]];
}

function seedClassTemplateCatalog(): array
{
    $cycleId = DB::table('ref_cycles')->insertGetId([
        'code' => 'HUM_TEST', 'nom' => 'Humanités test', 'ordre' => 4, 'actif' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $sciId = DB::table('ref_options')->insertGetId([
        'cycle_id' => $cycleId, 'code' => 'SCI_TEST', 'nom' => 'Commerciale & Gestion',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $n1 = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId, 'degre_id' => null, 'code' => 'HUM_TEST_1',
        'nom' => '1ère Humanités', 'numero' => 1, 'ordre' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $n2 = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId, 'degre_id' => null, 'code' => 'HUM_TEST_2',
        'nom' => '2ème Humanités', 'numero' => 2, 'ordre' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $n3 = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId, 'degre_id' => null, 'code' => 'HUM_TEST_3',
        'nom' => '3ème Humanités', 'numero' => 3, 'ordre' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $n4 = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId, 'degre_id' => null, 'code' => 'HUM_TEST_4',
        'nom' => '4ème Humanités', 'numero' => 4, 'ordre' => 4,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $layoutId = DB::table('system_layouts_bulletin')->insertGetId([
        'code' => 'CLASS_TEMPLATE_LAYOUT', 'nom' => 'Layout test', 'actif' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $makeTemplate = fn (string $code, ?int $min, ?int $max): int => DB::table('system_templates_bulletin')->insertGetId([
        'code' => $code, 'nom' => "Modèle {$code}", 'cycle_id' => $cycleId,
        'option_id' => $sciId, 'niveau_min_id' => $min, 'niveau_max_id' => $max,
        'layout_id' => $layoutId, 'type_decoupage' => 'TRIMESTRIEL', 'version' => 'test',
        'actif' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $wide = $makeTemplate('CLASS_TEMPLATE_WIDE', $n1, $n2);
    $narrow = $makeTemplate('CLASS_TEMPLATE_NARROW', $n1, $n1);

    $yearId = DB::table('annees_scolaires')->insertGetId([
        'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
        'active' => true, 'cloturee' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [
        'cycle_id' => $cycleId, 'option_id' => $sciId,
        'n1' => $n1, 'n2' => $n2, 'n3' => $n3, 'n4' => $n4,
        'wide' => $wide, 'narrow' => $narrow,
        'year_id' => $yearId,
    ];
}

function classPayload(array $catalog, array $overrides = []): array
{
    return array_merge([
        'annee_scolaire_id' => $catalog['year_id'],
        'cycle_id' => $catalog['cycle_id'],
        'niveau_id' => $catalog['n1'],
        'option_id' => $catalog['option_id'],
        'nom' => '1ère Scientifique A',
    ], $overrides);
}

test('the system links the narrowest configured template', function (): void {
    $tenant = createTenantForClassTemplate('class-template-narrow');
    [$school, $headers] = classTemplateAdmin($tenant);
    $catalog = seedClassTemplateCatalog();

    $this->withHeaders($headers)
        ->postJson('/api/v1/classes', classPayload($catalog))
        ->assertCreated()
        ->assertJsonPath('data.system_template_id', $catalog['narrow'])
        ->assertJsonPath('data.option_id', $catalog['option_id']);
});

test('a school organizes every level of its selected options without duplicates', function (): void {
    $tenant = createTenantForClassTemplate('class-template-organization');
    [$school, $headers] = classTemplateAdmin($tenant);
    $catalog = seedClassTemplateCatalog();
    seedAssignableClassTemplate($catalog);

    DB::table('system_templates_bulletin')->insert([
        'code' => 'CLASS_TEMPLATE_THIRD',
        'nom' => 'Modèle niveau 3',
        'cycle_id' => $catalog['cycle_id'],
        'option_id' => $catalog['option_id'],
        'niveau_min_id' => $catalog['n3'],
        'niveau_max_id' => $catalog['n3'],
        'layout_id' => DB::table('system_layouts_bulletin')->where('code', 'CLASS_TEMPLATE_LAYOUT')->value('id'),
        'type_decoupage' => 'TRIMESTRIEL',
        'version' => 'test',
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('system_templates_bulletin')->insert([
        'code' => 'CLASS_TEMPLATE_FOURTH',
        'nom' => 'Modèle niveau 4',
        'cycle_id' => $catalog['cycle_id'],
        'option_id' => $catalog['option_id'],
        'niveau_min_id' => $catalog['n4'],
        'niveau_max_id' => $catalog['n4'],
        'layout_id' => DB::table('system_layouts_bulletin')->where('code', 'CLASS_TEMPLATE_LAYOUT')->value('id'),
        'type_decoupage' => 'TRIMESTRIEL',
        'version' => 'test',
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'annee_scolaire_id' => $catalog['year_id'],
        'organisations' => [[
            'cycle_id' => $catalog['cycle_id'],
            'option_id' => $catalog['option_id'],
        ]],
        'parallele' => 'A',
    ];

    $this->withHeaders($headers)
        ->postJson('/api/v1/classes/organisation', $payload)
        ->assertCreated()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.option.id', $catalog['option_id'])
        ->assertJsonPath('data.3.nom', '4ème Commerciale & Gestion A')
        ->assertJsonPath('data.3.code', '4eme CG A');

    $this->withHeaders($headers)
        ->postJson('/api/v1/classes/organisation', $payload)
        ->assertCreated()
        ->assertJsonCount(4, 'data');

    expect(DB::table('classes')
        ->where('tenant_id', $tenant->getKey())
        ->where('etablissement_id', $school->getKey())
        ->count())->toBe(4);
    expect(ModeleBulletin::query()->where('tenant_id', $tenant->id)->count())->toBe(4);
});

test('organizing a cycle without options still fills the sigle', function (): void {
    $tenant = createTenantForClassTemplate('class-template-nooption');
    [$school, $headers] = classTemplateAdmin($tenant);

    $cycleId = DB::table('ref_cycles')->insertGetId([
        'code' => 'PRIM_NOTEST', 'nom' => 'Primaire test', 'ordre' => 2, 'actif' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $niveauId = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId, 'degre_id' => null, 'code' => 'PRIM_NOTEST_6',
        'nom' => '6ème Primaire', 'numero' => 6, 'ordre' => 6,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $layoutId = DB::table('system_layouts_bulletin')->insertGetId([
        'code' => 'NOTEST_LAYOUT', 'nom' => 'Layout test', 'actif' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $templateId = DB::table('system_templates_bulletin')->insertGetId([
        'code' => 'NOTEST_TEMPLATE', 'nom' => 'Modèle test', 'cycle_id' => $cycleId,
        'option_id' => null, 'niveau_min_id' => $niveauId, 'niveau_max_id' => $niveauId,
        'layout_id' => $layoutId, 'type_decoupage' => 'TRIMESTRIEL', 'version' => 'test',
        'actif' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $yearId = DB::table('annees_scolaires')->insertGetId([
        'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
        'active' => true, 'cloturee' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withHeaders($headers)
        ->postJson('/api/v1/classes/organisation', [
            'annee_scolaire_id' => $yearId,
            'organisations' => [['cycle_id' => $cycleId, 'option_id' => null]],
            'parallele' => 'A',
        ])
        ->assertCreated()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nom', '6ème Primaire A')
        ->assertJsonPath('data.0.code', '6eme Primaire A');
});

test('an explicit template is refused on creation', function (): void {
    $tenant = createTenantForClassTemplate('class-template-explicit');
    [$school, $headers] = classTemplateAdmin($tenant);
    $catalog = seedClassTemplateCatalog();

    $this->withHeaders($headers)
        ->postJson('/api/v1/classes', classPayload($catalog, ['system_template_id' => $catalog['wide']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['system_template_id']);
});

test('an option is required when the cycle organizes options', function (): void {
    $tenant = createTenantForClassTemplate('class-template-option');
    [$school, $headers] = classTemplateAdmin($tenant);
    $catalog = seedClassTemplateCatalog();
    $payload = classPayload($catalog);
    unset($payload['option_id']);

    $this->withHeaders($headers)
        ->postJson('/api/v1/classes', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option_id']);
});

test('incoherent cycle combinations are refused', function (): void {
    $tenant = createTenantForClassTemplate('class-template-coherence');
    [$school, $headers] = classTemplateAdmin($tenant);
    $catalog = seedClassTemplateCatalog();

    $foreignCycle = DB::table('ref_cycles')->insertGetId([
        'code' => 'PRIM_TEST', 'nom' => 'Primaire test', 'ordre' => 2, 'actif' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $foreignNiveau = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $foreignCycle, 'degre_id' => null, 'code' => 'PRIM_TEST_1',
        'nom' => '1ère primaire test', 'numero' => 1, 'ordre' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Niveau d'un autre cycle.
    $this->withHeaders($headers)
        ->postJson('/api/v1/classes', classPayload($catalog, ['niveau_id' => $foreignNiveau]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['niveau_id']);

    // Option d'un autre cycle.
    $foreignOption = DB::table('ref_options')->insertGetId([
        'cycle_id' => $foreignCycle, 'code' => 'PRIM_OPT_TEST', 'nom' => 'Option primaire test',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->withHeaders($headers)
        ->postJson('/api/v1/classes', classPayload($catalog, ['option_id' => $foreignOption]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['option_id']);
});

test('a missing platform configuration blocks creation explictly', function (): void {
    $tenant = createTenantForClassTemplate('class-template-missing');
    [$school, $headers] = classTemplateAdmin($tenant);
    $catalog = seedClassTemplateCatalog();

    // Niveau 3 : aucune plage configurée.
    $this->withHeaders($headers)
        ->postJson('/api/v1/classes', classPayload($catalog, ['niveau_id' => $catalog['n3'], 'nom' => '3ème Scientifique A']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['system_template_id']);
});
