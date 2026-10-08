<?php

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Evaluation\Exceptions\EvaluationException;
use App\Domains\SchoolYear\Events\ClassArchived;
use App\Domains\Student\Events\EnrollmentExited;
use App\Domains\Student\Events\StudentArchived;
use App\Domains\Student\Events\StudentEnrolled;
use App\Domains\Student\Events\StudentReactivated;
use App\Domains\Student\Exceptions\StudentException;
use App\Domains\Student\Services\ImportStudentsService;
use App\Domains\Teacher\Events\ClassTitularAssigned;
use App\Domains\Teacher\Events\CourseTeacherAssigned;
use App\Domains\Teacher\Services\UpsertMyCourseAssignmentNoteService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Http\Middleware\EstablishmentMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
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
use App\Models\PresenceEleve;
use App\Models\Proclamation;
use App\Models\ProclamationLigne;
use App\Models\Role;
use App\Models\SuiviEncodageNote;
use App\Models\Tenant;
use App\Models\TitulaireClasse;
use App\Models\Utilisateur;
use App\Models\VerrouillagePeriode;
use App\Policies\AttributionCoursPolicy;
use App\Policies\BulletinPolicy;
use App\Policies\ClassePolicy;
use App\Policies\ElevePolicy;
use App\Policies\EtablissementPolicy;
use App\Policies\InscriptionPolicy;
use App\Policies\NotePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     cycle_id: int,
 *     niveau_id: int,
 *     layout_id: int,
 *     template_id: int
 * }
 */
function createAcademicMutationCatalog(): array
{
    $cycleId = DB::table('ref_cycles')->insertGetId([
        'code' => 'ACADEMIC_BOUNDARY',
        'nom' => 'Academic Boundary',
        'ordre' => 1,
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $niveauId = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId,
        'degre_id' => null,
        'code' => 'ACADEMIC_BOUNDARY_1',
        'nom' => 'Academic Boundary 1',
        'numero' => 1,
        'ordre' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $layoutId = DB::table('system_layouts_bulletin')->insertGetId([
        'code' => 'ACADEMIC_BOUNDARY_LAYOUT',
        'nom' => 'Academic Boundary Layout',
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $templateId = DB::table('system_templates_bulletin')->insertGetId([
        'code' => 'ACADEMIC_BOUNDARY_TEMPLATE',
        'nom' => 'Academic Boundary Template',
        'cycle_id' => $cycleId,
        'layout_id' => $layoutId,
        'type_decoupage' => 'TRIMESTRIEL',
        'version' => 'test',
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'cycle_id' => $cycleId,
        'niveau_id' => $niveauId,
        'layout_id' => $layoutId,
        'template_id' => $templateId,
    ];
}

/**
 * @param  array{cycle_id: int, niveau_id: int, layout_id: int, template_id: int}  $catalog
 * @return array{
 *     tenant: Tenant,
 *     establishment: Etablissement,
 *     user: Utilisateur,
 *     year: AnneeScolaire,
 *     class: Classe,
 *     model: ModeleBulletin,
 *     node: ModeleNode,
 *     period: ModelePeriode,
 *     student: Eleve,
 *     enrollment: Inscription,
 *     bulletin: Bulletin
 * }
 */
function createAcademicMutationScope(
    array $catalog,
    string $suffix = 'A',
    ?Tenant $tenant = null,
): array {
    $tenant ??= Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => "Tenant {$suffix}",
        'slug' => 'academic-boundary-'.Str::lower($suffix).'-'.Str::lower(Str::random(6)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
    $establishment = Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => "Établissement {$suffix}",
        'actif' => true,
    ]);
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'nom' => "Enseignant {$suffix}",
        'telephone' => '+243'.random_int(100000000, 999999999),
        'email' => 'academic-'.Str::lower(Str::random(8)).'@example.test',
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $managementRole = Role::query()->firstOrCreate(
        [
            'tenant_id' => null,
            'code' => 'DIRECTEUR_ETUDES',
        ],
        [
            'nom' => 'Directeur des études',
            'system_role' => true,
        ],
    );
    $user->roles()->attach($managementRole->id);
    $year = AnneeScolaire::create([
        'libelle' => "2026-2027-{$suffix}",
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);
    $class = Classe::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'annee_scolaire_id' => $year->id,
        'cycle_id' => $catalog['cycle_id'],
        'niveau_id' => $catalog['niveau_id'],
        'system_template_id' => $catalog['template_id'],
        'nom' => "Classe {$suffix}",
        'active' => true,
    ]);
    $model = ModeleBulletin::query()->create([
        'tenant_id' => $tenant->id,
        'classe_id' => $class->id,
        'annee_scolaire_id' => $year->id,
        'system_template_id' => $catalog['template_id'],
        'layout_id' => $catalog['layout_id'],
        'nom' => "Modèle {$suffix}",
        'type_decoupage' => 'TRIMESTRIEL',
        'verrouille' => false,
        'actif' => true,
    ]);
    $node = ModeleNode::query()->create([
        'tenant_id' => $tenant->id,
        'modele_bulletin_id' => $model->id,
        'type_node' => 'MATIERE',
        'libelle' => "Cours {$suffix}",
        'ordre' => 1,
        'afficher_ligne' => true,
        'calculer_sous_total' => false,
        'obligatoire' => true,
    ]);
    $period = ModelePeriode::query()->create([
        'tenant_id' => $tenant->id,
        'modele_bulletin_id' => $model->id,
        'code' => "P1_{$suffix}",
        'nom' => "Période {$suffix}",
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);
    ModeleMaximum::query()->create([
        'tenant_id' => $tenant->id,
        'modele_bulletin_id' => $model->id,
        'node_id' => $node->id,
        'periode_id' => $period->id,
        'maxima' => 20,
        'coefficient' => 1,
    ]);
    $student = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'num_permanent' => "MAT-{$suffix}",
        'nom' => "Élève {$suffix}",
        'actif' => true,
    ]);
    $enrollment = Inscription::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'eleve_id' => $student->id,
        'classe_id' => $class->id,
        'annee_scolaire_id' => $year->id,
        'statut' => 'ACTIF',
    ]);
    $bulletin = Bulletin::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'inscription_id' => $enrollment->id,
        'classe_id' => $class->id,
        'modele_bulletin_id' => $model->id,
        'decision_statut' => 'EN_COURS',
        'verrouille' => false,
    ]);
    AttributionCours::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'classe_id' => $class->id,
        'modele_bulletin_id' => $model->id,
        'node_id' => $node->id,
        'enseignant_id' => $user->id,
        'actif' => true,
    ]);

    return compact(
        'tenant',
        'establishment',
        'user',
        'year',
        'class',
        'model',
        'node',
        'period',
        'student',
        'enrollment',
        'bulletin',
    );
}

/**
 * @param  array{tenant: Tenant, user: Utilisateur}  $scope
 */
function academicMutationToken(array $scope): string
{
    return $scope['user']->createToken(
        'academic-mutation',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
}

/**
 * @param  array{tenant: Tenant, establishment: Etablissement}  $scope
 */
function createCourseAssignmentTeacher(
    array $scope,
    string $suffix,
    bool $withTeacherRole = true,
): Utilisateur {
    $teacher = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => "Enseignant attribution {$suffix}",
        'telephone' => '+242'.random_int(100000000, 999999999),
        'email' => 'assignment-'.Str::lower(Str::random(8)).'@example.test',
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);

    if ($withTeacherRole) {
        $teacherRole = Role::query()->firstOrCreate(
            [
                'tenant_id' => null,
                'code' => 'ENSEIGNANT',
            ],
            [
                'nom' => 'Enseignant',
                'system_role' => true,
            ],
        );
        $teacher->roles()->attach($teacherRole->id);
    }

    return $teacher;
}

/**
 * @param  array{tenant: Tenant, establishment: Etablissement}  $scope
 */
function createClassTitularCandidate(
    array $scope,
    string $suffix,
    bool $withTitularRole = true,
): Utilisateur {
    $titular = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => "Titulaire attribution {$suffix}",
        'telephone' => '+241'.random_int(100000000, 999999999),
        'email' => 'titular-assignment-'.Str::lower(Str::random(8)).'@example.test',
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);

    if ($withTitularRole) {
        $titularRole = Role::query()->firstOrCreate(
            [
                'tenant_id' => null,
                'code' => 'TITULAIRE',
            ],
            [
                'nom' => 'Titulaire',
                'system_role' => true,
            ],
        );
        $titular->roles()->attach($titularRole->id);
    }

    return $titular;
}

/**
 * @param  list<list<string|int|float|null>>  $rows
 */
function noteXlsxUpload(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'notes-xlsx-');

    if ($temporaryPath === false) {
        throw new RuntimeException('Unable to create a temporary XLSX test file.');
    }

    try {
        (new Xlsx($spreadsheet))->save($temporaryPath);
        $contents = file_get_contents($temporaryPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the temporary XLSX test file.');
        }

        return UploadedFile::fake()->createWithContent('notes.xlsx', $contents);
    } finally {
        $spreadsheet->disconnectWorksheets();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}

/**
 * @return array{
 *     catalog: array<string, int>,
 *     scope: array<string, mixed>,
 *     teacher: Utilisateur,
 *     assignment: AttributionCours,
 *     slot: ModeleMaximum,
 *     token: string,
 *     uri: string
 * }
 */
function createTeacherNoteImportContext(string $suffix): array
{
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, $suffix);
    $teacher = createCourseAssignmentTeacher($scope, $suffix);
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->where('periode_id', $scope['period']->id)
        ->firstOrFail();
    $token = $teacher->createToken(
        'teacher-note-import',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/imports-notes";

    return compact(
        'scope',
        'catalog',
        'teacher',
        'assignment',
        'slot',
        'token',
        'uri',
    );
}

test('attendance derives immutable context fields and upserts within a scoped class', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $uri = "/api/v1/classes/{$scope['class']->id}/presences";
    $token = academicMutationToken($scope);

    $this->withToken($token)->postJson($uri, [
        'inscription_id' => $scope['enrollment']->id,
        'date_presence' => '2026-10-10',
        'statut' => 'ABSENT',
    ])->assertCreated();

    $this->withToken($token)->postJson($uri, [
        'inscription_id' => $scope['enrollment']->id,
        'date_presence' => '2026-10-10',
        'statut' => 'JUSTIFIE',
        'observation' => 'Motif reçu',
    ])->assertCreated();

    $this->assertDatabaseCount('presences_eleves', 1);
    $this->assertDatabaseHas('presences_eleves', [
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'classe_id' => $scope['class']->id,
        'inscription_id' => $scope['enrollment']->id,
        'saisi_par' => $scope['user']->id,
        'statut' => 'JUSTIFIE',
    ]);
});

test('attendance rejects another establishment and client managed fields', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'A');
    $foreignScope = createAcademicMutationScope($catalog, 'B', $scope['tenant']);
    $token = academicMutationToken($scope);

    $this->withToken($token)
        ->postJson("/api/v1/classes/{$foreignScope['class']->id}/presences", [
            'inscription_id' => $foreignScope['enrollment']->id,
            'date_presence' => '2026-10-10',
            'statut' => 'PRESENT',
        ])
        ->assertNotFound();

    $this->withToken($token)
        ->postJson("/api/v1/classes/{$scope['class']->id}/presences", [
            'inscription_id' => $foreignScope['enrollment']->id,
            'date_presence' => '2026-10-10',
            'statut' => 'PRESENT',
        ])
        ->assertNotFound();

    $this->withToken($token)
        ->postJson("/api/v1/classes/{$scope['class']->id}/presences", [
            'inscription_id' => $scope['enrollment']->id,
            'date_presence' => '2026-10-10',
            'statut' => 'PRESENT',
            'saisi_par' => $foreignScope['user']->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['saisi_par']);
});

test('an active class titular can enroll students and record attendance only in their class', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $titular = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Titulaire contextuel',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $scope['class']->update(['titulaire_id' => $titular->id]);
    $student = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-TITULAIRE',
        'nom' => 'Élève du titulaire',
        'actif' => true,
    ]);
    $otherClass = Classe::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'cycle_id' => $scope['class']->cycle_id,
        'niveau_id' => $scope['class']->niveau_id,
        'system_template_id' => $scope['class']->system_template_id,
        'nom' => 'Autre classe',
        'active' => true,
    ]);
    $token = $titular->createToken(
        'class-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $titularRole = Role::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'code' => 'TITULAIRE',
        'nom' => 'Titulaire',
        'system_role' => false,
    ]);
    $titular->roles()->attach($titularRole);

    expect(Gate::getPolicyFor(Classe::class))
        ->toBeInstanceOf(ClassePolicy::class);

    $this->withToken($token)
        ->postJson("/api/v1/classes/{$scope['class']->id}/inscriptions", [
            'eleve_id' => $student->id,
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson("/api/v1/classes/{$scope['class']->id}/presences", [
            'inscription_id' => $scope['enrollment']->id,
            'date_presence' => '2026-10-11',
            'statut' => 'PRESENT',
        ])
        ->assertCreated();

    foreach ([
        "/api/v1/classes/{$otherClass->id}/inscriptions",
        "/api/v1/classes/{$otherClass->id}/presences",
    ] as $uri) {
        $this->withToken($token)
            ->postJson($uri)
            ->assertForbidden();
    }

    $scope['class']->update(['active' => false]);

    foreach ([
        "/api/v1/classes/{$scope['class']->id}/inscriptions",
        "/api/v1/classes/{$scope['class']->id}/presences",
    ] as $uri) {
        $this->withToken($token)
            ->postJson($uri)
            ->assertForbidden();
    }

    $scope['class']->update(['active' => true]);
    $inactiveTitular = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Titulaire inactif',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $scope['class']->update(['titulaire_id' => $inactiveTitular->id]);
    $inactiveToken = $inactiveTitular->createToken(
        'inactive-class-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $inactiveTitular->update(['actif' => false]);

    foreach ([
        "/api/v1/classes/{$scope['class']->id}/inscriptions",
        "/api/v1/classes/{$scope['class']->id}/presences",
    ] as $uri) {
        $this->withToken($inactiveToken)
            ->postJson($uri)
            ->assertForbidden();
    }

    $this->assertDatabaseCount('inscriptions', 2);
    $this->assertDatabaseCount('presences_eleves', 1);
});

test('enrollment validation rejects a student outside the current establishment', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'A');
    $foreignScope = createAcademicMutationScope($catalog, 'B', $scope['tenant']);

    $this->withToken(academicMutationToken($scope))
        ->postJson("/api/v1/classes/{$scope['class']->id}/inscriptions", [
            'eleve_id' => $foreignScope['student']->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['eleve_id']);
});

test('class archival is audited, state idempotent and preserves academic history', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $scope['enrollment']->update(['statut' => 'ABANDON']);
    $note = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 12,
        'saisie_par' => $scope['user']->id,
    ]);
    $attendance = PresenceEleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'classe_id' => $scope['class']->id,
        'inscription_id' => $scope['enrollment']->id,
        'periode_id' => $scope['period']->id,
        'date_presence' => '2026-10-01',
        'moment' => 'JOURNEE',
        'statut' => 'PRESENT',
        'saisi_par' => $scope['user']->id,
    ]);
    $token = academicMutationToken($scope);
    $uri = "/api/v1/classes/{$scope['class']->id}/archivage";
    Event::fake([ClassArchived::class]);

    expect(Gate::getPolicyFor(Classe::class))
        ->toBeInstanceOf(ClassePolicy::class)
        ->and(is_subclass_of(
            ClassArchived::class,
            ShouldDispatchAfterCommit::class,
        ))->toBeTrue();

    $this->withToken($token)
        ->postJson($uri, ['motif' => 'Clôture administrative'])
        ->assertOk()
        ->assertJsonPath('data.id', $scope['class']->id)
        ->assertJsonPath('data.active', false)
        ->assertJsonMissingPath('data.tenant_id');

    $this->assertDatabaseHas('classes', [
        'id' => $scope['class']->id,
        'active' => false,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $scope['tenant']->id,
        'utilisateur_id' => $scope['user']->id,
        'action' => 'CLASSE_ARCHIVEE',
        'table_cible' => 'classes',
        'ligne_cible_id' => $scope['class']->id,
    ]);
    $this->assertDatabaseHas('inscriptions', [
        'id' => $scope['enrollment']->id,
        'statut' => 'ABANDON',
    ]);
    $this->assertDatabaseHas('bulletins', [
        'id' => $scope['bulletin']->id,
        'inscription_id' => $scope['enrollment']->id,
    ]);
    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'valeur' => 12,
    ]);
    $this->assertDatabaseHas('presences_eleves', [
        'id' => $attendance->id,
        'statut' => 'PRESENT',
    ]);
    $this->assertDatabaseHas('attributions_cours', [
        'classe_id' => $scope['class']->id,
        'enseignant_id' => $scope['user']->id,
        'actif' => true,
    ]);

    $newAuditValues = json_decode(
        (string) DB::table('audit_logs')
            ->where('action', 'CLASSE_ARCHIVEE')
            ->where('ligne_cible_id', $scope['class']->id)
            ->value('nouvelles_valeurs'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($newAuditValues)->toMatchArray([
        'active' => false,
        'motif' => 'Clôture administrative',
    ]);
    Event::assertDispatched(
        ClassArchived::class,
        fn (ClassArchived $event): bool => $event->tenantId === $scope['tenant']->id
            && $event->establishmentId === $scope['establishment']->id
            && $event->schoolYearId === $scope['year']->id
            && $event->classId === $scope['class']->id
            && $event->actorId === $scope['user']->id,
    );
    Event::assertDispatchedTimes(ClassArchived::class, 1);

    $this->withToken($token)
        ->postJson($uri, ['motif' => 'Replay client'])
        ->assertOk()
        ->assertJsonPath('data.active', false);

    expect(
        DB::table('audit_logs')
            ->where('action', 'CLASSE_ARCHIVEE')
            ->where('ligne_cible_id', $scope['class']->id)
            ->count(),
    )->toBe(1);
    Event::assertDispatchedTimes(ClassArchived::class, 1);

    $this->withToken($token)
        ->patchJson("/api/v1/classes/{$scope['class']->id}", [
            'nom' => 'Modification interdite',
        ])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Archived classes cannot be modified.',
        );

    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/periodes/{$scope['period']->id}/verrouillages",
            ['motif' => 'Transition interdite'],
        )
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Archived classes do not allow period transitions.',
        );

    $newStudent = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-ARCHIVED-CLASS',
        'nom' => 'Élève sans classe archivée',
        'actif' => true,
    ]);

    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/inscriptions",
            ['eleve_id' => $newStudent->id],
        )
        ->assertForbidden();

    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/presences",
            [],
        )
        ->assertForbidden();

    $tenantContext = app(TenantContext::class);
    $tenantContext->set($scope['tenant']);

    try {
        expect(fn () => app(ImportStudentsService::class)->import(
            [[
                'nom' => 'Nouvel élève interdit',
            ]],
            $scope['class']->id,
            $scope['year']->id,
        ))->toThrow(
            StudentException::class,
            'The archived class is unavailable for student import.',
        );
    } finally {
        $tenantContext->clear();
    }

    $this->assertDatabaseCount('inscriptions', 1);
});

test('class archival refuses active enrollments without changing history', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    Event::fake([ClassArchived::class]);

    $this->withToken(academicMutationToken($scope))
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/archivage",
            ['motif' => 'Demande invalide'],
        )
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath(
            'detail',
            'The class has active enrollments and cannot be archived.',
        );

    $this->assertDatabaseHas('classes', [
        'id' => $scope['class']->id,
        'active' => true,
    ]);
    $this->assertDatabaseHas('inscriptions', [
        'id' => $scope['enrollment']->id,
        'statut' => 'ACTIF',
    ]);
    $this->assertDatabaseHas('bulletins', [
        'id' => $scope['bulletin']->id,
        'inscription_id' => $scope['enrollment']->id,
    ]);
    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'CLASSE_ARCHIVEE',
        'ligne_cible_id' => $scope['class']->id,
    ]);
    Event::assertNotDispatched(ClassArchived::class);
});

test('class archival is restricted to current establishment management and strictly validated', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog);
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'FOREIGN-CLASS-ARCHIVE',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);

    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$foreignScope['class']->id}/archivage",
            ['motif' => 'Hors périmètre'],
        )
        ->assertNotFound();

    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/archivage",
            ['champ_inattendu' => true],
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['motif', 'champ_inattendu']);

    $titular = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Titulaire sans droit archivage classe',
        'telephone' => '+243999000004',
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $scope['class']->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'class-archive-forbidden',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;

    $this->app['auth']->forgetGuards();

    $this->withToken($titularToken)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/archivage",
            ['motif' => 'Tentative titulaire'],
        )
        ->assertForbidden();

    $this->assertDatabaseHas('classes', [
        'id' => $scope['class']->id,
        'active' => true,
    ]);
    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'CLASSE_ARCHIVEE',
        'ligne_cible_id' => $scope['class']->id,
    ]);
});

test('student archival is audited, state idempotent and prevents later enrollment', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $student = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-ARCHIVE',
        'nom' => 'Élève à archiver',
        'actif' => true,
    ]);
    $token = academicMutationToken($scope);
    $uri = "/api/v1/eleves/{$student->id}/archivage";
    Event::fake([StudentArchived::class]);

    expect(Gate::getPolicyFor(Eleve::class))
        ->toBeInstanceOf(ElevePolicy::class)
        ->and(is_subclass_of(
            StudentArchived::class,
            ShouldDispatchAfterCommit::class,
        ))->toBeTrue();

    $this->withToken($token)
        ->postJson($uri, ['motif' => 'Dossier clôturé'])
        ->assertOk()
        ->assertJsonPath('data.id', $student->id)
        ->assertJsonPath('data.actif', false)
        ->assertJsonMissingPath('data.tenant_id');

    $this->assertDatabaseHas('eleves', [
        'id' => $student->id,
        'actif' => false,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $scope['tenant']->id,
        'utilisateur_id' => $scope['user']->id,
        'action' => 'ELEVE_ARCHIVE',
        'table_cible' => 'eleves',
        'ligne_cible_id' => $student->id,
    ]);
    $newAuditValues = json_decode(
        (string) DB::table('audit_logs')
            ->where('action', 'ELEVE_ARCHIVE')
            ->where('ligne_cible_id', $student->id)
            ->value('nouvelles_valeurs'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($newAuditValues)->toMatchArray([
        'actif' => false,
        'motif' => 'Dossier clôturé',
    ]);
    Event::assertDispatched(
        StudentArchived::class,
        fn (StudentArchived $event): bool => $event->tenantId === $scope['tenant']->id
            && $event->establishmentId === $scope['establishment']->id
            && $event->studentId === $student->id
            && $event->actorId === $scope['user']->id,
    );
    Event::assertDispatchedTimes(StudentArchived::class, 1);

    $this->withToken($token)
        ->postJson($uri, ['motif' => 'Replay client'])
        ->assertOk()
        ->assertJsonPath('data.actif', false);

    expect(
        DB::table('audit_logs')
            ->where('action', 'ELEVE_ARCHIVE')
            ->where('ligne_cible_id', $student->id)
            ->count(),
    )->toBe(1);
    Event::assertDispatchedTimes(StudentArchived::class, 1);

    $this->withToken($token)
        ->postJson("/api/v1/classes/{$scope['class']->id}/inscriptions", [
            'eleve_id' => $student->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['eleve_id']);
});

test('student archival refuses active enrollments without changing history', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    Event::fake([StudentArchived::class]);

    $this->withToken(academicMutationToken($scope))
        ->postJson(
            "/api/v1/eleves/{$scope['student']->id}/archivage",
            ['motif' => 'Demande invalide'],
        )
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath(
            'detail',
            'The student has an active enrollment and cannot be archived.',
        );

    $this->assertDatabaseHas('eleves', [
        'id' => $scope['student']->id,
        'actif' => true,
    ]);
    $this->assertDatabaseHas('inscriptions', [
        'id' => $scope['enrollment']->id,
        'statut' => 'ACTIF',
    ]);
    $this->assertDatabaseHas('bulletins', [
        'id' => $scope['bulletin']->id,
        'inscription_id' => $scope['enrollment']->id,
    ]);
    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'ELEVE_ARCHIVE',
        'ligne_cible_id' => $scope['student']->id,
    ]);
    Event::assertNotDispatched(StudentArchived::class);
});

test('student archival is restricted to current establishment management', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog);
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'FOREIGN-ARCHIVE',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);

    $this->withToken($token)
        ->postJson(
            "/api/v1/eleves/{$foreignScope['student']->id}/archivage",
            ['motif' => 'Hors périmètre'],
        )
        ->assertNotFound();

    $titular = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Titulaire sans droit archivage',
        'telephone' => '+243999000002',
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $scope['class']->update(['titulaire_id' => $titular->id]);
    $student = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-NO-ARCHIVE',
        'nom' => 'Élève protégé',
        'actif' => true,
    ]);
    $titularToken = $titular->createToken(
        'archive-forbidden',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;

    $this->app['auth']->forgetGuards();

    $this->withToken($titularToken)
        ->postJson(
            "/api/v1/eleves/{$student->id}/archivage",
            ['motif' => 'Tentative titulaire'],
        )
        ->assertForbidden();

    $this->assertDatabaseHas('eleves', [
        'id' => $student->id,
        'actif' => true,
    ]);
    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'ELEVE_ARCHIVE',
        'ligne_cible_id' => $student->id,
    ]);
});

test('student reactivation is audited, state idempotent and preserves academic history', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $scope['enrollment']->update(['statut' => 'ABANDON']);
    $scope['student']->update(['actif' => false]);
    $note = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 13,
        'saisie_par' => $scope['user']->id,
    ]);
    $uri = "/api/v1/eleves/{$scope['student']->id}/reactivation";
    Event::fake([StudentReactivated::class]);

    expect(Gate::getPolicyFor(Eleve::class))
        ->toBeInstanceOf(ElevePolicy::class)
        ->and(is_subclass_of(
            StudentReactivated::class,
            ShouldDispatchAfterCommit::class,
        ))->toBeTrue();

    $this->withToken(academicMutationToken($scope))
        ->postJson($uri, ['motif' => 'Retour confirmé'])
        ->assertOk()
        ->assertJsonPath('data.id', $scope['student']->id)
        ->assertJsonPath('data.actif', true)
        ->assertJsonMissingPath('data.tenant_id');

    $this->assertDatabaseHas('eleves', [
        'id' => $scope['student']->id,
        'actif' => true,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $scope['tenant']->id,
        'utilisateur_id' => $scope['user']->id,
        'action' => 'ELEVE_REACTIVE',
        'table_cible' => 'eleves',
        'ligne_cible_id' => $scope['student']->id,
    ]);
    $this->assertDatabaseHas('inscriptions', [
        'id' => $scope['enrollment']->id,
        'statut' => 'ABANDON',
    ]);
    $this->assertDatabaseHas('bulletins', [
        'id' => $scope['bulletin']->id,
        'inscription_id' => $scope['enrollment']->id,
    ]);
    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'bulletin_id' => $scope['bulletin']->id,
        'valeur' => 13,
    ]);

    $newAuditValues = json_decode(
        (string) DB::table('audit_logs')
            ->where('action', 'ELEVE_REACTIVE')
            ->where('ligne_cible_id', $scope['student']->id)
            ->value('nouvelles_valeurs'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($newAuditValues)->toMatchArray([
        'actif' => true,
        'motif' => 'Retour confirmé',
    ]);
    Event::assertDispatched(
        StudentReactivated::class,
        fn (StudentReactivated $event): bool => $event->tenantId === $scope['tenant']->id
            && $event->establishmentId === $scope['establishment']->id
            && $event->studentId === $scope['student']->id
            && $event->actorId === $scope['user']->id,
    );
    Event::assertDispatchedTimes(StudentReactivated::class, 1);

    $this->withToken(academicMutationToken($scope))
        ->postJson($uri, ['motif' => 'Replay client'])
        ->assertOk()
        ->assertJsonPath('data.actif', true);

    expect(
        DB::table('audit_logs')
            ->where('action', 'ELEVE_REACTIVE')
            ->where('ligne_cible_id', $scope['student']->id)
            ->count(),
    )->toBe(1);
    Event::assertDispatchedTimes(StudentReactivated::class, 1);
});

test('student reactivation is restricted and strictly validated', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog);
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'FOREIGN-REACTIVATE',
        $scope['tenant'],
    );
    $scope['student']->update(['actif' => false]);
    $foreignScope['student']->update(['actif' => false]);
    $token = academicMutationToken($scope);

    $this->withToken($token)
        ->postJson(
            "/api/v1/eleves/{$foreignScope['student']->id}/reactivation",
            ['motif' => 'Hors périmètre'],
        )
        ->assertNotFound();

    $this->withToken($token)
        ->postJson(
            "/api/v1/eleves/{$scope['student']->id}/reactivation",
            ['champ_inattendu' => true],
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['motif', 'champ_inattendu']);

    $titular = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Titulaire sans droit réactivation',
        'telephone' => '+243999000003',
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $scope['class']->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'reactivate-forbidden',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;

    $this->app['auth']->forgetGuards();

    $this->withToken($titularToken)
        ->postJson(
            "/api/v1/eleves/{$scope['student']->id}/reactivation",
            ['motif' => 'Tentative titulaire'],
        )
        ->assertForbidden();

    $this->assertDatabaseHas('eleves', [
        'id' => $scope['student']->id,
        'actif' => false,
    ]);
    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'ELEVE_REACTIVE',
        'ligne_cible_id' => $scope['student']->id,
    ]);
});

test('legacy student import never reactivates an archived student implicitly', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $scope['enrollment']->update(['statut' => 'ABANDON']);
    $scope['student']->update(['actif' => false]);
    $tenantContext = app(TenantContext::class);
    $tenantContext->set($scope['tenant']);

    try {
        $result = app(ImportStudentsService::class)->import(
            [[
                'nom' => $scope['student']->nom,
                'postnom' => $scope['student']->postnom,
                'prenom' => $scope['student']->prenom,
            ]],
            $scope['class']->id,
            $scope['year']->id,
        );
    } finally {
        $tenantContext->clear();
    }

    expect($result)
        ->toMatchArray([
            'created' => 0,
            'errors' => [[
                'student' => $scope['student']->nom.' ',
                'error' => 'The archived student must be reactivated before enrollment.',
            ]],
        ]);
    $this->assertDatabaseHas('eleves', [
        'id' => $scope['student']->id,
        'actif' => false,
    ]);
    $this->assertDatabaseCount('inscriptions', 1);
    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'ELEVE_REACTIVE',
        'ligne_cible_id' => $scope['student']->id,
    ]);
});

test('enrollment exit is audited, state idempotent and preserves academic history', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $uri = "/api/v1/inscriptions/{$scope['enrollment']->id}/sorties";
    $note = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 14,
        'saisie_par' => $scope['user']->id,
    ]);
    $progress = SuiviEncodageNote::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'enseignant_id' => $scope['user']->id,
        'nombre_eleves' => 1,
        'nombre_notes_saisies' => 1,
        'pourcentage_avancement' => 100,
        'statut' => 'TERMINE',
    ]);
    Event::fake([EnrollmentExited::class]);

    expect(Gate::getPolicyFor(Inscription::class))
        ->toBeInstanceOf(InscriptionPolicy::class)
        ->and(is_subclass_of(
            EnrollmentExited::class,
            ShouldDispatchAfterCommit::class,
        ))->toBeTrue();

    $this->withToken($token)
        ->postJson($uri, [
            'statut' => 'ABANDON',
            'date_sortie' => '2026-10-15',
            'motif' => 'Départ volontaire',
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $scope['enrollment']->id)
        ->assertJsonPath('data.statut', 'ABANDON')
        ->assertJsonPath('data.bulletin.id', $scope['bulletin']->id)
        ->assertJsonMissingPath('data.tenant_id');

    $this->assertDatabaseHas('inscriptions', [
        'id' => $scope['enrollment']->id,
        'statut' => 'ABANDON',
    ]);
    $this->assertDatabaseHas('bulletins', [
        'id' => $scope['bulletin']->id,
        'inscription_id' => $scope['enrollment']->id,
    ]);
    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'bulletin_id' => $scope['bulletin']->id,
        'valeur' => 14,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $scope['tenant']->id,
        'utilisateur_id' => $scope['user']->id,
        'action' => 'INSCRIPTION_SORTIE',
        'table_cible' => 'inscriptions',
        'ligne_cible_id' => $scope['enrollment']->id,
    ]);
    $this->assertDatabaseHas('suivis_encodage_notes', [
        'id' => $progress->id,
        'nombre_eleves' => 0,
        'nombre_notes_saisies' => 0,
        'pourcentage_avancement' => 0,
        'statut' => 'NON_COMMENCE',
    ]);

    $newAuditValues = json_decode(
        (string) DB::table('audit_logs')
            ->where('action', 'INSCRIPTION_SORTIE')
            ->where('ligne_cible_id', $scope['enrollment']->id)
            ->value('nouvelles_valeurs'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($newAuditValues)->toMatchArray([
        'statut' => 'ABANDON',
        'date_sortie' => '2026-10-15',
        'motif' => 'Départ volontaire',
    ]);
    Event::assertDispatched(
        EnrollmentExited::class,
        fn (EnrollmentExited $event): bool => $event->tenantId === $scope['tenant']->id
            && $event->establishmentId === $scope['establishment']->id
            && $event->schoolYearId === $scope['year']->id
            && $event->classId === $scope['class']->id
            && $event->studentId === $scope['student']->id
            && $event->enrollmentId === $scope['enrollment']->id
            && $event->actorId === $scope['user']->id
            && $event->status === 'ABANDON'
            && $event->exitDate === '2026-10-15',
    );
    Event::assertDispatchedTimes(EnrollmentExited::class, 1);

    $this->withToken($token)
        ->postJson($uri, [
            'statut' => 'ABANDON',
            'date_sortie' => '2026-10-20',
            'motif' => 'Replay client',
        ])
        ->assertOk()
        ->assertJsonPath('data.statut', 'ABANDON');

    expect(
        DB::table('audit_logs')
            ->where('action', 'INSCRIPTION_SORTIE')
            ->where('ligne_cible_id', $scope['enrollment']->id)
            ->count(),
    )->toBe(1);
    Event::assertDispatchedTimes(EnrollmentExited::class, 1);

    $this->withToken($token)
        ->postJson($uri, [
            'statut' => 'EXCLU',
            'date_sortie' => '2026-10-20',
            'motif' => 'Transition contradictoire',
        ])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The enrollment has already reached another final state.',
        );
});

test('enrollment exit validates its command and blocks later note and attendance writes', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $uri = "/api/v1/inscriptions/{$scope['enrollment']->id}/sorties";

    $this->withToken($token)
        ->postJson($uri, [
            'statut' => 'ABANDON',
            'date_sortie' => '2026-10-15',
            'motif' => 'Champ client interdit',
            'tenant_id' => $scope['tenant']->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_id']);

    $this->withToken($token)
        ->postJson($uri, [
            'statut' => 'TRANSFERE',
            'date_sortie' => '2026-08-31',
            'motif' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'statut',
            'date_sortie',
            'motif',
        ]);

    $this->assertDatabaseHas('inscriptions', [
        'id' => $scope['enrollment']->id,
        'statut' => 'ACTIF',
    ]);

    $this->withToken($token)
        ->postJson($uri, [
            'statut' => 'EXCLU',
            'date_sortie' => '2027-02-10',
            'motif' => 'Décision disciplinaire',
        ])
        ->assertOk()
        ->assertJsonPath('data.statut', 'EXCLU');

    $this->withToken($token)
        ->postJson('/api/v1/notes', [
            'bulletin_id' => $scope['bulletin']->id,
            'node_id' => $scope['node']->id,
            'periode_id' => $scope['period']->id,
            'valeur' => 12,
        ])
        ->assertMethodNotAllowed();

    $this->withToken($token)
        ->postJson("/api/v1/classes/{$scope['class']->id}/presences", [
            'inscription_id' => $scope['enrollment']->id,
            'date_presence' => '2027-02-11',
            'statut' => 'ABSENT',
        ])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Attendance can only be recorded for active enrollments.',
        );

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('presences_eleves', 0);
});

test('enrollment exit is scoped to management and the exact active class titular', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'EXIT-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'EXIT-B',
        $scope['tenant'],
    );
    $managementToken = academicMutationToken($scope);
    $payload = [
        'statut' => 'ABANDON',
        'date_sortie' => '2026-11-05',
        'motif' => 'Sortie contrôlée',
    ];

    $this->withToken($managementToken)
        ->postJson(
            "/api/v1/inscriptions/{$foreignScope['enrollment']->id}/sorties",
            $payload,
        )
        ->assertNotFound();

    $otherTeacher = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Titulaire non affecté',
        'telephone' => '+243999000003',
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $titularRole = Role::query()->firstOrCreate(
        ['tenant_id' => null, 'code' => 'TITULAIRE'],
        ['nom' => 'Titulaire', 'system_role' => true],
    );
    $otherTeacher->roles()->attach($titularRole);
    $otherTeacherToken = $otherTeacher->createToken(
        'wrong-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($otherTeacherToken)
        ->postJson(
            "/api/v1/inscriptions/{$scope['enrollment']->id}/sorties",
            $payload,
        )
        ->assertForbidden();

    $scope['class']->update(['titulaire_id' => $otherTeacher->id]);
    $this->app['auth']->forgetGuards();

    $this->withToken($otherTeacherToken)
        ->postJson(
            "/api/v1/inscriptions/{$scope['enrollment']->id}/sorties",
            $payload,
        )
        ->assertOk()
        ->assertJsonPath('data.statut', 'ABANDON');
});

test('enrollment creates one report card atomically and rejects duplicate submissions', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $student = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-ATOMIC',
        'nom' => 'Élève atomique',
        'actif' => true,
    ]);
    $token = academicMutationToken($scope);
    $uri = "/api/v1/classes/{$scope['class']->id}/inscriptions";
    $payload = [
        'eleve_id' => $student->id,
        'numero_inscription' => 'INS-ATOMIC',
        'date_inscription' => '2026-09-10',
    ];
    Event::fake([StudentEnrolled::class]);

    $response = $this->withToken($token)
        ->postJson($uri, $payload)
        ->assertCreated()
        ->assertJsonPath('data.eleve.id', $student->id)
        ->assertJsonPath('data.numero_inscription', 'INS-ATOMIC')
        ->assertJsonPath('data.date_inscription', '2026-09-10');

    $enrollmentId = $response->json('data.id');
    $reportCardId = $response->json('data.bulletin.id');

    expect($enrollmentId)->toBeInt()
        ->and($reportCardId)->toBeInt()
        ->and(is_subclass_of(
            StudentEnrolled::class,
            ShouldDispatchAfterCommit::class,
        ))->toBeTrue();

    Event::assertDispatched(
        StudentEnrolled::class,
        fn (StudentEnrolled $event): bool => $event->tenantId === $scope['tenant']->id
            && $event->establishmentId === $scope['establishment']->id
            && $event->schoolYearId === $scope['year']->id
            && $event->classId === $scope['class']->id
            && $event->studentId === $student->id
            && $event->enrollmentId === $enrollmentId
            && $event->reportCardId === $reportCardId,
    );
    Event::assertDispatchedTimes(StudentEnrolled::class, 1);

    $this->assertDatabaseHas('inscriptions', [
        'id' => $enrollmentId,
        'tenant_id' => $scope['tenant']->id,
        'eleve_id' => $student->id,
        'classe_id' => $scope['class']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'statut' => 'ACTIF',
    ]);
    $this->assertDatabaseHas('bulletins', [
        'id' => $reportCardId,
        'tenant_id' => $scope['tenant']->id,
        'inscription_id' => $enrollmentId,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'decision_statut' => 'EN_COURS',
        'verrouille' => false,
    ]);
    $this->assertDatabaseCount('inscriptions', 2);
    $this->assertDatabaseCount('bulletins', 2);
    $this->assertDatabaseCount('notes', 0);

    $this->withToken($token)
        ->postJson($uri, $payload)
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath(
            'detail',
            'The student is already enrolled for this school year. A transfer is required.',
        );

    $otherClass = Classe::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'cycle_id' => $scope['class']->cycle_id,
        'niveau_id' => $scope['class']->niveau_id,
        'system_template_id' => $scope['class']->system_template_id,
        'nom' => 'Classe de transfert',
        'active' => true,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/classes/{$otherClass->id}/inscriptions", [
            'eleve_id' => $student->id,
        ])
        ->assertConflict();

    $this->assertDatabaseCount('inscriptions', 2);
    $this->assertDatabaseCount('bulletins', 2);
    Event::assertDispatchedTimes(StudentEnrolled::class, 1);
});

test('enrollment rolls back without an active report card model and rejects closed contexts', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $student = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-ROLLBACK',
        'nom' => 'Élève rollback',
        'actif' => true,
    ]);
    $uri = "/api/v1/classes/{$scope['class']->id}/inscriptions";
    $scope['model']->update(['actif' => false]);
    Event::fake([StudentEnrolled::class]);

    $this->withToken($token)
        ->postJson($uri, ['eleve_id' => $student->id])
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath(
            'detail',
            'No active report card configuration is available for this class.',
        );

    $this->assertDatabaseMissing('inscriptions', ['eleve_id' => $student->id]);
    $this->assertDatabaseCount('bulletins', 1);
    Event::assertNotDispatched(StudentEnrolled::class);

    $scope['model']->update(['actif' => true]);
    $scope['year']->update(['active' => false]);
    $closedYearStudent = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-CLOSED-YEAR',
        'nom' => 'Élève année fermée',
        'actif' => true,
    ]);

    $this->withToken($token)
        ->postJson($uri, ['eleve_id' => $closedYearStudent->id])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The school year is not open for enrollment.',
        );

    $scope['year']->update(['active' => true]);
    $inactiveStudent = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-INACTIVE',
        'nom' => 'Élève inactif',
        'actif' => false,
    ]);

    $this->withToken($token)
        ->postJson($uri, ['eleve_id' => $inactiveStudent->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['eleve_id']);

    $this->assertDatabaseCount('inscriptions', 1);
    $this->assertDatabaseCount('bulletins', 1);
    Event::assertNotDispatched(StudentEnrolled::class);
});

test('report card composition is complete, sparse and query bounded', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $uri = "/api/v1/inscriptions/{$scope['enrollment']->id}/bulletin";

    $this->withToken($token)->getJson($uri)->assertOk();

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.id', $scope['bulletin']->id)
        ->assertJsonPath('data.inscription.id', $scope['enrollment']->id)
        ->assertJsonPath('data.inscription.eleve.id', $scope['student']->id)
        ->assertJsonPath('data.classe.id', $scope['class']->id)
        ->assertJsonPath('data.modele.id', $scope['model']->id)
        ->assertJsonPath('data.nodes.0.id', $scope['node']->id)
        ->assertJsonPath('data.periodes.0.id', $scope['period']->id)
        ->assertJsonPath('data.slots.0.maxima', '20.00')
        ->assertJsonPath('data.slots.0.coefficient', '1.00')
        ->assertJsonPath('data.slots.0.note', null)
        ->assertJsonMissingPath('data.tenant_id')
        ->assertJsonMissingPath('data.inscription.tenant_id');

    $singleSlotQueryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    foreach (range(2, 5) as $order) {
        $node = ModeleNode::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'type_node' => 'MATIERE',
            'libelle' => "Cours {$order}",
            'ordre' => $order,
            'afficher_ligne' => true,
            'calculer_sous_total' => false,
            'obligatoire' => true,
        ]);
        ModeleMaximum::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'node_id' => $node->id,
            'periode_id' => $scope['period']->id,
            'maxima' => 20,
            'coefficient' => 1,
        ]);
    }

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(5, 'data.nodes')
        ->assertJsonCount(5, 'data.slots');

    $multipleSlotQueryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    expect($multipleSlotQueryCount)->toBe($singleSlotQueryCount);

    Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 14,
        'commentaire' => 'Acquis',
        'date_saisie' => '2026-10-10 08:30:00',
    ]);

    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.slots.0.note.statut_resultat', 'SAISIE')
        ->assertJsonPath('data.slots.0.note.valeur', '14.00')
        ->assertJsonPath('data.slots.0.note.commentaire', 'Acquis');

    $this->assertDatabaseCount('notes', 1);
});

test('report card reads authorize current managers and titulars including archives', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $uri = "/api/v1/inscriptions/{$scope['enrollment']->id}/bulletin";

    $titular = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Titulaire courant',
        'telephone' => '+243999000001',
        'email' => 'titulaire-courant@example.test',
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $scope['class']->update([
        'titulaire_id' => $titular->id,
        'active' => false,
    ]);
    $scope['enrollment']->update(['statut' => 'TRANSFERE']);
    $titularToken = academicMutationToken([
        'tenant' => $scope['tenant'],
        'user' => $titular,
    ]);

    $this->withToken($titularToken)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.classe.active', false)
        ->assertJsonPath('data.inscription.statut', 'TRANSFERE');

    $scope['class']->update(['titulaire_id' => $scope['user']->id]);
    $titularRole = Role::query()->firstOrCreate(
        [
            'tenant_id' => null,
            'code' => 'TITULAIRE',
        ],
        [
            'nom' => 'Titulaire',
            'system_role' => true,
        ],
    );
    $titular->roles()->attach($titularRole->id);

    $this->withToken($titularToken)->getJson($uri)->assertForbidden();
});

test('report card reads fail closed for foreign, missing and inconsistent records', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog);
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'FOREIGN-REPORT',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);

    $this->withToken($token)
        ->getJson("/api/v1/inscriptions/{$foreignScope['enrollment']->id}/bulletin")
        ->assertNotFound();
    $this->withToken($token)
        ->getJson('/api/v1/inscriptions/999999/bulletin')
        ->assertNotFound();

    $studentWithoutReportCard = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-WITHOUT-REPORT',
        'nom' => 'Sans bulletin',
        'actif' => true,
    ]);
    $enrollmentWithoutReportCard = Inscription::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'eleve_id' => $studentWithoutReportCard->id,
        'classe_id' => $scope['class']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'statut' => 'ACTIF',
    ]);

    $this->withToken($token)
        ->getJson(
            "/api/v1/inscriptions/{$enrollmentWithoutReportCard->id}/bulletin",
        )
        ->assertNotFound();

    $scope['bulletin']->update(['classe_id' => $foreignScope['class']->id]);

    $this->withToken($token)
        ->getJson("/api/v1/inscriptions/{$scope['enrollment']->id}/bulletin")
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath(
            'detail',
            'The report card is inconsistent with its enrollment.',
        );

    $scope['bulletin']->update(['classe_id' => $scope['class']->id]);
    Bulletin::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'inscription_id' => $scope['enrollment']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $foreignScope['model']->id,
        'decision_statut' => 'EN_COURS',
        'verrouille' => false,
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/inscriptions/{$scope['enrollment']->id}/bulletin")
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Multiple report cards are attached to this enrollment.',
        );
});

test('legacy global note creation and corrections are retired', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);

    $this->withToken($token)
        ->postJson('/api/v1/notes', [
            'bulletin_id' => $scope['bulletin']->id,
            'node_id' => $scope['node']->id,
            'periode_id' => $scope['period']->id,
            'valeur' => 14,
        ])
        ->assertMethodNotAllowed()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 405);

    $note = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 14,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);

    foreach (['putJson', 'patchJson'] as $method) {
        $this->withToken($token)
            ->{$method}("/api/v1/notes/{$note->id}", [
                'valeur' => 16,
                'lock_version' => 1,
            ])
            ->assertMethodNotAllowed()
            ->assertJsonPath('status', 405);
    }

    expect($note->refresh()->valeur)->toBe('14.00')
        ->and($note->lock_version)->toBe(1);
    $this->assertDatabaseCount('notes_historiques', 0);
});

test('note resources are scoped paginated and hide internal columns', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'A');
    $foreignScope = createAcademicMutationScope($catalog, 'B', $scope['tenant']);
    $token = academicMutationToken($scope);
    $note = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 13,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    $foreignNote = Note::withoutGlobalScopes()->create([
        'tenant_id' => $foreignScope['tenant']->id,
        'bulletin_id' => $foreignScope['bulletin']->id,
        'node_id' => $foreignScope['node']->id,
        'periode_id' => $foreignScope['period']->id,
        'valeur' => 11,
        'saisie_par' => $foreignScope['user']->id,
        'date_saisie' => now(),
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/notes/{$note->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $note->id)
        ->assertJsonPath('data.eleve.num_permanent', 'MAT-A')
        ->assertJsonMissingPath('data.tenant_id')
        ->assertJsonMissingPath('data.bulletin');

    foreach ([
        '/api/v1/notes?per_page=1',
        "/api/v1/classes/{$scope['class']->id}/notes?per_page=1",
    ] as $uri) {
        $this->withToken($token)
            ->getJson($uri)
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $note->id)
            ->assertJsonMissingPath('data.0.tenant_id')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'valeur',
                    'eleve',
                    'cours',
                    'periode',
                ]],
                'links',
                'meta',
            ]);
    }

    $this->withToken($token)
        ->getJson('/api/v1/notes?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);

    foreach ([
        '/api/v1/notes?unexpected=1',
        "/api/v1/classes/{$scope['class']->id}/notes?unexpected=1",
        "/api/v1/notes/{$note->id}?unexpected=1",
    ] as $uri) {
        $this->withToken($token)
            ->getJson($uri)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unexpected']);
    }

    $this->withToken($token)
        ->getJson("/api/v1/notes/{$foreignNote->id}")
        ->assertNotFound();
});

test('note reads authorize managers exact titulars and current assigned teachers', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00:00'));

    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'NOTE-READ-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'NOTE-READ-B',
        $scope['tenant'],
    );
    $note = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 12,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    $foreignNote = Note::withoutGlobalScopes()->create([
        'tenant_id' => $foreignScope['tenant']->id,
        'bulletin_id' => $foreignScope['bulletin']->id,
        'node_id' => $foreignScope['node']->id,
        'periode_id' => $foreignScope['period']->id,
        'valeur' => 9,
        'saisie_par' => $foreignScope['user']->id,
        'date_saisie' => now(),
    ]);
    $firstHistoryId = DB::table('notes_historiques')->insertGetId([
        'note_id' => $note->id,
        'ancien_statut_resultat' => 'SAISIE',
        'nouveau_statut_resultat' => 'SAISIE',
        'ancienne_valeur' => 10,
        'nouvelle_valeur' => 11,
        'modifie_par' => $scope['user']->id,
        'motif' => 'Première correction',
        'created_at' => '2026-10-15 08:01:00',
        'updated_at' => '2026-10-15 08:01:00',
    ]);
    $latestHistoryId = DB::table('notes_historiques')->insertGetId([
        'note_id' => $note->id,
        'ancien_statut_resultat' => 'SAISIE',
        'nouveau_statut_resultat' => 'SAISIE',
        'ancienne_valeur' => 11,
        'nouvelle_valeur' => 12,
        'modifie_par' => $scope['user']->id,
        'motif' => 'Deuxième correction',
        'created_at' => '2026-10-15 08:02:00',
        'updated_at' => '2026-10-15 08:02:00',
    ]);
    $noteUri = "/api/v1/notes/{$note->id}";
    $historyUri = "{$noteUri}/historique";
    $classNotesUri = "/api/v1/classes/{$scope['class']->id}/notes";

    $this->getJson('/api/v1/notes')->assertUnauthorized();
    $this->getJson($noteUri)->assertUnauthorized();
    $this->getJson($historyUri)->assertUnauthorized();
    expect(Gate::getPolicyFor(Note::class))->toBeInstanceOf(NotePolicy::class);

    $managementToken = academicMutationToken($scope);
    $this->withToken($managementToken)
        ->getJson("{$historyUri}?per_page=1&page=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $latestHistoryId)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2);
    $this->withToken($managementToken)
        ->getJson("{$historyUri}?per_page=101")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
    $this->withToken($managementToken)
        ->getJson("{$historyUri}?unexpected=1")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($managementToken)
        ->getJson("/api/v1/notes/{$foreignNote->id}")
        ->assertNotFound();
    $this->withToken($managementToken)
        ->getJson("/api/v1/notes/{$foreignNote->id}/historique")
        ->assertNotFound();

    $titular = createClassTitularCandidate($scope, 'NOTE-READ');
    $scope['class']->update(['titulaire_id' => $titular->id]);
    $otherClass = Classe::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'cycle_id' => $catalog['cycle_id'],
        'niveau_id' => $catalog['niveau_id'],
        'system_template_id' => $catalog['template_id'],
        'nom' => 'Classe hors titulariat',
        'active' => true,
    ]);
    $titularToken = $titular->createToken(
        'titular-note-read',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($titularToken)
        ->getJson('/api/v1/notes')
        ->assertForbidden();
    $this->withToken($titularToken)
        ->getJson($classNotesUri)
        ->assertOk()
        ->assertJsonPath('data.0.id', $note->id);
    $this->withToken($titularToken)
        ->getJson("/api/v1/classes/{$otherClass->id}/notes")
        ->assertForbidden();
    $this->withToken($titularToken)
        ->getJson($noteUri)
        ->assertOk()
        ->assertJsonPath('data.id', $note->id);
    $this->withToken($titularToken)
        ->getJson($historyUri)
        ->assertOk()
        ->assertJsonPath('data.0.id', $latestHistoryId)
        ->assertJsonPath('data.1.id', $firstHistoryId);

    $teacher = createCourseAssignmentTeacher($scope, 'NOTE-READ');
    $unassignedTeacher = createCourseAssignmentTeacher(
        $scope,
        'NOTE-READ-UNASSIGNED',
    );
    $assignment = AttributionCours::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $teacherToken = $teacher->createToken(
        'teacher-note-read',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $unassignedToken = $unassignedTeacher->createToken(
        'unassigned-note-read',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($teacherToken)
        ->getJson('/api/v1/notes')
        ->assertForbidden();
    $this->withToken($teacherToken)
        ->getJson($classNotesUri)
        ->assertForbidden();
    $this->withToken($teacherToken)
        ->getJson($noteUri)
        ->assertOk()
        ->assertJsonPath('data.id', $note->id);
    $this->withToken($teacherToken)
        ->getJson($historyUri)
        ->assertOk()
        ->assertJsonPath('data.0.id', $latestHistoryId);

    $this->app['auth']->forgetGuards();
    $this->withToken($unassignedToken)
        ->getJson($noteUri)
        ->assertForbidden();
    $this->withToken($unassignedToken)
        ->getJson($historyUri)
        ->assertForbidden();

    $assignment->update(['date_fin' => '2026-10-14']);
    $this->app['auth']->forgetGuards();
    $this->withToken($teacherToken)
        ->getJson($noteUri)
        ->assertForbidden();
    $this->withToken($teacherToken)
        ->getJson($historyUri)
        ->assertForbidden();
});

test('retired global note mutations cannot bypass lock or establishment boundaries', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'A');
    $foreignScope = createAcademicMutationScope($catalog, 'B', $scope['tenant']);
    $unassignedUser = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Non assigné',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $unassignedToken = $unassignedUser->createToken(
        'unassigned',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $payload = [
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 12,
    ];

    $this->withToken($unassignedToken)
        ->postJson('/api/v1/notes', $payload)
        ->assertMethodNotAllowed();

    VerrouillagePeriode::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'periode_id' => $scope['period']->id,
        'verrouille' => true,
        'verrouille_par' => $scope['user']->id,
        'date_verrouillage' => now(),
    ]);

    $this->withToken(academicMutationToken($scope))
        ->postJson('/api/v1/notes', $payload)
        ->assertMethodNotAllowed()
        ->assertHeader('Content-Type', 'application/problem+json');

    $foreignNote = Note::withoutGlobalScopes()->create([
        'tenant_id' => $foreignScope['tenant']->id,
        'bulletin_id' => $foreignScope['bulletin']->id,
        'node_id' => $foreignScope['node']->id,
        'periode_id' => $foreignScope['period']->id,
        'valeur' => 10,
        'saisie_par' => $foreignScope['user']->id,
    ]);

    $this->withToken(academicMutationToken($scope))
        ->getJson("/api/v1/notes/{$foreignNote->id}/historique")
        ->assertNotFound();
});

test('period locking is scoped audited and idempotent', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    SuiviEncodageNote::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'enseignant_id' => $scope['user']->id,
        'nombre_eleves' => 1,
        'nombre_notes_saisies' => 1,
        'pourcentage_avancement' => 100,
        'statut' => 'TERMINE',
    ]);
    $lockUri = "/api/v1/classes/{$scope['class']->id}/periodes/{$scope['period']->id}/verrouillages";
    $reopenUri = "/api/v1/classes/{$scope['class']->id}/periodes/{$scope['period']->id}/reouvertures";

    $this->withToken($token)
        ->postJson($lockUri, ['motif' => 'Fin de la saisie'])
        ->assertSuccessful()
        ->assertJsonPath('data.verrouille', true);

    $this->withToken($token)
        ->postJson($lockUri, ['motif' => 'Rejeu identique'])
        ->assertSuccessful();

    $this->assertDatabaseCount('verrouillages_periodes', 1);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseHas('suivis_encodage_notes', [
        'id' => SuiviEncodageNote::query()->firstOrFail()->id,
        'statut' => 'VERROUILLE',
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/notes', [
            'bulletin_id' => $scope['bulletin']->id,
            'node_id' => $scope['node']->id,
            'periode_id' => $scope['period']->id,
            'valeur' => 12,
        ])
        ->assertMethodNotAllowed();

    $this->withToken($token)
        ->postJson($reopenUri, ['motif' => 'Correction autorisée'])
        ->assertSuccessful()
        ->assertJsonPath('data.verrouille', false);

    $this->withToken($token)
        ->postJson($reopenUri, ['motif' => 'Rejeu identique'])
        ->assertSuccessful();

    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseHas('suivis_encodage_notes', [
        'id' => SuiviEncodageNote::query()->firstOrFail()->id,
        'statut' => 'TERMINE',
    ]);
});

test('academic administrative mutations require a management role', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $unauthorizedUser = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Enseignant sans autorisation',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $unauthorizedToken = $unauthorizedUser->createToken(
        'period-transition-forbidden',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;

    expect(Gate::getPolicyFor(Etablissement::class))
        ->toBeInstanceOf(EtablissementPolicy::class);

    foreach ([
        '/api/v1/ouvertures-annuelles',
        '/api/v1/classes',
        '/api/v1/eleves',
        "/api/v1/classes/{$scope['class']->id}/inscriptions",
        "/api/v1/classes/{$scope['class']->id}/presences",
        "/api/v1/classes/{$scope['class']->id}/periodes/{$scope['period']->id}/verrouillages",
    ] as $uri) {
        $this->withToken($unauthorizedToken)
            ->postJson($uri)
            ->assertForbidden();
    }

    $this->assertDatabaseCount('annees_scolaires', 1);
    $this->assertDatabaseCount('classes', 1);
    $this->assertDatabaseCount('eleves', 1);
    $this->assertDatabaseCount('inscriptions', 1);
    $this->assertDatabaseCount('presences_eleves', 0);
    $this->assertDatabaseCount('verrouillages_periodes', 0);
});

test('academic administration rejects inactive users and roles from another tenant', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $foreignTenant = Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'Tenant étranger',
        'slug' => 'academic-policy-foreign-'.Str::lower(Str::random(6)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
    $foreignRole = Role::query()->create([
        'tenant_id' => $foreignTenant->id,
        'code' => 'ADMIN_ECOLE',
        'nom' => 'Administrateur étranger',
        'system_role' => false,
    ]);
    $foreignRoleUser = Utilisateur::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'nom' => 'Administrateur hors périmètre',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);
    $foreignRoleUser->roles()->attach($foreignRole);
    $foreignRoleToken = $foreignRoleUser->createToken(
        'foreign-management-role',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;

    $this->withToken($foreignRoleToken)
        ->postJson('/api/v1/eleves', ['nom' => 'Refusé'])
        ->assertForbidden();

    $this->withToken($foreignRoleToken)
        ->postJson("/api/v1/classes/{$scope['class']->id}/presences")
        ->assertForbidden();

    $inactiveUser = $scope['user'];
    $inactiveToken = academicMutationToken($scope);
    $inactiveUser->update(['actif' => false]);

    $this->withToken($inactiveToken)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/periodes/{$scope['period']->id}/verrouillages",
            ['motif' => 'Utilisateur désactivé'],
        )
        ->assertForbidden();

    $this->assertDatabaseMissing('eleves', ['nom' => 'Refusé']);
    $this->assertDatabaseCount('verrouillages_periodes', 0);
});

test('period locking rejects incomplete or foreign academic scopes', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'A');
    $foreignScope = createAcademicMutationScope($catalog, 'B', $scope['tenant']);
    $token = academicMutationToken($scope);
    SuiviEncodageNote::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'enseignant_id' => $scope['user']->id,
        'nombre_eleves' => 1,
        'nombre_notes_saisies' => 0,
        'pourcentage_avancement' => 0,
        'statut' => 'EN_COURS',
    ]);

    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/periodes/{$scope['period']->id}/verrouillages",
            ['motif' => 'Tentative incomplète'],
        )
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$foreignScope['class']->id}/periodes/{$foreignScope['period']->id}/verrouillages",
            ['motif' => 'Périmètre étranger'],
        )
        ->assertNotFound();

    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/periodes/{$foreignScope['period']->id}/verrouillages",
            ['motif' => 'Période étrangère'],
        )
        ->assertNotFound();
});

test('legacy global CSV note import is retired before file processing', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $payload = [
        'classe_id' => $scope['class']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
    ];

    $this->withToken($token)
        ->postJson('/api/v1/notes/import', [
            ...$payload,
            'file' => UploadedFile::fake()->createWithContent(
                'notes.csv',
                "matricule,note\nMAT-A,12\nINCONNU,14\n",
            ),
        ])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('suivis_encodage_notes', 0);
});

test('legacy global note import rejects CSV and XLSX payloads', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $payload = [
        'classe_id' => $scope['class']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
    ];

    $this->withToken($token)
        ->postJson('/api/v1/notes/import', [
            ...$payload,
            'file' => UploadedFile::fake()->createWithContent(
                'notes.csv',
                "matricule,note\nmat-a,14\n",
            ),
        ])
        ->assertNotFound();

    $this->withToken($token)
        ->postJson('/api/v1/notes/import', [
            ...$payload,
            'file' => noteXlsxUpload([
                ['matricule', 'note'],
                ['MAT-A', 15],
            ]),
        ])
        ->assertNotFound();

    $this->withToken($token)
        ->postJson('/api/v1/notes/import', [
            ...$payload,
            'file' => noteXlsxUpload([
                ['matricule', 'note', 'lock_version'],
                ['MAT-A', 15, 1],
            ]),
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('notes_historiques', 0);
});

test('legacy global note import never validates or writes XLSX payloads', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $payload = [
        'classe_id' => $scope['class']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
    ];

    $this->withToken($token)
        ->postJson('/api/v1/notes/import', [
            ...$payload,
            'file' => noteXlsxUpload([
                ['matricule', 'note'],
                ['MAT-A', 12],
                ['INCONNU', 14],
            ]),
        ])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('suivis_encodage_notes', 0);

    $this->withToken($token)
        ->postJson('/api/v1/notes/import', [
            ...$payload,
            'file' => UploadedFile::fake()->createWithContent(
                'notes.xlsx',
                'not an XLSX spreadsheet',
            ),
        ])
        ->assertNotFound();

    $scope['enrollment']->update(['statut' => 'ABANDON']);

    $this->withToken($token)
        ->postJson('/api/v1/notes/import', [
            ...$payload,
            'file' => UploadedFile::fake()->createWithContent(
                'notes.csv',
                "matricule,note\nMAT-A,12\n",
            ),
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('notes', 0);
});

test('school resources are paginated and hidden across establishments', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'A');
    $foreignScope = createAcademicMutationScope($catalog, 'B', $scope['tenant']);
    $token = academicMutationToken($scope);

    $this->withToken($token)
        ->getJson('/api/v1/classes?per_page=1')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    $this->withToken($token)
        ->getJson('/api/v1/eleves?per_page=1')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    $this->withToken($token)
        ->getJson("/api/v1/classes/{$scope['class']->id}/inscriptions?per_page=1")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    $this->withToken($token)
        ->getJson("/api/v1/classes/{$scope['class']->id}/bulletins?per_page=1")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    foreach ([
        "/api/v1/classes/{$foreignScope['class']->id}",
        "/api/v1/eleves/{$foreignScope['student']->id}",
        "/api/v1/inscriptions/{$foreignScope['enrollment']->id}",
        "/api/v1/bulletins/{$foreignScope['bulletin']->id}",
    ] as $uri) {
        $this->withToken($token)->getJson($uri)->assertNotFound();
    }
});

test('bulletin reads are scoped filtered paginated and consistency checked', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'BULLETIN-READ-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'BULLETIN-READ-B',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);
    $classUri = "/api/v1/classes/{$scope['class']->id}/bulletins";
    $bulletinUri = "/api/v1/bulletins/{$scope['bulletin']->id}";
    $additionalBulletins = collect();

    $scope['bulletin']->update(['decision_statut' => 'PASSE']);

    foreach (range(1, 4) as $index) {
        $student = Eleve::withoutGlobalScopes()->create([
            'tenant_id' => $scope['tenant']->id,
            'etablissement_id' => $scope['establishment']->id,
            'num_permanent' => "BULLETIN-READ-{$index}",
            'nom' => "Élève bulletin {$index}",
            'actif' => true,
        ]);
        $enrollment = Inscription::withoutGlobalScopes()->create([
            'tenant_id' => $scope['tenant']->id,
            'eleve_id' => $student->id,
            'classe_id' => $scope['class']->id,
            'annee_scolaire_id' => $scope['year']->id,
            'statut' => 'ACTIF',
        ]);
        $additionalBulletins->push(
            Bulletin::withoutGlobalScopes()->create([
                'tenant_id' => $scope['tenant']->id,
                'inscription_id' => $enrollment->id,
                'classe_id' => $scope['class']->id,
                'modele_bulletin_id' => $scope['model']->id,
                'decision_statut' => 'EN_COURS',
                'verrouille' => false,
            ]),
        );
    }

    $this->getJson($classUri)->assertUnauthorized();
    $this->getJson($bulletinUri)->assertUnauthorized();
    $this->getJson('/api/v1/bulletins')->assertNotFound();
    expect(Gate::getPolicyFor(Bulletin::class))
        ->toBeInstanceOf(BulletinPolicy::class);

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->withToken($token)
        ->getJson("{$classUri}?decision_statut=EN_COURS&per_page=2&page=1")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 4)
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    $queryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(18);

    $this->withToken($token)
        ->getJson("{$classUri}?decision_statut=PASSE")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $scope['bulletin']->id);
    $this->withToken($token)
        ->getJson($bulletinUri)
        ->assertOk()
        ->assertJsonPath('data.id', $scope['bulletin']->id)
        ->assertJsonPath('data.eleve.id', $scope['student']->id)
        ->assertJsonMissingPath('data.eleve.telephone_parent')
        ->assertJsonMissingPath('data.eleve.adresse')
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    foreach ([
        "{$classUri}?per_page=101" => 'per_page',
        "{$classUri}?decision_statut=INVALIDE" => 'decision_statut',
        "{$classUri}?tenant_id={$scope['tenant']->id}" => 'tenant_id',
        "{$bulletinUri}?unexpected=1" => 'unexpected',
    ] as $uri => $field) {
        $this->withToken($token)
            ->getJson($uri)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    }

    $this->withToken($token)
        ->getJson(
            "/api/v1/classes/{$foreignScope['class']->id}/bulletins",
        )
        ->assertNotFound();
    $this->withToken($token)
        ->getJson("/api/v1/bulletins/{$foreignScope['bulletin']->id}")
        ->assertNotFound();

    $inconsistentBulletin = $additionalBulletins->first();
    $inconsistentEnrollmentId = $inconsistentBulletin->inscription_id;
    Inscription::withoutGlobalScopes()
        ->whereKey($inconsistentEnrollmentId)
        ->update(['classe_id' => $foreignScope['class']->id]);

    $this->withToken($token)
        ->getJson("/api/v1/bulletins/{$inconsistentBulletin->id}")
        ->assertConflict()
        ->assertJsonPath('detail', 'The report card is inconsistent.');
});

test('only management and the exact titular read complete bulletins', function () {
    $scope = createAcademicMutationScope(
        createAcademicMutationCatalog(),
        'BULLETIN-AUTH',
    );
    $classUri = "/api/v1/classes/{$scope['class']->id}/bulletins";
    $bulletinUri = "/api/v1/bulletins/{$scope['bulletin']->id}";
    $titular = createClassTitularCandidate($scope, 'BULLETIN-AUTH');
    Classe::withoutGlobalScopes()
        ->whereKey($scope['class']->id)
        ->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'bulletin-titular-read',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;

    $this->app['auth']->forgetGuards();
    $this->withToken($titularToken)
        ->getJson($classUri)
        ->assertOk()
        ->assertJsonCount(1, 'data');
    $this->withToken($titularToken)
        ->getJson($bulletinUri)
        ->assertOk();

    Classe::withoutGlobalScopes()
        ->whereKey($scope['class']->id)
        ->update(['active' => false]);
    $this->withToken($titularToken)
        ->getJson($classUri)
        ->assertOk();

    $assignedTeacher = createCourseAssignmentTeacher(
        $scope,
        'BULLETIN-AUTH',
    );
    AttributionCours::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $assignedTeacher->id,
        'actif' => true,
    ]);
    $teacherToken = $assignedTeacher->createToken(
        'bulletin-assigned-teacher-read',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;

    $this->app['auth']->forgetGuards();
    $this->withToken($teacherToken)
        ->getJson($classUri)
        ->assertForbidden();
    $this->withToken($teacherToken)
        ->getJson($bulletinUri)
        ->assertForbidden();

    Classe::withoutGlobalScopes()
        ->whereKey($scope['class']->id)
        ->update(['titulaire_id' => null]);
    $this->app['auth']->forgetGuards();
    $this->withToken($titularToken)
        ->getJson($classUri)
        ->assertForbidden();
    $this->withToken($titularToken)
        ->getJson($bulletinUri)
        ->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->withToken(academicMutationToken($scope))
        ->getJson($bulletinUri)
        ->assertOk();
});

test('management and the exact titular list ordered class courses without n plus one queries', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $earlierCourse = ModeleNode::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'type_node' => 'MATIERE',
        'libelle' => 'Cours prioritaire',
        'ordre' => 0,
        'afficher_ligne' => true,
        'calculer_sous_total' => false,
        'obligatoire' => true,
    ]);
    ModeleMaximum::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $earlierCourse->id,
        'periode_id' => $scope['period']->id,
        'maxima' => 10,
        'coefficient' => 1,
    ]);
    $assignedTeacher = createCourseAssignmentTeacher($scope, 'LIST');
    AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $earlierCourse->id,
        'enseignant_id' => $assignedTeacher->id,
        'actif' => true,
    ]);
    $courseWithoutMaximum = ModeleNode::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'type_node' => 'MATIERE',
        'libelle' => 'Cours sans maximum',
        'ordre' => 3,
        'afficher_ligne' => true,
        'calculer_sous_total' => false,
        'obligatoire' => false,
    ]);

    foreach (range(4, 12) as $order) {
        ModeleNode::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'type_node' => 'MATIERE',
            'libelle' => "Cours {$order}",
            'ordre' => $order,
            'afficher_ligne' => true,
            'calculer_sous_total' => false,
            'obligatoire' => true,
        ]);
    }

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->withToken($token)
        ->getJson("/api/v1/classes/{$scope['class']->id}/cours?per_page=100&page=1")
        ->assertOk()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('data.0.id', $earlierCourse->id)
        ->assertJsonPath('data.0.attribuable', true)
        ->assertJsonPath(
            'data.0.attribution_active.enseignant.id',
            $assignedTeacher->id,
        )
        ->assertJsonPath('data.1.id', $scope['node']->id)
        ->assertJsonPath('data.2.id', $courseWithoutMaximum->id)
        ->assertJsonPath('data.2.obligatoire', false)
        ->assertJsonPath('data.2.attribuable', false)
        ->assertJsonPath('data.2.attribution_active', null)
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    $queryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(16);

    $titular = createClassTitularCandidate($scope, 'COURSE-LIST');
    $scope['class']->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'class-course-list-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($titularToken)
        ->getJson("/api/v1/classes/{$scope['class']->id}/cours?per_page=1&page=2")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

test('class course listing fails closed across context role and configuration boundaries', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'COURSES-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'COURSES-B',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);
    $uri = "/api/v1/classes/{$scope['class']->id}/cours";
    $assignedTeacher = createCourseAssignmentTeacher($scope, 'READ-ONLY');
    $teacherCourse = ModeleNode::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'type_node' => 'MATIERE',
        'libelle' => 'Cours enseignant affecté',
        'ordre' => 2,
        'afficher_ligne' => true,
        'calculer_sous_total' => false,
        'obligatoire' => true,
    ]);
    AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $teacherCourse->id,
        'enseignant_id' => $assignedTeacher->id,
        'actif' => true,
    ]);
    $teacherToken = $assignedTeacher->createToken(
        'assigned-teacher-course-list',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($teacherToken)
        ->getJson($uri)
        ->assertForbidden();

    $genericTitular = createClassTitularCandidate($scope, 'GENERIC');
    $genericTitularToken = $genericTitular->createToken(
        'generic-titular-course-list',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($genericTitularToken)
        ->getJson($uri)
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson("/api/v1/classes/{$foreignScope['class']->id}/cours")
        ->assertNotFound();

    $this->withToken($token)
        ->getJson("{$uri}?tenant_id={$scope['tenant']->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_id']);

    $classWithoutModel = Classe::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'cycle_id' => $scope['class']->cycle_id,
        'niveau_id' => $scope['class']->niveau_id,
        'system_template_id' => $scope['class']->system_template_id,
        'nom' => 'Classe sans cours',
        'active' => true,
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/classes/{$classWithoutModel->id}/cours")
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The class has no available course configuration.',
        );

    $scope['model']->update(['actif' => false]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The class has no available course configuration.',
        );

    $scope['class']->update(['active' => false]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk();

    $scope['class']->update(['active' => true]);
    $scope['model']->update(['actif' => true]);
    $secondTeacher = createCourseAssignmentTeacher($scope, 'DUPLICATE');
    AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $secondTeacher->id,
        'actif' => true,
    ]);

    $this->withToken($token)
        ->getJson($uri)
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The class contains conflicting active course assignments.',
        );
});

test('teacher policy centralizes current assignment and slot authorization', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'TEACHER-POLICY-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'TEACHER-POLICY-B',
        $scope['tenant'],
    );
    $teacher = createCourseAssignmentTeacher($scope, 'POLICY');
    $otherTeacher = createCourseAssignmentTeacher(
        $scope,
        'POLICY-OTHER',
    );
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->where('periode_id', $scope['period']->id)
        ->firstOrFail();
    $foreignSlot = ModeleMaximum::query()
        ->where('node_id', $foreignScope['node']->id)
        ->firstOrFail();
    $establishmentContext = app(EstablishmentContext::class);
    $establishmentContext->set($scope['establishment']);

    try {
        expect(Gate::getPolicyFor(AttributionCours::class))
            ->toBeInstanceOf(AttributionCoursPolicy::class)
            ->and(Gate::forUser($teacher)->allows(
                'viewAny',
                AttributionCours::class,
            ))->toBeTrue()
            ->and(Gate::forUser($teacher)->allows(
                'viewSlots',
                $assignment,
            ))->toBeTrue()
            ->and(Gate::forUser($teacher)->allows(
                'viewGradeSheet',
                [$assignment, $slot],
            ))->toBeTrue()
            ->and(Gate::forUser($teacher)->allows(
                'upsertNote',
                [$assignment, $slot],
            ))->toBeTrue()
            ->and(Gate::forUser($teacher)->allows(
                'importNotes',
                [$assignment, $slot],
            ))->toBeTrue()
            ->and(Gate::forUser($otherTeacher)->allows(
                'viewSlots',
                $assignment,
            ))->toBeFalse()
            ->and(Gate::forUser($teacher)->allows(
                'viewGradeSheet',
                [$assignment, $foreignSlot],
            ))->toBeFalse()
            ->and(Gate::forUser($scope['user'])->allows(
                'viewAny',
                AttributionCours::class,
            ))->toBeFalse();

        $assignment->update(['date_debut' => '2026-10-16']);

        expect(Gate::forUser($teacher)->allows(
            'viewSlots',
            $assignment->refresh(),
        ))->toBeFalse();

        $assignment->update(['date_debut' => null]);
        $teacher->update(['actif' => false]);

        expect(Gate::forUser($teacher->refresh())->allows(
            'upsertNote',
            [$assignment->refresh(), $slot],
        ))->toBeFalse();
    } finally {
        $establishmentContext->clear();
    }
});

test('an assigned teacher lists only their current course assignments without n plus one queries', function () {
    $this->travelTo(CarbonImmutable::parse(
        '2026-10-14 22:30:00',
        'UTC',
    ));

    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $scope['class']->update(['code' => '3E-A']);
    $teacher = createCourseAssignmentTeacher($scope, 'WORKSPACE');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $firstAssignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'date_debut' => '2026-10-15',
        'date_fin' => '2026-10-15',
        'actif' => true,
    ]);

    foreach (range(2, 12) as $order) {
        $course = ModeleNode::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'type_node' => 'MATIERE',
            'libelle' => "Cours enseignant {$order}",
            'ordre' => $order,
            'afficher_ligne' => true,
            'calculer_sous_total' => false,
            'obligatoire' => true,
        ]);
        AttributionCours::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'classe_id' => $scope['class']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'node_id' => $course->id,
            'enseignant_id' => $teacher->id,
            'actif' => true,
        ]);
    }

    $token = $teacher->createToken(
        'teacher-workspace',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->withToken($token)
        ->getJson('/api/v1/me/attributions?per_page=100&page=1')
        ->assertOk()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('data.0.id', $firstAssignment->id)
        ->assertJsonPath('data.0.classe.id', $scope['class']->id)
        ->assertJsonPath('data.0.classe.code', '3E-A')
        ->assertJsonPath(
            'data.0.annee_scolaire.id',
            $scope['year']->id,
        )
        ->assertJsonPath('data.0.cours.id', $scope['node']->id)
        ->assertJsonPath('data.0.cours.ordre', 1)
        ->assertJsonPath('data.0.date_debut', '2026-10-15')
        ->assertJsonPath('data.0.date_fin', '2026-10-15')
        ->assertJsonPath('data.0.actif', true)
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id])
        ->assertJsonMissing(['enseignant_id' => $teacher->id])
        ->assertJsonMissing([
            'etablissement_id' => $scope['establishment']->id,
        ])
        ->assertJsonPath('meta.total', 12);

    $queryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(16);

    $paginatedResponse = $this->withToken($token)
        ->getJson('/api/v1/me/attributions?per_page=1&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 12);

    expect($paginatedResponse->json('links.first'))
        ->toContain('per_page=1');
});

test('the teacher workspace fails closed across role context and temporal boundaries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'WORKSPACE-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'WORKSPACE-B',
        $scope['tenant'],
    );
    $teacher = createCourseAssignmentTeacher($scope, 'BOUNDARIES');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'date_debut' => '2026-10-15',
        'date_fin' => '2026-10-15',
        'actif' => true,
    ]);
    AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $foreignScope['class']->id,
        'modele_bulletin_id' => $foreignScope['model']->id,
        'node_id' => $foreignScope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $token = $teacher->createToken(
        'teacher-workspace-boundaries',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = '/api/v1/me/attributions';

    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assignment->id);

    $assignment->update([
        'date_debut' => '2026-10-16',
        'date_fin' => null,
    ]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $assignment->update([
        'date_debut' => null,
        'date_fin' => '2026-10-14',
    ]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $assignment->update([
        'date_debut' => null,
        'date_fin' => null,
        'actif' => false,
    ]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $assignment->update(['actif' => true]);
    $scope['class']->update(['active' => false]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $scope['class']->update(['active' => true]);
    $scope['year']->update(['cloturee' => true]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $scope['year']->update([
        'active' => false,
        'cloturee' => false,
    ]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $scope['year']->update(['active' => true]);
    $scope['model']->update(['actif' => false]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $scope['model']->update(['actif' => true]);
    $scope['node']->update(['type_node' => 'RUBRIQUE']);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $scope['node']->update(['type_node' => 'MATIERE']);
    $scope['year']->update([
        'date_debut' => '2026-10-15',
        'date_fin' => '2026-10-15',
    ]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $scope['year']->update([
        'date_debut' => '2026-10-16',
        'date_fin' => '2027-07-31',
    ]);
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $scope['year']->update([
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
    ]);
    $this->withToken($token)
        ->getJson("{$uri}?unexpected=1")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($token)
        ->getJson("{$uri}?per_page=101")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
    $this->withToken($token)
        ->getJson("{$uri}?page=0")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page']);

    $unassignedTeacher = createCourseAssignmentTeacher(
        $scope,
        'UNASSIGNED',
    );
    $unassignedToken = $unassignedTeacher->createToken(
        'unassigned-teacher-workspace',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($unassignedToken)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $nonTeacher = createCourseAssignmentTeacher(
        $scope,
        'NO-ROLE',
        false,
    );
    $nonTeacherToken = $nonTeacher->createToken(
        'non-teacher-workspace',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($nonTeacherToken)
        ->getJson($uri)
        ->assertForbidden();

    $managementToken = academicMutationToken($scope);
    $this->app['auth']->forgetGuards();

    $this->withToken($managementToken)
        ->getJson($uri)
        ->assertForbidden();

    $teacher->update(['actif' => false]);
    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson($uri)
        ->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->getJson($uri)->assertUnauthorized();
});

test('the teacher workspace refuses conflicting current assignments for one course', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $teacher = createCourseAssignmentTeacher($scope, 'CONFLICT-A');
    $otherTeacher = createCourseAssignmentTeacher($scope, 'CONFLICT-B');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);

    foreach ([$teacher, $otherTeacher] as $assignedTeacher) {
        AttributionCours::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'classe_id' => $scope['class']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'node_id' => $scope['node']->id,
            'enseignant_id' => $assignedTeacher->id,
            'actif' => true,
        ]);
    }

    $token = $teacher->createToken(
        'conflicting-teacher-workspace',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/me/attributions')
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'An active course assignment is inconsistent.',
        );
});

test('an assigned teacher lists ordered entry slots with their lock state without n plus one queries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $teacher = createCourseAssignmentTeacher($scope, 'SLOTS');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $firstSlot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->where('periode_id', $scope['period']->id)
        ->firstOrFail();

    VerrouillagePeriode::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'periode_id' => $scope['period']->id,
        'verrouille' => true,
        'verrouille_par' => $scope['user']->id,
        'date_verrouillage' => now(),
    ]);

    foreach (range(2, 12) as $order) {
        $period = ModelePeriode::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'code' => "SLOT_{$order}",
            'nom' => "CrÃ©neau {$order}",
            'type_periode' => 'PERIODE',
            'ordre' => $order,
            'saisissable' => true,
            'calculable' => false,
            'actif' => true,
        ]);
        ModeleMaximum::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'node_id' => $scope['node']->id,
            'periode_id' => $period->id,
            'maxima' => $order + 10,
            'coefficient' => 1.5,
        ]);
    }

    foreach ([
        ['code' => 'INACTIVE', 'actif' => false, 'saisissable' => true, 'maxima' => 20],
        ['code' => 'READ_ONLY', 'actif' => true, 'saisissable' => false, 'maxima' => 20],
        ['code' => 'ZERO', 'actif' => true, 'saisissable' => true, 'maxima' => 0],
    ] as $index => $excludedSlot) {
        $period = ModelePeriode::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'code' => $excludedSlot['code'],
            'nom' => $excludedSlot['code'],
            'type_periode' => 'PERIODE',
            'ordre' => 20 + $index,
            'saisissable' => $excludedSlot['saisissable'],
            'calculable' => false,
            'actif' => $excludedSlot['actif'],
        ]);
        ModeleMaximum::query()->create([
            'tenant_id' => $scope['tenant']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'node_id' => $scope['node']->id,
            'periode_id' => $period->id,
            'maxima' => $excludedSlot['maxima'],
            'coefficient' => 1,
        ]);
    }

    $token = $teacher->createToken(
        'teacher-entry-slots',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux";
    $auditCount = DB::table('audit_logs')->count();

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->withToken($token)
        ->getJson("{$uri}?per_page=100&page=1")
        ->assertOk()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('data.0.id', $firstSlot->id)
        ->assertJsonPath('data.0.attribution_id', $assignment->id)
        ->assertJsonPath('data.0.periode.id', $scope['period']->id)
        ->assertJsonPath('data.0.periode.ordre', 1)
        ->assertJsonPath('data.0.maximum', '20.00')
        ->assertJsonPath('data.0.coefficient', '1.00')
        ->assertJsonPath('data.0.verrouille', true)
        ->assertJsonPath('data.0.saisie_autorisee', false)
        ->assertJsonPath('data.1.periode.ordre', 2)
        ->assertJsonPath('data.1.verrouille', false)
        ->assertJsonPath('data.1.saisie_autorisee', true)
        ->assertJsonPath('meta.total', 12)
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id])
        ->assertJsonMissing([
            'modele_bulletin_id' => $scope['model']->id,
        ])
        ->assertJsonMissing(['node_id' => $scope['node']->id]);

    $queryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(18)
        ->and(DB::table('audit_logs')->count())->toBe($auditCount);

    $paginatedResponse = $this->withToken($token)
        ->getJson("{$uri}?per_page=1&page=2")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 12);

    expect($paginatedResponse->json('links.first'))
        ->toContain('per_page=1');
});

test('entry slot listing fails closed outside the current teachers assignment', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'SLOTS-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'SLOTS-B',
        $scope['tenant'],
    );
    $teacher = createCourseAssignmentTeacher($scope, 'SLOT-BOUNDARY');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $foreignAssignment = AttributionCours::query()
        ->where('classe_id', $foreignScope['class']->id)
        ->firstOrFail();
    $token = $teacher->createToken(
        'teacher-entry-slot-boundaries',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux";

    $this->withToken($token)->getJson($uri)->assertOk();
    $this->withToken($token)
        ->getJson("/api/v1/me/attributions/{$foreignAssignment->id}/creneaux")
        ->assertNotFound();
    $this->withToken($token)
        ->getJson('/api/v1/me/attributions/not-an-id/creneaux')
        ->assertNotFound();
    $this->withToken($token)
        ->getJson("{$uri}?unexpected=1")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($token)
        ->getJson("{$uri}?per_page=101")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
    $this->withToken($token)
        ->getJson("{$uri}?page=0")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page']);

    $assignment->update(['date_debut' => '2026-10-16']);
    $this->withToken($token)->getJson($uri)->assertNotFound();

    $assignment->update(['date_debut' => null]);
    $scope['class']->update(['active' => false]);
    $this->withToken($token)->getJson($uri)->assertNotFound();

    $managementToken = academicMutationToken($scope);
    $this->app['auth']->forgetGuards();
    $this->withToken($managementToken)->getJson($uri)->assertForbidden();

    $teacher->update(['actif' => false]);
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson($uri)->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->getJson($uri)->assertUnauthorized();
});

test('entry slot listing reports assignment and slot configuration conflicts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'SLOT-CONFLICT-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'SLOT-CONFLICT-B',
        $scope['tenant'],
    );
    $teacher = createCourseAssignmentTeacher($scope, 'SLOT-CONFLICT-A');
    $otherTeacher = createCourseAssignmentTeacher(
        $scope,
        'SLOT-CONFLICT-B',
    );
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $conflictingAssignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $otherTeacher->id,
        'actif' => true,
    ]);
    $token = $teacher->createToken(
        'teacher-entry-slot-conflicts',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux";

    $this->withToken($token)
        ->getJson($uri)
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'An active course assignment is inconsistent.',
        );

    $conflictingAssignment->update(['actif' => false]);
    ModeleMaximum::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $foreignScope['period']->id,
        'maxima' => 20,
        'coefficient' => 1,
    ]);

    $this->withToken($token)
        ->getJson($uri)
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The course assignment contains inconsistent entry slots.',
        );
});

test('an assigned teacher reads a sparse locked grade sheet without n plus one queries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $scope['student']->update(['nom' => 'Zulu']);
    $teacher = createCourseAssignmentTeacher($scope, 'GRADE-SHEET');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->where('periode_id', $scope['period']->id)
        ->firstOrFail();
    $firstOtherBulletin = null;

    foreach (range(1, 11) as $index) {
        $student = Eleve::withoutGlobalScopes()->create([
            'tenant_id' => $scope['tenant']->id,
            'etablissement_id' => $scope['establishment']->id,
            'num_permanent' => "GRADE-{$index}",
            'nom' => sprintf('Alpha %02d', $index),
            'actif' => true,
        ]);
        $enrollment = Inscription::withoutGlobalScopes()->create([
            'tenant_id' => $scope['tenant']->id,
            'eleve_id' => $student->id,
            'classe_id' => $scope['class']->id,
            'annee_scolaire_id' => $scope['year']->id,
            'numero_inscription' => "INS-GRADE-{$index}",
            'statut' => 'ACTIF',
        ]);
        $reportCard = Bulletin::withoutGlobalScopes()->create([
            'tenant_id' => $scope['tenant']->id,
            'inscription_id' => $enrollment->id,
            'classe_id' => $scope['class']->id,
            'modele_bulletin_id' => $scope['model']->id,
            'decision_statut' => 'EN_COURS',
            'verrouille' => false,
        ]);
        $firstOtherBulletin ??= $reportCard;
    }

    Note::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 14.5,
        'saisie_par' => $teacher->id,
        'date_saisie' => '2026-10-15 08:30:00',
        'commentaire' => 'Bon travail',
    ]);
    $otherPeriod = ModelePeriode::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'code' => 'OTHER_GRADE_SLOT',
        'nom' => 'Autre creneau',
        'type_periode' => 'PERIODE',
        'ordre' => 2,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);
    Note::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $firstOtherBulletin->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $otherPeriod->id,
        'valeur' => 18,
        'saisie_par' => $teacher->id,
        'date_saisie' => now(),
    ]);
    VerrouillagePeriode::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'periode_id' => $scope['period']->id,
        'verrouille' => true,
        'verrouille_par' => $scope['user']->id,
        'date_verrouillage' => now(),
    ]);

    $token = $teacher->createToken(
        'teacher-grade-sheet',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/feuille-notes";
    $auditCount = DB::table('audit_logs')->count();

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->withToken($token)
        ->getJson("{$uri}?per_page=100&page=1")
        ->assertOk()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('data.0.inscription.numero_inscription', 'INS-GRADE-1')
        ->assertJsonPath('data.0.inscription.eleve.nom', 'Alpha 01')
        ->assertJsonPath('data.0.note', null)
        ->assertJsonPath('data.11.inscription.id', $scope['enrollment']->id)
        ->assertJsonPath('data.11.inscription.eleve.nom', 'Zulu')
        ->assertJsonPath('data.11.note.statut_resultat', 'SAISIE')
        ->assertJsonPath('data.11.note.valeur', '14.50')
        ->assertJsonPath('data.11.note.commentaire', 'Bon travail')
        ->assertJsonPath(
            'data.11.note.date_saisie',
            '2026-10-15T08:30:00.000000Z',
        )
        ->assertJsonPath('creneau.id', $slot->id)
        ->assertJsonPath('creneau.attribution_id', $assignment->id)
        ->assertJsonPath('creneau.maximum', '20.00')
        ->assertJsonPath('creneau.verrouille', true)
        ->assertJsonPath('creneau.saisie_autorisee', false)
        ->assertJsonPath('meta.total', 12)
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id])
        ->assertJsonMissing(['bulletin_id' => $scope['bulletin']->id])
        ->assertJsonMissing(['node_id' => $scope['node']->id])
        ->assertJsonMissing(['periode_id' => $scope['period']->id]);

    $queryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(24)
        ->and(DB::table('audit_logs')->count())->toBe($auditCount);

    $paginatedResponse = $this->withToken($token)
        ->getJson("{$uri}?per_page=1&page=2")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 12)
        ->assertJsonPath('creneau.id', $slot->id);

    expect($paginatedResponse->json('links.first'))
        ->toContain('per_page=1');

    Inscription::query()
        ->where('classe_id', $scope['class']->id)
        ->update(['statut' => 'ABANDON']);

    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('creneau.id', $slot->id);

    $this->assertDatabaseHas('notes', [
        'bulletin_id' => $scope['bulletin']->id,
        'valeur' => 14.5,
    ]);
});

test('grade sheet resolution fails closed across assignment slot and role boundaries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'SHEET-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'SHEET-B',
        $scope['tenant'],
    );
    $teacher = createCourseAssignmentTeacher($scope, 'SHEET-BOUNDARY');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->firstOrFail();
    $foreignSlot = ModeleMaximum::query()
        ->where('node_id', $foreignScope['node']->id)
        ->firstOrFail();
    $foreignAssignment = AttributionCours::query()
        ->where('classe_id', $foreignScope['class']->id)
        ->firstOrFail();
    $token = $teacher->createToken(
        'teacher-grade-sheet-boundaries',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/feuille-notes";

    $this->withToken($token)->getJson($uri)->assertOk();
    $this->withToken($token)
        ->getJson("/api/v1/me/attributions/{$assignment->id}/creneaux/{$foreignSlot->id}/feuille-notes")
        ->assertNotFound();
    $this->withToken($token)
        ->getJson("/api/v1/me/attributions/{$foreignAssignment->id}/creneaux/{$foreignSlot->id}/feuille-notes")
        ->assertNotFound();
    $this->withToken($token)
        ->getJson('/api/v1/me/attributions/not-an-id/creneaux/1/feuille-notes')
        ->assertNotFound();
    $this->withToken($token)
        ->getJson("/api/v1/me/attributions/{$assignment->id}/creneaux/not-an-id/feuille-notes")
        ->assertNotFound();
    $this->withToken($token)
        ->getJson("{$uri}?unexpected=1")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($token)
        ->getJson("{$uri}?per_page=101")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);

    $assignment->update(['date_debut' => '2026-10-16']);
    $this->withToken($token)->getJson($uri)->assertNotFound();

    $assignment->update(['date_debut' => null]);
    $slot->update(['maxima' => 0]);
    $this->withToken($token)->getJson($uri)->assertNotFound();

    $managementToken = academicMutationToken($scope);
    $this->app['auth']->forgetGuards();
    $this->withToken($managementToken)->getJson($uri)->assertForbidden();

    $teacher->update(['actif' => false]);
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson($uri)->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->getJson($uri)->assertUnauthorized();
});

test('grade sheet listing reports inconsistent students report cards and values', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $teacher = createCourseAssignmentTeacher($scope, 'SHEET-CONFLICT');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->firstOrFail();
    $student = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'SHEET-CONFLICT',
        'nom' => 'Eleve incoherent',
        'actif' => true,
    ]);
    $enrollment = Inscription::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'eleve_id' => $student->id,
        'classe_id' => $scope['class']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'statut' => 'ACTIF',
    ]);
    $token = $teacher->createToken(
        'teacher-grade-sheet-conflicts',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/feuille-notes";

    $this->withToken($token)
        ->getJson($uri)
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The grade sheet contains inconsistent active enrollments.',
        );

    $reportCard = Bulletin::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'inscription_id' => $enrollment->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'decision_statut' => 'EN_COURS',
        'verrouille' => false,
    ]);
    $student->update(['actif' => false]);

    $this->withToken($token)->getJson($uri)->assertConflict();

    $student->update(['actif' => true]);
    Note::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $reportCard->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 21,
        'saisie_par' => $teacher->id,
        'date_saisie' => now(),
    ]);

    $this->withToken($token)
        ->getJson($uri)
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The grade sheet contains inconsistent active enrollments.',
        );
});

test('management atomically replaces a course teacher and replays the same assignment', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $teacher = createCourseAssignmentTeacher($scope, 'PRIMARY');
    $scope['year']->update([
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-12-31',
    ]);
    $uri = "/api/v1/classes/{$scope['class']->id}/cours/{$scope['node']->id}/attribution";
    $payload = [
        'enseignant_id' => $teacher->id,
        'date_debut' => '2026-01-15',
        'date_fin' => '2026-12-15',
    ];

    Event::fake([CourseTeacherAssigned::class]);

    $this->withToken($token)
        ->putJson($uri, $payload)
        ->assertOk()
        ->assertJsonPath('data.classe_id', $scope['class']->id)
        ->assertJsonPath('data.cours.id', $scope['node']->id)
        ->assertJsonPath('data.enseignant.id', $teacher->id)
        ->assertJsonPath('data.date_debut', '2026-01-15')
        ->assertJsonPath('data.date_fin', '2026-12-15')
        ->assertJsonPath('data.actif', true)
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    $this->assertDatabaseHas('attributions_cours', [
        'classe_id' => $scope['class']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $scope['user']->id,
        'actif' => false,
    ]);
    $this->assertDatabaseHas('attributions_cours', [
        'classe_id' => $scope['class']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    expect(
        AttributionCours::withoutGlobalScopes()
            ->where('classe_id', $scope['class']->id)
            ->where('node_id', $scope['node']->id)
            ->where('actif', true)
            ->count(),
    )->toBe(1);
    expect(
        DB::table('audit_logs')
            ->where('action', 'ATTRIBUTION_COURS_ENREGISTREE')
            ->where('utilisateur_id', $scope['user']->id)
            ->count(),
    )->toBe(1);
    Event::assertDispatched(
        CourseTeacherAssigned::class,
        fn (CourseTeacherAssigned $event): bool => $event->tenantId === $scope['tenant']->id
            && $event->establishmentId === $scope['establishment']->id
            && $event->schoolYearId === $scope['year']->id
            && $event->classId === $scope['class']->id
            && $event->courseNodeId === $scope['node']->id
            && $event->teacherId === $teacher->id
            && $event->previousTeacherId === $scope['user']->id
            && $event->actorId === $scope['user']->id,
    );

    $this->withToken($token)
        ->putJson($uri, $payload)
        ->assertOk()
        ->assertJsonPath('data.enseignant.id', $teacher->id);

    expect(
        DB::table('audit_logs')
            ->where('action', 'ATTRIBUTION_COURS_ENREGISTREE')
            ->count(),
    )->toBe(1);
    Event::assertDispatchedTimes(CourseTeacherAssigned::class, 1);

    $assignment = AttributionCours::query()
        ->where('enseignant_id', $teacher->id)
        ->where('actif', true)
        ->firstOrFail();
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->where('periode_id', $scope['period']->id)
        ->firstOrFail();
    $noteUri = "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/inscriptions/{$scope['enrollment']->id}/note";

    $this->withToken($token)
        ->putJson($noteUri, ['valeur' => 16])
        ->assertForbidden();

    $teacherToken = $teacher->createToken(
        'assigned-teacher',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($teacherToken)
        ->putJson($noteUri, ['valeur' => 16])
        ->assertOk()
        ->assertJsonPath('data.note.valeur', '16.00');
});

test('course assignment fails closed across class course teacher and role boundaries', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'ASSIGN-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'ASSIGN-B',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);
    $teacher = createCourseAssignmentTeacher($scope, 'VALID');
    $foreignTeacher = createCourseAssignmentTeacher($foreignScope, 'FOREIGN');
    $unqualifiedUser = createCourseAssignmentTeacher(
        $scope,
        'NO-ROLE',
        withTeacherRole: false,
    );
    $uri = "/api/v1/classes/{$scope['class']->id}/cours/{$scope['node']->id}/attribution";

    foreach ([$foreignTeacher, $unqualifiedUser] as $invalidTeacher) {
        $this->withToken($token)
            ->putJson($uri, ['enseignant_id' => $invalidTeacher->id])
            ->assertNotFound();
    }

    $teacher->update(['actif' => false]);
    $this->withToken($token)
        ->putJson($uri, ['enseignant_id' => $teacher->id])
        ->assertNotFound();

    $this->withToken($token)
        ->putJson(
            "/api/v1/classes/{$scope['class']->id}/cours/{$foreignScope['node']->id}/attribution",
            ['enseignant_id' => $foreignTeacher->id],
        )
        ->assertNotFound();

    $titular = createCourseAssignmentTeacher(
        $scope,
        'TITULAR',
        withTeacherRole: false,
    );
    $titularRole = Role::query()->firstOrCreate(
        ['tenant_id' => null, 'code' => 'TITULAIRE'],
        ['nom' => 'Titulaire', 'system_role' => true],
    );
    $titular->roles()->attach($titularRole->id);
    $scope['class']->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'course-assignment-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($titularToken)
        ->putJson($uri, ['enseignant_id' => $foreignTeacher->id])
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->putJson($uri, [
            'enseignant_id' => $foreignTeacher->id,
            'tenant_id' => $scope['tenant']->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_id']);

    expect(
        DB::table('audit_logs')
            ->where('action', 'ATTRIBUTION_COURS_ENREGISTREE')
            ->count(),
    )->toBe(0);
});

test('course assignment validates dates and rejects closed or incomplete contexts', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $teacher = createCourseAssignmentTeacher($scope, 'CONFLICT');
    $uri = "/api/v1/classes/{$scope['class']->id}/cours/{$scope['node']->id}/attribution";

    $this->withToken($token)
        ->putJson($uri, [
            'enseignant_id' => $teacher->id,
            'date_debut' => '2026-10-10',
            'date_fin' => '2026-10-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_fin']);

    $this->withToken($token)
        ->putJson($uri, [
            'enseignant_id' => $teacher->id,
            'date_debut' => '2026-08-31',
        ])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Assignment dates must fall within the school year.',
        );

    $scope['class']->update(['active' => false]);
    $this->withToken($token)
        ->putJson($uri, ['enseignant_id' => $teacher->id])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Teachers cannot be assigned to an archived class.',
        );

    $scope['class']->update(['active' => true]);
    $scope['year']->update(['cloturee' => true]);
    $this->withToken($token)
        ->putJson($uri, ['enseignant_id' => $teacher->id])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Teachers cannot be assigned in a closed school year.',
        );

    $scope['year']->update(['cloturee' => false]);
    $unconfiguredCourse = ModeleNode::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'type_node' => 'MATIERE',
        'libelle' => 'Cours sans maximum',
        'ordre' => 2,
        'afficher_ligne' => true,
        'calculer_sous_total' => false,
        'obligatoire' => true,
    ]);

    $this->withToken($token)
        ->putJson(
            "/api/v1/classes/{$scope['class']->id}/cours/{$unconfiguredCourse->id}/attribution",
            ['enseignant_id' => $teacher->id],
        )
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'The course has no configured grading maximum.',
        );

    expect(CourseTeacherAssigned::class)
        ->toImplement(ShouldDispatchAfterCommit::class);
});

test('management atomically replaces a class titular and replays the same assignment', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $scope['year']->update([
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-12-31',
    ]);
    $token = academicMutationToken($scope);
    $firstTitular = createClassTitularCandidate($scope, 'PRIMARY');
    $secondTitular = createClassTitularCandidate($scope, 'REPLACEMENT');
    $uri = "/api/v1/classes/{$scope['class']->id}/titulariat";
    $firstPayload = [
        'titulaire_id' => $firstTitular->id,
        'date_debut' => '2026-01-15',
        'date_fin' => '2026-12-15',
    ];

    Event::fake([ClassTitularAssigned::class]);

    $this->withToken($token)
        ->putJson($uri, $firstPayload)
        ->assertOk()
        ->assertJsonPath('data.classe_id', $scope['class']->id)
        ->assertJsonPath('data.titulaire.id', $firstTitular->id)
        ->assertJsonPath('data.date_debut', '2026-01-15')
        ->assertJsonPath('data.date_fin', '2026-12-15')
        ->assertJsonPath('data.actif', true)
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id]);

    $firstAssignment = TitulaireClasse::withoutGlobalScopes()
        ->where('classe_id', $scope['class']->id)
        ->where('utilisateur_id', $firstTitular->id)
        ->sole();

    expect($scope['class']->refresh()->titulaire_id)->toBe($firstTitular->id)
        ->and($firstAssignment->actif)->toBeTrue();
    expect(
        DB::table('audit_logs')
            ->where('action', 'TITULARIAT_CLASSE_ENREGISTRE')
            ->count(),
    )->toBe(1);
    Event::assertDispatched(
        ClassTitularAssigned::class,
        fn (ClassTitularAssigned $event): bool => $event->tenantId === $scope['tenant']->id
            && $event->establishmentId === $scope['establishment']->id
            && $event->schoolYearId === $scope['year']->id
            && $event->classId === $scope['class']->id
            && $event->assignmentId === $firstAssignment->id
            && $event->titularId === $firstTitular->id
            && $event->previousTitularId === null
            && $event->actorId === $scope['user']->id,
    );

    $this->withToken($token)
        ->putJson($uri, $firstPayload)
        ->assertOk()
        ->assertJsonPath('data.id', $firstAssignment->id);

    expect(
        TitulaireClasse::withoutGlobalScopes()
            ->where('classe_id', $scope['class']->id)
            ->count(),
    )->toBe(1);
    expect(
        DB::table('audit_logs')
            ->where('action', 'TITULARIAT_CLASSE_ENREGISTRE')
            ->count(),
    )->toBe(1);
    Event::assertDispatchedTimes(ClassTitularAssigned::class, 1);

    $this->withToken($token)
        ->putJson($uri, [
            'titulaire_id' => $secondTitular->id,
            'date_debut' => '2026-02-01',
        ])
        ->assertOk()
        ->assertJsonPath('data.titulaire.id', $secondTitular->id)
        ->assertJsonPath('data.date_debut', '2026-02-01')
        ->assertJsonPath('data.date_fin', null);

    $this->assertDatabaseHas('titulaires_classes', [
        'id' => $firstAssignment->id,
        'utilisateur_id' => $firstTitular->id,
        'actif' => false,
    ]);
    expect($firstAssignment->refresh()->date_fin?->toDateString())
        ->toBe('2026-02-01');
    $this->assertDatabaseHas('titulaires_classes', [
        'classe_id' => $scope['class']->id,
        'utilisateur_id' => $secondTitular->id,
        'actif' => true,
    ]);
    expect(
        TitulaireClasse::withoutGlobalScopes()
            ->where('classe_id', $scope['class']->id)
            ->where('utilisateur_id', $secondTitular->id)
            ->sole()
            ->date_debut?->toDateString(),
    )->toBe('2026-02-01');
    expect($scope['class']->refresh()->titulaire_id)->toBe($secondTitular->id);
    expect(
        TitulaireClasse::withoutGlobalScopes()
            ->where('classe_id', $scope['class']->id)
            ->where('actif', true)
            ->count(),
    )->toBe(1);
    expect(
        DB::table('audit_logs')
            ->where('action', 'TITULARIAT_CLASSE_ENREGISTRE')
            ->count(),
    )->toBe(2);
    Event::assertDispatchedTimes(ClassTitularAssigned::class, 2);

    $attendancePayload = [
        'inscription_id' => $scope['enrollment']->id,
        'date_presence' => '2026-10-21',
        'statut' => 'PRESENT',
    ];
    $firstTitularToken = $firstTitular->createToken(
        'former-class-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $secondTitularToken = $secondTitular->createToken(
        'current-class-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($firstTitularToken)
        ->postJson("/api/v1/classes/{$scope['class']->id}/presences", $attendancePayload)
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken($secondTitularToken)
        ->postJson("/api/v1/classes/{$scope['class']->id}/presences", $attendancePayload)
        ->assertCreated();
});

test('class titular assignment fails closed across establishment role and actor boundaries', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'TITULAR-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'TITULAR-B',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);
    $validTitular = createClassTitularCandidate($scope, 'VALID');
    $foreignTitular = createClassTitularCandidate($foreignScope, 'FOREIGN');
    $unqualifiedUser = createClassTitularCandidate(
        $scope,
        'NO-ROLE',
        withTitularRole: false,
    );
    $uri = "/api/v1/classes/{$scope['class']->id}/titulariat";

    foreach ([$foreignTitular, $unqualifiedUser] as $invalidTitular) {
        $this->withToken($token)
            ->putJson($uri, ['titulaire_id' => $invalidTitular->id])
            ->assertNotFound();
    }

    $validTitular->update(['actif' => false]);
    $this->withToken($token)
        ->putJson($uri, ['titulaire_id' => $validTitular->id])
        ->assertNotFound();

    $this->withToken($token)
        ->putJson(
            "/api/v1/classes/{$foreignScope['class']->id}/titulariat",
            ['titulaire_id' => $foreignTitular->id],
        )
        ->assertNotFound();

    $actorTitular = createClassTitularCandidate($scope, 'ACTOR');
    $scope['class']->update(['titulaire_id' => $actorTitular->id]);
    $actorToken = $actorTitular->createToken(
        'class-titular-assignment-actor',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($actorToken)
        ->putJson($uri, ['titulaire_id' => $foreignTitular->id])
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->putJson($uri, [
            'titulaire_id' => $foreignTitular->id,
            'tenant_id' => $scope['tenant']->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant_id']);

    expect(
        DB::table('audit_logs')
            ->where('action', 'TITULARIAT_CLASSE_ENREGISTRE')
            ->count(),
    )->toBe(0);
    expect(
        TitulaireClasse::withoutGlobalScopes()
            ->where('classe_id', $scope['class']->id)
            ->count(),
    )->toBe(0);
});

test('class titular assignment validates state dates and blocks generic pointer mutation', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $token = academicMutationToken($scope);
    $titular = createClassTitularCandidate($scope, 'CONFLICT');
    $uri = "/api/v1/classes/{$scope['class']->id}/titulariat";

    $this->withToken($token)
        ->putJson($uri, [
            'titulaire_id' => $titular->id,
            'date_debut' => '2026-10-10',
            'date_fin' => '2026-10-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_fin']);

    $this->withToken($token)
        ->putJson($uri, [
            'titulaire_id' => $titular->id,
            'date_debut' => '2026-08-31',
        ])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Titular assignment dates must fall within the school year.',
        );

    $scope['class']->update(['active' => false]);
    $this->withToken($token)
        ->putJson($uri, ['titulaire_id' => $titular->id])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'A titular cannot be assigned to an archived class.',
        );

    $scope['class']->update(['active' => true]);
    $scope['year']->update(['cloturee' => true]);
    $this->withToken($token)
        ->putJson($uri, ['titulaire_id' => $titular->id])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'A titular cannot be assigned in a closed school year.',
        );

    $scope['year']->update(['cloturee' => false]);
    $this->withToken($token)
        ->patchJson("/api/v1/classes/{$scope['class']->id}", [
            'titulaire_id' => $titular->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['titulaire_id']);

    expect($scope['class']->refresh()->titulaire_id)->toBeNull();
    expect(
        DB::table('audit_logs')
            ->where('action', 'TITULARIAT_CLASSE_ENREGISTRE')
            ->count(),
    )->toBe(0);
    expect(ClassTitularAssigned::class)
        ->toImplement(ShouldDispatchAfterCommit::class);
});

test('an assigned teacher creates corrects and replays a note idempotently', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00:00'));

    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $teacher = createCourseAssignmentTeacher($scope, 'NOTE-UPSERT');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->where('periode_id', $scope['period']->id)
        ->firstOrFail();
    $token = $teacher->createToken(
        'teacher-note-upsert',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/inscriptions/{$scope['enrollment']->id}/note";

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 14.5,
            'lock_version' => 7,
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'NOTE_VERSION_CONFLICT')
        ->assertJsonPath(
            'detail',
            'A lock version cannot target a note that does not exist.',
        );
    $this->assertDatabaseCount('notes', 0);

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 14.5,
            'commentaire' => 'Bon travail',
        ])
        ->assertOk()
        ->assertJsonPath('data.inscription.id', $scope['enrollment']->id)
        ->assertJsonPath('data.inscription.eleve.id', $scope['student']->id)
        ->assertJsonPath('data.creneau_id', $slot->id)
        ->assertJsonPath('data.note.statut_resultat', 'SAISIE')
        ->assertJsonPath('data.note.lock_version', 1)
        ->assertJsonPath('data.note.valeur', '14.50')
        ->assertJsonPath('data.note.commentaire', 'Bon travail')
        ->assertJsonMissing(['tenant_id' => $scope['tenant']->id])
        ->assertJsonMissing(['bulletin_id' => $scope['bulletin']->id])
        ->assertJsonMissing(['node_id' => $scope['node']->id])
        ->assertJsonMissing(['periode_id' => $scope['period']->id])
        ->assertJsonMissing(['saisie_par' => $teacher->id]);

    $this->withToken($token)
        ->getJson(
            "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/feuille-notes",
        )
        ->assertOk()
        ->assertJsonPath('data.0.note.statut_resultat', 'SAISIE')
        ->assertJsonPath('data.0.note.lock_version', 1)
        ->assertJsonPath('data.0.note.valeur', '14.50')
        ->assertJsonPath('data.0.note.commentaire', 'Bon travail');

    $note = Note::query()->firstOrFail();
    $this->app['auth']->forgetGuards();
    $this->withToken(academicMutationToken($scope))
        ->getJson(
            "/api/v1/inscriptions/{$scope['enrollment']->id}/bulletin",
        )
        ->assertOk()
        ->assertJsonPath('data.slots.0.note.id', $note->id)
        ->assertJsonPath('data.slots.0.note.statut_resultat', 'SAISIE')
        ->assertJsonPath('data.slots.0.note.valeur', '14.50');
    $this->app['auth']->forgetGuards();

    $progress = SuiviEncodageNote::query()->firstOrFail();
    $initialNoteTimestamp = $note->updated_at?->toISOString();
    $initialEntryTimestamp = $note->date_saisie?->toISOString();
    $initialProgressTimestamp = $progress->updated_at?->toISOString();

    expect($note->saisie_par)->toBe($teacher->id)
        ->and($progress->nombre_eleves)->toBe(1)
        ->and($progress->nombre_notes_saisies)->toBe(1)
        ->and($progress->pourcentage_avancement)->toBe('100.00')
        ->and($progress->statut)->toBe('TERMINE')
        ->and($progress->enseignant_id)->toBe($teacher->id);
    $this->assertDatabaseCount('notes_historiques', 0);

    $this->travel(1)->minute();

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => '14.50',
            'commentaire' => 'Bon travail',
            'motif_correction' => 'Rejeu sans effet',
        ])
        ->assertOk()
        ->assertJsonPath('data.note.valeur', '14.50');

    $note->refresh();
    $progress->refresh();

    expect($note->updated_at?->toISOString())->toBe($initialNoteTimestamp)
        ->and($note->date_saisie?->toISOString())->toBe($initialEntryTimestamp)
        ->and($progress->updated_at?->toISOString())->toBe($initialProgressTimestamp);
    $this->assertDatabaseCount('notes_historiques', 0);

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 16,
            'motif_correction' => 'Version manquante',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'NOTE_VERSION_CONFLICT')
        ->assertJsonPath(
            'detail',
            'The current note lock version is required.',
        );

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 16,
            'motif_correction' => 'Version de type chaîne',
            'lock_version' => '1',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lock_version']);

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 16,
            'motif_correction' => 'Version invalide',
            'lock_version' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lock_version']);

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 16,
            'lock_version' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'detail',
            'A correction reason is required to modify an existing note.',
        );

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 16,
            'motif_correction' => 'Correction de la copie',
            'lock_version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.note.valeur', '16.00')
        ->assertJsonPath('data.note.lock_version', 2)
        ->assertJsonPath('data.note.commentaire', null);

    $this->assertDatabaseCount('notes', 1);
    $this->assertDatabaseCount('notes_historiques', 1);
    $this->assertDatabaseHas('notes_historiques', [
        'note_id' => $note->id,
        'ancien_statut_resultat' => 'SAISIE',
        'nouveau_statut_resultat' => 'SAISIE',
        'ancienne_valeur' => 14.5,
        'nouvelle_valeur' => 16,
        'modifie_par' => $teacher->id,
        'motif' => 'Correction de la copie',
    ]);

    $progressAfterCorrection = $progress->refresh()->updated_at?->toISOString();

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => '16.00',
            'motif_correction' => 'Correction de la copie',
            'lock_version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.note.lock_version', 2);

    expect($progress->refresh()->updated_at?->toISOString())
        ->toBe($progressAfterCorrection);
    $this->assertDatabaseCount('notes_historiques', 1);

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 15,
            'motif_correction' => 'Correction depuis une vue obsolète',
            'lock_version' => 1,
        ])
        ->assertConflict()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'NOTE_VERSION_CONFLICT')
        ->assertJsonPath(
            'detail',
            'The note was modified by another request.',
        );

    expect($note->refresh()->valeur)->toBe('16.00')
        ->and($note->lock_version)->toBe(2);
    $this->assertDatabaseCount('notes_historiques', 1);

    $this->withToken($token)
        ->putJson($uri, [
            'valeur' => 16,
            'commentaire' => 'Commentaire corrigé',
            'motif_correction' => 'Précision pédagogique',
            'lock_version' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.note.lock_version', 3)
        ->assertJsonPath(
            'data.note.commentaire',
            'Commentaire corrigé',
        );

    $this->assertDatabaseCount('notes_historiques', 2);
    $this->assertDatabaseHas('notes_historiques', [
        'note_id' => $note->id,
        'ancien_statut_resultat' => 'SAISIE',
        'nouveau_statut_resultat' => 'SAISIE',
        'ancienne_valeur' => 16,
        'nouvelle_valeur' => 16,
        'modifie_par' => $teacher->id,
        'motif' => 'Précision pédagogique',
    ]);
});

test('teacher note upsert accepts the exact minimum and configured maximum', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $teacher = createCourseAssignmentTeacher($scope, 'NOTE-LIMITS');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->where('periode_id', $scope['period']->id)
        ->firstOrFail();
    $secondStudent = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'NOTE-MAX',
        'nom' => 'Maximum',
        'actif' => true,
    ]);
    $secondEnrollment = Inscription::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'eleve_id' => $secondStudent->id,
        'classe_id' => $scope['class']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'statut' => 'ACTIF',
    ]);
    Bulletin::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'inscription_id' => $secondEnrollment->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'decision_statut' => 'EN_COURS',
        'verrouille' => false,
    ]);
    $token = $teacher->createToken(
        'teacher-note-limits',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $routePrefix = "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/inscriptions";

    $this->withToken($token)
        ->putJson(
            "{$routePrefix}/{$scope['enrollment']->id}/note",
            ['valeur' => 0],
        )
        ->assertOk()
        ->assertJsonPath('data.note.valeur', '0.00');
    $this->withToken($token)
        ->putJson(
            "{$routePrefix}/{$secondEnrollment->id}/note",
            ['valeur' => 20],
        )
        ->assertOk()
        ->assertJsonPath('data.note.valeur', '20.00');

    expect(Note::query()->orderBy('valeur')->pluck('valeur')->all())
        ->toBe(['0.00', '20.00']);
    $this->assertDatabaseHas('suivis_encodage_notes', [
        'classe_id' => $scope['class']->id,
        'nombre_eleves' => 2,
        'nombre_notes_saisies' => 2,
        'pourcentage_avancement' => 100,
        'statut' => 'TERMINE',
    ]);
});

test('teacher note service rejects a negative value without relying on the HTTP request', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $context = createTeacherNoteImportContext('NOTE-NEGATIVE-SERVICE');
    $scope = $context['scope'];

    expect(fn () => app(UpsertMyCourseAssignmentNoteService::class)->upsert(
        assignment: $context['assignment'],
        slot: $context['slot'],
        enrollmentId: $scope['enrollment']->id,
        teacher: $context['teacher'],
        status: NoteResultStatus::Saisie,
        value: '-0.01',
        comment: null,
        correctionReason: null,
        expectedVersion: null,
    ))->toThrow(
        EvaluationException::class,
        'A grade value cannot be negative.',
    );

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('notes_historiques', 0);
});

test('teacher records absent and exempt results with strict transitions', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00:00'));

    $context = createTeacherNoteImportContext('NOTE-STATUS');
    $scope = $context['scope'];
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$context['assignment']->id}/creneaux/{$context['slot']->id}/inscriptions/{$scope['enrollment']->id}/note";
    $gradeSheetUri = "/api/v1/me/attributions/{$context['assignment']->id}/creneaux/{$context['slot']->id}/feuille-notes";

    $this->withToken($context['token'])
        ->putJson($uri, [
            'statut_resultat' => 'ABSENTE',
            'valeur' => null,
            'commentaire' => 'Absence constatee',
        ])
        ->assertOk()
        ->assertJsonPath('data.note.statut_resultat', 'ABSENTE')
        ->assertJsonPath('data.note.lock_version', 1)
        ->assertJsonPath('data.note.valeur', null);

    $this->withToken($context['token'])
        ->getJson($gradeSheetUri)
        ->assertOk()
        ->assertJsonPath('data.0.note.statut_resultat', 'ABSENTE')
        ->assertJsonPath('data.0.note.lock_version', 1)
        ->assertJsonPath('data.0.note.valeur', null);

    $note = Note::query()->firstOrFail();
    $progress = SuiviEncodageNote::query()->firstOrFail();
    $noteTimestamp = $note->updated_at?->toISOString();
    $progressTimestamp = $progress->updated_at?->toISOString();

    expect($note->statut_resultat)->toBe(NoteResultStatus::Absente)
        ->and($note->valeur)->toBeNull()
        ->and($progress->nombre_notes_saisies)->toBe(1)
        ->and($progress->statut)->toBe('TERMINE');

    $this->travel(1)->minute();

    $this->withToken($context['token'])
        ->putJson($uri, [
            'statut_resultat' => 'ABSENTE',
            'valeur' => null,
            'commentaire' => 'Absence constatee',
            'motif_correction' => 'Replay',
        ])
        ->assertOk();

    expect($note->refresh()->updated_at?->toISOString())
        ->toBe($noteTimestamp)
        ->and($progress->refresh()->updated_at?->toISOString())
        ->toBe($progressTimestamp);
    $this->assertDatabaseCount('notes_historiques', 0);

    foreach ([
        [],
        ['statut_resultat' => 'SAISIE'],
        ['statut_resultat' => 'ABSENTE', 'valeur' => 0],
        ['statut_resultat' => 'DISPENSEE', 'valeur' => 12],
        ['statut_resultat' => 'ANNULEE'],
        ['statut_resultat' => 'INCONNUE'],
    ] as $invalidPayload) {
        $this->withToken($context['token'])
            ->putJson($uri, $invalidPayload)
            ->assertUnprocessable();
    }

    $this->withToken($context['token'])
        ->putJson($uri, [
            'statut_resultat' => 'DISPENSEE',
            'valeur' => null,
            'lock_version' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'detail',
            'A correction reason is required to modify an existing note.',
        );

    $this->withToken($context['token'])
        ->putJson($uri, [
            'statut_resultat' => 'DISPENSEE',
            'valeur' => null,
            'motif_correction' => 'Dispense confirmee',
            'lock_version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.note.statut_resultat', 'DISPENSEE')
        ->assertJsonPath('data.note.lock_version', 2)
        ->assertJsonPath('data.note.valeur', null);

    $this->assertDatabaseHas('notes_historiques', [
        'note_id' => $note->id,
        'ancien_statut_resultat' => 'ABSENTE',
        'nouveau_statut_resultat' => 'DISPENSEE',
        'ancienne_valeur' => null,
        'nouvelle_valeur' => null,
        'modifie_par' => $context['teacher']->id,
        'motif' => 'Dispense confirmee',
    ]);
    expect($progress->refresh()->updated_at?->toISOString())
        ->toBe($progressTimestamp);

    $this->withToken($context['token'])
        ->putJson($uri, [
            'statut_resultat' => 'SAISIE',
            'valeur' => 12,
            'motif_correction' => 'Copie retrouvee',
            'lock_version' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.note.statut_resultat', 'SAISIE')
        ->assertJsonPath('data.note.lock_version', 3)
        ->assertJsonPath('data.note.valeur', '12.00');

    $this->assertDatabaseCount('notes_historiques', 2);
    $this->assertDatabaseHas('notes_historiques', [
        'note_id' => $note->id,
        'ancien_statut_resultat' => 'DISPENSEE',
        'nouveau_statut_resultat' => 'SAISIE',
        'ancienne_valeur' => null,
        'nouvelle_valeur' => 12,
        'modifie_par' => $context['teacher']->id,
        'motif' => 'Copie retrouvee',
    ]);
    expect($progress->refresh()->updated_at?->toISOString())
        ->toBe($progressTimestamp);

    $note->refresh();
    $note->update([
        'statut_resultat' => NoteResultStatus::Annulee,
        'valeur' => null,
    ]);

    $this->withToken($context['token'])
        ->putJson($uri, [
            'statut_resultat' => 'SAISIE',
            'valeur' => 12,
            'motif_correction' => 'Tentative de reprise',
            'lock_version' => 3,
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'detail',
            'An annulled result cannot be modified by a teacher.',
        );

    expect($note->refresh()->statut_resultat)
        ->toBe(NoteResultStatus::Annulee);
    $this->assertDatabaseCount('notes_historiques', 2);
});

test('teacher note upsert fails closed on payload ownership and lock boundaries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'NOTE-A');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'NOTE-B',
        $scope['tenant'],
    );
    $teacher = createCourseAssignmentTeacher($scope, 'NOTE-BOUNDARY');
    AttributionCours::query()
        ->where('enseignant_id', $scope['user']->id)
        ->update(['actif' => false]);
    $assignment = AttributionCours::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $teacher->id,
        'actif' => true,
    ]);
    $slot = ModeleMaximum::query()
        ->where('node_id', $scope['node']->id)
        ->where('periode_id', $scope['period']->id)
        ->firstOrFail();
    $foreignSlot = ModeleMaximum::query()
        ->where('node_id', $foreignScope['node']->id)
        ->firstOrFail();
    $token = $teacher->createToken(
        'teacher-note-boundaries',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $otherTeacher = createCourseAssignmentTeacher(
        $scope,
        'NOTE-BOUNDARY-OTHER',
    );
    $otherTeacherToken = $otherTeacher->createToken(
        'other-teacher-note-boundaries',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $uri = "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/inscriptions/{$scope['enrollment']->id}/note";

    $this->withToken($otherTeacherToken)
        ->putJson($uri, ['valeur' => 10])
        ->assertNotFound();
    $this->assertDatabaseCount('notes', 0);
    $this->app['auth']->forgetGuards();

    foreach ([
        ['valeur' => -1],
        ['valeur' => 20.01],
        ['valeur' => '10.123'],
        ['valeur' => 10, 'bulletin_id' => $scope['bulletin']->id],
        ['valeur' => 10, 'tenant_id' => $scope['tenant']->id],
    ] as $invalidPayload) {
        $this->withToken($token)
            ->putJson($uri, $invalidPayload)
            ->assertUnprocessable();
    }

    $this->withToken($token)
        ->putJson("{$uri}?unexpected=1", ['valeur' => 10])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($token)
        ->putJson("{$uri}?valeur=10", ['valeur' => 10])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['valeur']);
    $this->withToken($token)
        ->putJson(
            "/api/v1/me/attributions/{$assignment->id}/creneaux/{$foreignSlot->id}/inscriptions/{$scope['enrollment']->id}/note",
            ['valeur' => 10],
        )
        ->assertNotFound();
    $this->withToken($token)
        ->putJson(
            "/api/v1/me/attributions/{$assignment->id}/creneaux/{$slot->id}/inscriptions/{$foreignScope['enrollment']->id}/note",
            ['valeur' => 10],
        )
        ->assertNotFound();
    $this->withToken($token)
        ->putJson(
            "/api/v1/me/attributions/not-an-id/creneaux/{$slot->id}/inscriptions/{$scope['enrollment']->id}/note",
            ['valeur' => 10],
        )
        ->assertNotFound();

    $scope['enrollment']->update(['statut' => 'ABANDON']);
    $this->withToken($token)
        ->putJson($uri, ['valeur' => 10])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Notes can only be recorded for an active enrollment.',
        );

    $scope['enrollment']->update(['statut' => 'ACTIF']);
    $scope['student']->update(['actif' => false]);
    $this->withToken($token)
        ->putJson($uri, ['valeur' => 10])
        ->assertConflict()
        ->assertJsonPath(
            'detail',
            'Notes can only be recorded for an active student.',
        );

    $scope['student']->update(['actif' => true]);
    $scope['bulletin']->update(['verrouille' => true]);
    $this->withToken($token)
        ->putJson($uri, ['valeur' => 10])
        ->assertConflict()
        ->assertJsonPath('detail', 'The report card is locked.');

    $scope['bulletin']->update(['verrouille' => false]);
    VerrouillagePeriode::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'periode_id' => $scope['period']->id,
        'verrouille' => true,
        'verrouille_par' => $scope['user']->id,
        'date_verrouillage' => now(),
    ]);
    $this->withToken($token)
        ->putJson($uri, ['valeur' => 10])
        ->assertStatus(423)
        ->assertHeader('Content-Type', 'application/problem+json');

    $managementToken = academicMutationToken($scope);
    $this->app['auth']->forgetGuards();
    $this->withToken($managementToken)
        ->putJson($uri, ['valeur' => 10])
        ->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->putJson($uri, ['valeur' => 10])->assertUnauthorized();
    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('notes_historiques', 0);
    $this->assertDatabaseCount('suivis_encodage_notes', 0);
});

test('assigned teacher imports CSV and XLSX notes with correction and replay semantics', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00:00'));

    $context = createTeacherNoteImportContext('IMPORT');
    $scope = $context['scope'];
    $this->app['auth']->forgetGuards();

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => UploadedFile::fake()->createWithContent(
                'notes.csv',
                "matricule,note\nMAT-IMPORT,14.5\n",
            ),
        ])
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.corrected', 0)
        ->assertJsonPath('data.unchanged', 0);

    $note = Note::query()->firstOrFail();
    $progress = SuiviEncodageNote::query()->firstOrFail();
    $noteTimestamp = $note->updated_at?->toISOString();
    $entryTimestamp = $note->date_saisie?->toISOString();
    $progressTimestamp = $progress->updated_at?->toISOString();

    expect($note->valeur)->toBe('14.50')
        ->and($note->lock_version)->toBe(1)
        ->and($note->statut_resultat)->toBe(NoteResultStatus::Saisie)
        ->and($note->saisie_par)->toBe($context['teacher']->id)
        ->and($progress->nombre_notes_saisies)->toBe(1)
        ->and($progress->statut)->toBe('TERMINE');
    $this->assertDatabaseCount('notes_historiques', 0);

    $this->travel(1)->minute();

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                ['matricule', 'note'],
                ['MAT-IMPORT', 14.5],
            ]),
            'motif_correction' => 'Rejeu sans effet',
        ])
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.corrected', 0)
        ->assertJsonPath('data.unchanged', 1);

    expect($note->refresh()->updated_at?->toISOString())->toBe($noteTimestamp)
        ->and($note->date_saisie?->toISOString())->toBe($entryTimestamp)
        ->and($progress->refresh()->updated_at?->toISOString())
        ->toBe($progressTimestamp);

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                ['matricule', 'note', 'lock_version'],
                ['MAT-IMPORT', 16, 1],
            ]),
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'detail',
            'A correction reason is required to modify existing notes.',
        );

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                ['matricule', 'note', 'lock_version'],
                ['MAT-IMPORT', 16, 1],
            ]),
            'motif_correction' => 'Correction après vérification',
        ])
        ->assertOk()
        ->assertJsonPath('data.corrected', 1)
        ->assertJsonPath('data.unchanged', 0);

    $this->assertDatabaseCount('notes', 1);
    $this->assertDatabaseCount('notes_historiques', 1);
    $this->assertDatabaseHas('notes_historiques', [
        'note_id' => $note->id,
        'ancien_statut_resultat' => 'SAISIE',
        'nouveau_statut_resultat' => 'SAISIE',
        'ancienne_valeur' => 14.5,
        'nouvelle_valeur' => 16,
        'modifie_par' => $context['teacher']->id,
        'motif' => 'Correction après vérification',
    ]);
    expect($note->refresh()->valeur)->toBe('16.00')
        ->and($note->lock_version)->toBe(2)
        ->and($progress->refresh()->updated_at?->toISOString())
        ->toBe($progressTimestamp);

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                ['matricule', 'note', 'lock_version'],
                ['MAT-IMPORT', 15, 1],
            ]),
            'motif_correction' => 'Correction depuis un export obsolète',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'NOTE_VERSION_CONFLICT')
        ->assertJsonPath(
            'detail',
            'Note row 2 was modified by another request.',
        );

    expect($note->refresh()->valeur)->toBe('16.00')
        ->and($note->lock_version)->toBe(2);
    $this->assertDatabaseCount('notes_historiques', 1);
});

test('teacher imports absent and exempt results from CSV and XLSX atomically', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00:00'));

    $context = createTeacherNoteImportContext('IMPORT-STATUS');
    $this->app['auth']->forgetGuards();

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                ['matricule', 'note', 'statut_resultat'],
                ['MAT-IMPORT-STATUS', null, 'ABSENTE'],
            ]),
        ])
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.corrected', 0)
        ->assertJsonPath('data.unchanged', 0);

    $note = Note::query()->firstOrFail();
    $progress = SuiviEncodageNote::query()->firstOrFail();
    $noteTimestamp = $note->updated_at?->toISOString();
    $progressTimestamp = $progress->updated_at?->toISOString();

    expect($note->statut_resultat)->toBe(NoteResultStatus::Absente)
        ->and($note->lock_version)->toBe(1)
        ->and($note->valeur)->toBeNull()
        ->and($progress->nombre_notes_saisies)->toBe(1)
        ->and($progress->statut)->toBe('TERMINE');

    $this->travel(1)->minute();

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => UploadedFile::fake()->createWithContent(
                'notes.csv',
                "matricule,note,statut_resultat\nMAT-IMPORT-STATUS,,ABSENTE\n",
            ),
            'motif_correction' => 'Replay',
        ])
        ->assertOk()
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.corrected', 0)
        ->assertJsonPath('data.unchanged', 1);

    expect($note->refresh()->updated_at?->toISOString())
        ->toBe($noteTimestamp)
        ->and($progress->refresh()->updated_at?->toISOString())
        ->toBe($progressTimestamp);
    $this->assertDatabaseCount('notes_historiques', 0);

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                [
                    'matricule',
                    'note',
                    'statut_resultat',
                    'lock_version',
                ],
                ['MAT-IMPORT-STATUS', null, 'DISPENSEE', 1],
            ]),
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'detail',
            'A correction reason is required to modify existing notes.',
        );

    expect($note->refresh()->statut_resultat)
        ->toBe(NoteResultStatus::Absente);
    $this->assertDatabaseCount('notes_historiques', 0);

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                [
                    'matricule',
                    'note',
                    'statut_resultat',
                    'lock_version',
                ],
                ['MAT-IMPORT-STATUS', null, 'DISPENSEE', 1],
            ]),
            'motif_correction' => 'Dispense confirmee',
        ])
        ->assertOk()
        ->assertJsonPath('data.corrected', 1);

    expect($note->refresh()->statut_resultat)
        ->toBe(NoteResultStatus::Dispensee)
        ->and($note->lock_version)->toBe(2)
        ->and($note->valeur)->toBeNull()
        ->and($progress->refresh()->updated_at?->toISOString())
        ->toBe($progressTimestamp);
    $this->assertDatabaseHas('notes_historiques', [
        'note_id' => $note->id,
        'ancien_statut_resultat' => 'ABSENTE',
        'nouveau_statut_resultat' => 'DISPENSEE',
        'ancienne_valeur' => null,
        'nouvelle_valeur' => null,
        'motif' => 'Dispense confirmee',
    ]);

    foreach ([
        "matricule,note,statut_resultat\nMAT-IMPORT-STATUS,12,ABSENTE\n",
        "matricule,note,statut_resultat\nMAT-IMPORT-STATUS,,SAISIE\n",
        "matricule,note,statut_resultat\nMAT-IMPORT-STATUS,,ANNULEE\n",
        "matricule,note,statut_resultat\nMAT-IMPORT-STATUS,,INCONNUE\n",
    ] as $invalidCsv) {
        $this->withToken($context['token'])
            ->postJson($context['uri'], [
                'file' => UploadedFile::fake()->createWithContent(
                    'notes.csv',
                    $invalidCsv,
                ),
                'motif_correction' => 'Invalid import',
            ])
            ->assertUnprocessable();
    }

    expect($note->refresh()->statut_resultat)
        ->toBe(NoteResultStatus::Dispensee);
    $this->assertDatabaseCount('notes', 1);
    $this->assertDatabaseCount('notes_historiques', 1);
});

test('teacher note import rejects one stale row before changing any note', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00:00'));

    $context = createTeacherNoteImportContext('IMPORT-STALE-BATCH');
    $scope = $context['scope'];
    $secondStudent = Eleve::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'etablissement_id' => $scope['establishment']->id,
        'num_permanent' => 'MAT-IMPORT-STALE-2',
        'nom' => 'Import stale',
        'actif' => true,
    ]);
    $secondEnrollment = Inscription::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'eleve_id' => $secondStudent->id,
        'classe_id' => $scope['class']->id,
        'annee_scolaire_id' => $scope['year']->id,
        'statut' => 'ACTIF',
    ]);
    $secondReportCard = Bulletin::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'inscription_id' => $secondEnrollment->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'decision_statut' => 'EN_COURS',
        'verrouille' => false,
    ]);
    $firstNote = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 14,
        'saisie_par' => $context['teacher']->id,
        'date_saisie' => now(),
    ]);
    $secondNote = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $secondReportCard->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'valeur' => 12,
        'saisie_par' => $context['teacher']->id,
        'date_saisie' => now(),
    ]);
    $secondNote->forceFill(['lock_version' => 2])->save();
    $this->app['auth']->forgetGuards();

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                ['matricule', 'note', 'lock_version'],
                [$scope['student']->num_permanent, 15, 1],
                [$secondStudent->num_permanent, 13, 1],
            ]),
            'motif_correction' => 'Correction groupée',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'NOTE_VERSION_CONFLICT')
        ->assertJsonPath(
            'detail',
            'Note row 3 was modified by another request.',
        );

    expect($firstNote->refresh()->valeur)->toBe('14.00')
        ->and($firstNote->lock_version)->toBe(1)
        ->and($secondNote->refresh()->valeur)->toBe('12.00')
        ->and($secondNote->lock_version)->toBe(2);
    $this->assertDatabaseCount('notes_historiques', 0);
    $this->assertDatabaseCount('suivis_encodage_notes', 0);
});

test('teacher note import validates every row before writing anything', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $context = createTeacherNoteImportContext('IMPORT-ROLLBACK');
    $this->app['auth']->forgetGuards();

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => UploadedFile::fake()->createWithContent(
                'notes.csv',
                "matricule,note\nMAT-IMPORT-ROLLBACK,12\nINCONNU,14\n",
            ),
        ])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath(
            'detail',
            'Note row 3 does not identify one active student.',
        );

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('notes_historiques', 0);
    $this->assertDatabaseCount('suivis_encodage_notes', 0);

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => UploadedFile::fake()->createWithContent(
                'notes.csv',
                "matricule,note\n MAT-IMPORT-ROLLBACK ,12\nmat-import-rollback,13\n",
            ),
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'detail',
            'The note file contains duplicate matricules.',
        );

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                ['matricule', 'note'],
                ['MAT-IMPORT-ROLLBACK', '10.123'],
            ]),
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'detail',
            'Note row 2 contains an invalid matricule, note, or lock version.',
        );

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => noteXlsxUpload([
                ['matricule', 'note'],
                ['MAT-IMPORT-ROLLBACK', '=10+2'],
            ]),
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'detail',
            'The XLSX note file contains unsupported cell content.',
        );

    VerrouillagePeriode::withoutGlobalScopes()->create([
        'tenant_id' => $context['scope']['tenant']->id,
        'classe_id' => $context['scope']['class']->id,
        'periode_id' => $context['scope']['period']->id,
        'verrouille' => true,
        'verrouille_par' => $context['scope']['user']->id,
        'date_verrouillage' => now(),
    ]);

    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => UploadedFile::fake()->createWithContent(
                'notes.csv',
                "matricule,note\nMAT-IMPORT-ROLLBACK,12\n",
            ),
        ])
        ->assertStatus(423);

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('suivis_encodage_notes', 0);
});

test('database rejects incoherent note result statuses and values on insert and update', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $noteAttributes = [
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];

    foreach ([
        ['statut_resultat' => 'SAISIE', 'valeur' => null],
        ['statut_resultat' => 'SAISIE', 'valeur' => -0.01],
        ['statut_resultat' => 'ABSENTE', 'valeur' => 1],
        ['statut_resultat' => 'DISPENSEE', 'valeur' => 1],
        ['statut_resultat' => 'ANNULEE', 'valeur' => 1],
    ] as $invalidResult) {
        expect(
            fn () => DB::table('notes')->insert(
                ['public_id' => (string) Str::ulid(), ...$noteAttributes, ...$invalidResult],
            ),
        )->toThrow(QueryException::class);
    }

    $noteId = DB::table('notes')->insertGetId([
        'public_id' => (string) Str::ulid(),
        ...$noteAttributes,
        'statut_resultat' => 'ABSENTE',
        'valeur' => null,
    ]);

    expect(
        fn () => DB::table('notes')
            ->where('id', $noteId)
            ->update([
                'statut_resultat' => 'SAISIE',
                'valeur' => null,
            ]),
    )->toThrow(QueryException::class);

    DB::table('notes')
        ->where('id', $noteId)
        ->update([
            'statut_resultat' => 'SAISIE',
            'valeur' => 0,
        ]);

    $this->assertDatabaseHas('notes', [
        'id' => $noteId,
        'statut_resultat' => 'SAISIE',
        'valeur' => 0,
    ]);
});

test('database rejects incoherent historical result pairs and preserves legacy unknown pairs', function () {
    $scope = createAcademicMutationScope(createAcademicMutationCatalog());
    $note = Note::query()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'statut_resultat' => NoteResultStatus::Saisie,
        'valeur' => 12,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    $historyAttributes = [
        'note_id' => $note->id,
        'modifie_par' => $scope['user']->id,
        'motif' => 'Database invariant test',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    foreach ([
        [
            'ancien_statut_resultat' => 'SAISIE',
            'ancienne_valeur' => null,
            'nouveau_statut_resultat' => 'SAISIE',
            'nouvelle_valeur' => 12,
        ],
        [
            'ancien_statut_resultat' => null,
            'ancienne_valeur' => 10,
            'nouveau_statut_resultat' => 'SAISIE',
            'nouvelle_valeur' => 12,
        ],
        [
            'ancien_statut_resultat' => 'SAISIE',
            'ancienne_valeur' => 10,
            'nouveau_statut_resultat' => 'DISPENSEE',
            'nouvelle_valeur' => 12,
        ],
        [
            'ancien_statut_resultat' => 'SAISIE',
            'ancienne_valeur' => 10,
            'nouveau_statut_resultat' => 'SAISIE',
            'nouvelle_valeur' => -1,
        ],
    ] as $invalidHistory) {
        expect(
            fn () => DB::table('notes_historiques')->insert(
                [...$historyAttributes, ...$invalidHistory],
            ),
        )->toThrow(QueryException::class);
    }

    $legacyHistoryId = DB::table('notes_historiques')->insertGetId([
        ...$historyAttributes,
        'ancien_statut_resultat' => null,
        'ancienne_valeur' => null,
        'nouveau_statut_resultat' => null,
        'nouvelle_valeur' => null,
    ]);

    expect(
        fn () => DB::table('notes_historiques')
            ->where('id', $legacyHistoryId)
            ->update([
                'nouveau_statut_resultat' => 'ABSENTE',
                'nouvelle_valeur' => 1,
            ]),
    )->toThrow(QueryException::class);

    DB::table('notes_historiques')->insert([
        ...$historyAttributes,
        'ancien_statut_resultat' => 'SAISIE',
        'ancienne_valeur' => 12,
        'nouveau_statut_resultat' => 'ABSENTE',
        'nouvelle_valeur' => null,
    ]);

    $this->assertDatabaseCount('notes_historiques', 2);
});

test('teacher note import fails closed on route payload and authorization boundaries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15'));

    $context = createTeacherNoteImportContext('IMPORT-BOUNDARY');
    $foreignScope = createAcademicMutationScope(
        $context['catalog'],
        'IMPORT-FOREIGN',
        $context['scope']['tenant'],
    );
    $foreignSlot = ModeleMaximum::query()
        ->where('node_id', $foreignScope['node']->id)
        ->firstOrFail();
    $file = fn (): UploadedFile => UploadedFile::fake()->createWithContent(
        'notes.csv',
        "matricule,note\nMAT-IMPORT-BOUNDARY,12\n",
    );
    $otherTeacher = createCourseAssignmentTeacher(
        $context['scope'],
        'IMPORT-BOUNDARY-OTHER',
    );
    $otherTeacherToken = $otherTeacher->createToken(
        'other-teacher-note-import-boundaries',
        ["tenant:{$context['scope']['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->withToken($otherTeacherToken)
        ->postJson($context['uri'], ['file' => $file()])
        ->assertNotFound();
    $this->assertDatabaseCount('notes', 0);
    $this->app['auth']->forgetGuards();

    $this->withToken($context['token'])
        ->postJson("{$context['uri']}?unexpected=1", ['file' => $file()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($context['token'])
        ->postJson($context['uri'], [
            'file' => $file(),
            'classe_id' => $context['scope']['class']->id,
        ])
        ->assertUnprocessable();
    $this->withToken($context['token'])
        ->postJson(
            "/api/v1/me/attributions/{$context['assignment']->id}/creneaux/{$foreignSlot->id}/imports-notes",
            ['file' => $file()],
        )
        ->assertNotFound();
    $this->withToken($context['token'])
        ->postJson(
            "/api/v1/me/attributions/not-an-id/creneaux/{$context['slot']->id}/imports-notes",
            ['file' => $file()],
        )
        ->assertNotFound();

    $managementToken = academicMutationToken($context['scope']);
    $this->app['auth']->forgetGuards();
    $this->withToken($managementToken)
        ->postJson($context['uri'], ['file' => $file()])
        ->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->postJson($context['uri'], ['file' => $file()])
        ->assertUnauthorized();
    $this->assertDatabaseCount('notes', 0);
});

test('management previews a deterministic non official report card calculation without persistence', function () {
    $scope = createAcademicMutationScope(
        createAcademicMutationCatalog(),
        'CALCULATION-PREVIEW',
    );
    $scope['period']->update(['calculable' => true]);
    $scope['model']->update(['verrouille' => true]);
    VerrouillagePeriode::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'periode_id' => $scope['period']->id,
        'verrouille' => true,
        'verrouille_par' => $scope['user']->id,
        'date_verrouillage' => now(),
    ]);
    $note = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'statut_resultat' => NoteResultStatus::Saisie,
        'valeur' => 12,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    $token = academicMutationToken($scope);
    $uri = "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$scope['period']->id}/apercu-calcul";
    $bulletinAttributes = $scope['bulletin']->fresh()?->getAttributes();
    $auditCount = DB::table('audit_logs')->count();

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $firstResponse = $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.official', false)
        ->assertJsonPath('data.persisted', false)
        ->assertJsonPath('data.engine_version', '1.0.0')
        ->assertJsonPath('data.calculable', true)
        ->assertJsonPath('data.publication_ready', true)
        ->assertJsonPath('data.total_obtenu', '12.00')
        ->assertJsonPath('data.total_maxima', '20.00')
        ->assertJsonPath('data.pourcentage', '60.00')
        ->assertJsonPath('data.blockers', []);

    $queryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();
    $firstData = $firstResponse->json('data');

    expect($queryCount)->toBeLessThanOrEqual(30)
        ->and($firstData['configuration_hash'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($firstData['input_hash'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($firstData['result_hash'])->toMatch('/^[a-f0-9]{64}$/');

    $secondData = $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->json('data');

    expect($secondData)->toBe($firstData)
        ->and($scope['bulletin']->fresh()?->getAttributes())
        ->toBe($bulletinAttributes)
        ->and(DB::table('audit_logs')->count())->toBe($auditCount);

    $note->update(['valeur' => 14]);
    $changedData = $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.total_obtenu', '14.00')
        ->assertJsonPath('data.pourcentage', '70.00')
        ->json('data');

    expect($changedData['configuration_hash'])
        ->toBe($firstData['configuration_hash'])
        ->and($changedData['input_hash'])->not->toBe($firstData['input_hash'])
        ->and($changedData['result_hash'])
        ->not->toBe($firstData['result_hash']);
});

test('calculation preview fails closed on incomplete results scope and authorization boundaries', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'CALCULATION-BOUNDARY');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'CALCULATION-FOREIGN',
        $scope['tenant'],
    );
    $scope['period']->update(['calculable' => true]);
    $scope['model']->update(['verrouille' => true]);
    $note = Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'statut_resultat' => NoteResultStatus::Absente,
        'valeur' => null,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    $token = academicMutationToken($scope);
    $uri = "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$scope['period']->id}/apercu-calcul";

    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.official', false)
        ->assertJsonPath('data.persisted', false)
        ->assertJsonPath('data.calculable', false)
        ->assertJsonPath('data.publication_ready', false)
        ->assertJsonPath('data.total_obtenu', null)
        ->assertJsonPath(
            'data.blockers.0.code',
            'UNSUPPORTED_RESULT_STATUS',
        );

    $note->delete();
    $this->withToken($token)
        ->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.calculable', false)
        ->assertJsonPath('data.blockers.0.code', 'MISSING_RESULT');

    $this->withToken($token)
        ->getJson("{$uri}?unexpected=1")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($token)
        ->getJson(
            "/api/v1/bulletins/{$foreignScope['bulletin']->id}/periodes/{$foreignScope['period']->id}/apercu-calcul",
        )
        ->assertNotFound();
    $this->withToken($token)
        ->getJson(
            "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$foreignScope['period']->id}/apercu-calcul",
        )
        ->assertNotFound();
    $this->withToken($token)
        ->getJson(
            "/api/v1/bulletins/not-an-id/periodes/{$scope['period']->id}/apercu-calcul",
        )
        ->assertNotFound();

    $titular = createClassTitularCandidate($scope, 'CALCULATION-BOUNDARY');
    Classe::withoutGlobalScopes()
        ->whereKey($scope['class']->id)
        ->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'calculation-preview-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($titularToken)
        ->getJson($uri)
        ->assertForbidden();

    $assignedTeacher = createCourseAssignmentTeacher(
        $scope,
        'CALCULATION-BOUNDARY',
    );
    AttributionCours::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'classe_id' => $scope['class']->id,
        'modele_bulletin_id' => $scope['model']->id,
        'node_id' => $scope['node']->id,
        'enseignant_id' => $assignedTeacher->id,
        'actif' => true,
    ]);
    $teacherToken = $assignedTeacher->createToken(
        'calculation-preview-teacher',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($teacherToken)
        ->getJson($uri)
        ->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->getJson($uri)->assertUnauthorized();
});

test('management publishes the official report card calculation with persistence and audit', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'PUBLICATION');
    $scope['period']->update(['calculable' => true]);
    $scope['model']->update(['verrouille' => true]);
    Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'statut_resultat' => NoteResultStatus::Saisie,
        'valeur' => 12,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    DB::table('baremes_mentions')->insert([
        'tenant_id' => $scope['tenant']->id,
        'cycle_id' => $catalog['cycle_id'],
        'system_template_id' => $catalog['template_id'],
        'min_pourcentage' => 0.00,
        'max_pourcentage' => 100.00,
        'mention' => 'PASSABLE',
        'decision_defaut' => 'PASSE',
        'ordre' => 1,
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $token = academicMutationToken($scope);
    $uri = "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$scope['period']->id}/publier";
    $auditCount = DB::table('audit_logs')->count();

    $this->withToken($token)
        ->postJson($uri)
        ->assertOk()
        ->assertJsonPath('data.official', true)
        ->assertJsonPath('data.persisted', true)
        ->assertJsonPath('data.engine_version', '1.0.0')
        ->assertJsonPath('data.total_obtenu', '12.00')
        ->assertJsonPath('data.total_maxima', '20.00')
        ->assertJsonPath('data.pourcentage', '60.00')
        ->assertJsonPath('data.mention', 'PASSABLE')
        ->assertJsonPath('data.decision', 'PASSE')
        ->assertJsonPath('data.rang', 1)
        ->assertJsonPath('data.nombre_eleves_classe', 1)
        ->assertJsonPath('data.configuration_hash', fn (string $hash): bool => (bool) preg_match('/^[a-f0-9]{64}$/', $hash))
        ->assertJsonPath('data.input_hash', fn (string $hash): bool => (bool) preg_match('/^[a-f0-9]{64}$/', $hash))
        ->assertJsonPath('data.result_hash', fn (string $hash): bool => (bool) preg_match('/^[a-f0-9]{64}$/', $hash))
        ->assertJsonPath('data.publication_id', 1)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.publication_statut', 'PUBLIEE');

    $bulletin = Bulletin::withoutGlobalScopes()->find($scope['bulletin']->id);
    $lock = VerrouillagePeriode::withoutGlobalScopes()
        ->where('classe_id', $scope['class']->id)
        ->where('periode_id', $scope['period']->id)
        ->first();

    expect($bulletin->total_obtenu)->toBe('12.00')
        ->and($bulletin->pourcentage)->toBe('60.00')
        ->and($bulletin->calcule_at)->not->toBeNull()
        ->and($bulletin->verrouille)->toBeTrue()
        ->and($bulletin->mention)->toBe('PASSABLE')
        ->and($bulletin->decision_proposee)->toBe('PASSE')
        ->and($bulletin->decision_statut)->toBe('EN_COURS')
        ->and($bulletin->rang)->toBe(1)
        ->and($lock)->not->toBeNull()
        ->and($lock->verrouille)->toBeTrue()
        ->and(DB::table('audit_logs')->count())->toBe($auditCount + 1)
        ->and(DB::table('audit_logs')->where('action', 'BULLETIN_PUBLIE')->count())->toBe(1);

    $this->withToken($token)
        ->postJson($uri)
        ->assertOk()
        ->assertJsonPath('data.rang', 1);

    expect(DB::table('publications')->count())->toBe(1)
        ->and(DB::table('resultats_bulletins')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'BULLETIN_PUBLIE')->count())->toBe(1);
});

test('report card publication fails closed on results scope and authorization boundaries', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'PUBLICATION-BOUNDARY');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'PUBLICATION-FOREIGN',
        $scope['tenant'],
    );
    $scope['period']->update(['calculable' => true]);
    $scope['model']->update(['verrouille' => true]);
    Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'statut_resultat' => NoteResultStatus::Saisie,
        'valeur' => 12,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    $token = academicMutationToken($scope);
    $uri = "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$scope['period']->id}/publier";

    $this->withToken($token)
        ->postJson("{$uri}?unexpected=1")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($token)
        ->postJson(
            "/api/v1/bulletins/{$foreignScope['bulletin']->id}/periodes/{$foreignScope['period']->id}/publier",
        )
        ->assertNotFound();
    $this->withToken($token)
        ->postJson(
            "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$foreignScope['period']->id}/publier",
        )
        ->assertNotFound();
    $this->withToken($token)
        ->postJson(
            "/api/v1/bulletins/not-an-id/periodes/{$scope['period']->id}/publier",
        )
        ->assertNotFound();

    $titular = createClassTitularCandidate($scope, 'PUBLICATION-BOUNDARY');
    Classe::withoutGlobalScopes()
        ->whereKey($scope['class']->id)
        ->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'publication-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($titularToken)
        ->postJson($uri)
        ->assertForbidden();

    $assignedTeacher = createCourseAssignmentTeacher(
        $scope,
        'PUBLICATION-BOUNDARY',
    );
    $teacherToken = $assignedTeacher->createToken(
        'publication-teacher',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($teacherToken)
        ->postJson($uri)
        ->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->postJson($uri)->assertUnauthorized();

    expect(DB::table('audit_logs')->where('action', 'BULLETIN_PUBLIE')->count())->toBe(0);
});

test('management rectifies a published report card with a mandatory motif', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'RECTIFICATION');
    $scope['period']->update(['calculable' => true]);
    $scope['model']->update(['verrouille' => true]);
    Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'statut_resultat' => NoteResultStatus::Saisie,
        'valeur' => 12,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    DB::table('baremes_mentions')->insert([
        'tenant_id' => $scope['tenant']->id,
        'cycle_id' => $catalog['cycle_id'],
        'system_template_id' => $catalog['template_id'],
        'min_pourcentage' => 0.00,
        'max_pourcentage' => 100.00,
        'mention' => 'PASSABLE',
        'decision_defaut' => 'PASSE',
        'ordre' => 1,
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $token = academicMutationToken($scope);
    $publishUri = "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$scope['period']->id}/publier";
    $uri = "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$scope['period']->id}/rectifications";

    $this->withToken($token)->postJson($publishUri)->assertOk();
    $originalId = DB::table('publications')->where('statut', 'PUBLIEE')->value('id');

    $this->withToken($token)
        ->postJson($uri, ['motif' => 'Erreur de total de classe'])
        ->assertOk()
        ->assertJsonPath('data.publication_id', fn (int $id): bool => $id > $originalId)
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.publication_statut', 'PUBLIEE');

    $newPublicationId = DB::table('publications')->where('statut', 'PUBLIEE')->value('id');

    expect(DB::table('publications')->where('id', $originalId)->value('statut'))->toBe('RECTIFIEE')
        ->and($newPublicationId)->not->toBe($originalId)
        ->and(DB::table('rectifications')->value('publication_id'))->toBe($originalId)
        ->and(DB::table('rectifications')->value('nouvelle_publication_id'))->toBe($newPublicationId)
        ->and(DB::table('rectifications')->value('motif'))->toBe('Erreur de total de classe')
        ->and(DB::table('audit_logs')->where('action', 'BULLETIN_RECTIFIE')->count())->toBe(1);
});

test('report card rectification fails closed on scope and authorization boundaries', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'RECTIFICATION-BOUNDARY');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'RECTIFICATION-FOREIGN',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);
    $uri = "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$scope['period']->id}/rectifications";

    $this->withToken($token)
        ->postJson($uri)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['motif']);
    $this->withToken($token)
        ->postJson("{$uri}?unexpected=1", ['motif' => 'Test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($token)
        ->postJson($uri, ['motif' => 'Sans publication initiale'])
        ->assertConflict();
    $this->withToken($token)
        ->postJson(
            "/api/v1/bulletins/{$foreignScope['bulletin']->id}/periodes/{$foreignScope['period']->id}/rectifications",
            ['motif' => 'Test'],
        )
        ->assertNotFound();
    $this->withToken($token)
        ->postJson(
            "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$foreignScope['period']->id}/rectifications",
            ['motif' => 'Test'],
        )
        ->assertNotFound();

    $titular = createClassTitularCandidate($scope, 'RECTIFICATION-BOUNDARY');
    Classe::withoutGlobalScopes()
        ->whereKey($scope['class']->id)
        ->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'rectification-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($titularToken)
        ->postJson($uri, ['motif' => 'Test'])
        ->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->postJson($uri, ['motif' => 'Test'])->assertUnauthorized();

    expect(DB::table('rectifications')->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'BULLETIN_RECTIFIE')->count())->toBe(0);
});

test('management generates the class proclamation with ranked lines and audit', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'PROCLAMATION');
    $scope['period']->update(['calculable' => true]);
    $scope['model']->update(['verrouille' => true]);
    Note::withoutGlobalScopes()->create([
        'tenant_id' => $scope['tenant']->id,
        'bulletin_id' => $scope['bulletin']->id,
        'node_id' => $scope['node']->id,
        'periode_id' => $scope['period']->id,
        'statut_resultat' => NoteResultStatus::Saisie,
        'valeur' => 12,
        'saisie_par' => $scope['user']->id,
        'date_saisie' => now(),
    ]);
    DB::table('baremes_mentions')->insert([
        'tenant_id' => $scope['tenant']->id,
        'cycle_id' => $catalog['cycle_id'],
        'system_template_id' => $catalog['template_id'],
        'min_pourcentage' => 0.00,
        'max_pourcentage' => 100.00,
        'mention' => 'PASSABLE',
        'decision_defaut' => 'PASSE',
        'ordre' => 1,
        'actif' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $token = academicMutationToken($scope);
    $publishUri = "/api/v1/bulletins/{$scope['bulletin']->id}/periodes/{$scope['period']->id}/publier";
    $uri = "/api/v1/classes/{$scope['class']->id}/periodes/{$scope['period']->id}/proclamations";
    $auditCount = DB::table('audit_logs')->count();

    $this->withToken($token)->postJson($publishUri)->assertOk();
    $publieeId = DB::table('publications')->where('statut', 'PUBLIEE')->value('id');

    $this->withToken($token)
        ->postJson($uri)
        ->assertOk()
        ->assertJsonPath('data.statut', 'GENEREE')
        ->assertJsonPath('data.type', 'PERIODE')
        ->assertJsonPath('data.classe_id', $scope['class']->id)
        ->assertJsonPath('data.periode_id', $scope['period']->id)
        ->assertJsonPath('data.annee_scolaire_id', $scope['year']->id)
        ->assertJsonPath('data.generee_par', $scope['user']->id)
        ->assertJsonPath('data.publication_id', $publieeId);

    $lignes = ProclamationLigne::query()->get();
    $auditLogs = DB::table('audit_logs')->where('action', 'PROCLAMATION_GENEREE')->get();

    expect(Proclamation::query()->count())->toBe(1)
        ->and($lignes)->toHaveCount(1)
        ->and($lignes->first()->bulletin_id)->toBe($scope['bulletin']->id)
        ->and($lignes->first()->publication_id)->toBe($publieeId)
        ->and($lignes->first()->rang)->toBe(1)
        ->and($lignes->first()->decision)->toBe('PASSE')
        ->and($auditLogs)->toHaveCount(1)
        ->and(json_decode($auditLogs->first()->nouvelles_valeurs, true)['nombre_lignes'])->toBe(1);

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    $this->withToken($token)
        ->postJson($uri)
        ->assertOk()
        ->assertJsonPath('data.statut', 'GENEREE');

    $queryCount = count(DB::connection()->getQueryLog());
    DB::connection()->disableQueryLog();

    expect(Proclamation::query()->count())->toBe(1)
        ->and(ProclamationLigne::query()->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'PROCLAMATION_GENEREE')->count())->toBe(1)
        ->and($queryCount)->toBeLessThanOrEqual(15);
});

test('proclamation generation fails closed on results scope and authorization boundaries', function () {
    $catalog = createAcademicMutationCatalog();
    $scope = createAcademicMutationScope($catalog, 'PROCLAMATION-BOUNDARY');
    $foreignScope = createAcademicMutationScope(
        $catalog,
        'PROCLAMATION-FOREIGN',
        $scope['tenant'],
    );
    $token = academicMutationToken($scope);
    $uri = "/api/v1/classes/{$scope['class']->id}/periodes/{$scope['period']->id}/proclamations";

    $this->withToken($token)
        ->postJson("{$uri}?unexpected=1")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$scope['class']->id}/periodes/{$foreignScope['period']->id}/proclamations",
        )
        ->assertNotFound();
    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/{$foreignScope['class']->id}/periodes/{$foreignScope['period']->id}/proclamations",
        )
        ->assertNotFound();
    $this->withToken($token)
        ->postJson(
            "/api/v1/classes/not-an-id/periodes/{$scope['period']->id}/proclamations",
        )
        ->assertNotFound();

    $titular = createClassTitularCandidate($scope, 'PROCLAMATION-BOUNDARY');
    Classe::withoutGlobalScopes()
        ->whereKey($scope['class']->id)
        ->update(['titulaire_id' => $titular->id]);
    $titularToken = $titular->createToken(
        'proclamation-titular',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($titularToken)
        ->postJson($uri)
        ->assertForbidden();

    $assignedTeacher = createCourseAssignmentTeacher(
        $scope,
        'PROCLAMATION-BOUNDARY',
    );
    $teacherToken = $assignedTeacher->createToken(
        'proclamation-teacher',
        ["tenant:{$scope['tenant']->id}"],
    )->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($teacherToken)
        ->postJson($uri)
        ->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();
    $this->postJson($uri)->assertUnauthorized();

    expect(Proclamation::query()->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'PROCLAMATION_GENEREE')->count())->toBe(0);
});

test('academic routes expose the safer contract and authenticated API throttling', function () {
    expect(Route::has('api.v1.classes.presences.index'))->toBeTrue()
        ->and(Route::has('api.v1.classes.presences.store'))->toBeTrue()
        ->and(Route::has('api.v1.inscriptions.presences.index'))->toBeTrue()
        ->and(Route::has('api.v1.notes.history'))->toBeTrue()
        ->and(Route::has('api.v1.classes.periodes.verrouillages.store'))->toBeTrue()
        ->and(Route::has('api.v1.classes.periodes.reouvertures.store'))->toBeTrue()
        ->and(Route::has('api.v1.classes.inscriptions.index'))->toBeTrue()
        ->and(Route::has('api.v1.classes.cours.index'))->toBeTrue()
        ->and(Route::has('api.v1.classes.cours.attribution.update'))->toBeTrue()
        ->and(Route::has('api.v1.me.attributions.index'))->toBeTrue()
        ->and(Route::has('api.v1.me.attributions.creneaux.index'))->toBeTrue()
        ->and(Route::has(
            'api.v1.me.attributions.creneaux.feuille-notes.show',
        ))->toBeTrue()
        ->and(Route::has(
            'api.v1.me.attributions.creneaux.inscriptions.note.update',
        ))->toBeTrue()
        ->and(Route::has(
            'api.v1.me.attributions.creneaux.imports-notes.store',
        ))->toBeTrue()
        ->and(Route::has('api.v1.classes.titulariat.update'))->toBeTrue()
        ->and(Route::has('api.v1.notes.index'))->toBeTrue()
        ->and(Route::has('api.v1.notes.show'))->toBeTrue()
        ->and(Route::has('api.v1.notes.by-classe'))->toBeTrue()
        ->and(Route::has('api.v1.notes.store'))->toBeFalse()
        ->and(Route::has('api.v1.notes.update'))->toBeFalse()
        ->and(Route::has('api.v1.notes.import'))->toBeFalse()
        ->and(Route::has('api.v1.notes.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.bulletins.index'))->toBeFalse()
        ->and(Route::has('api.v1.bulletins.show'))->toBeTrue()
        ->and(Route::has('api.v1.bulletins.by-classe'))->toBeTrue()
        ->and(Route::has(
            'api.v1.bulletins.periodes.apercu-calcul.show',
        ))->toBeTrue()
        ->and(Route::has('api.v1.annees-scolaires.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.annees-scolaires.update'))->toBeFalse()
        ->and(Route::has('api.v1.annees-scolaires.store'))->toBeFalse()
        ->and(Route::has('api.v1.annees-scolaires.open'))->toBeFalse()
        ->and(Route::has('api.v1.annees-scolaires.cloture.store'))->toBeFalse()
        ->and(Route::has('api.v1.annees-scolaires.index'))->toBeTrue()
        ->and(Route::has('api.v1.annees-scolaires.show'))->toBeTrue()
        ->and(Route::has('api.v1.annees-scolaires.courante.show'))->toBeTrue()
        ->and(Route::has('api.v1.ouvertures-annuelles.store'))->toBeTrue()
        ->and(Route::has('api.v1.ouvertures-annuelles.cloture.store'))->toBeTrue()
        ->and(Route::has('api.v1.classes.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.classes.clone-template'))->toBeFalse()
        ->and(Route::has('api.v1.eleves.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.eleves.import'))->toBeFalse()
        ->and(Route::has('api.v1.inscriptions.destroy'))->toBeFalse()
        ->and(Route::has('api.v1.bulletins.calculate'))->toBeFalse();

    $legacyUris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): string => $route->uri());

    expect($legacyUris)->not->toContain('api/v1/attendance')
        ->and($legacyUris)->not->toContain('api/v1/inscriptions/{inscriptionId}/attendance')
        ->and($legacyUris)->not->toContain('api/v1/notes/{noteId}/history')
        ->and($legacyUris)->not->toContain('api/v1/periods/{periodeId}/lock')
        ->and($legacyUris)->not->toContain('api/v1/periods/{periodeId}/unlock')
        ->and($legacyUris)->not->toContain('api/v1/classes/{id}/clone-template')
        ->and($legacyUris)->not->toContain('api/v1/classes/{id}/students')
        ->and($legacyUris)->not->toContain('api/v1/notes/import')
        ->and($legacyUris)->not->toContain('api/v1/bulletins')
        ->and($legacyUris)->not->toContain('api/v1/bulletins/calculate');

    $route = Route::getRoutes()->getByName('api.v1.classes.presences.store');
    $middleware = app(Router::class)->gatherRouteMiddleware($route);

    expect($middleware)->toContain(TenantMiddleware::class)
        ->and($middleware)->toContain(EstablishmentMiddleware::class)
        ->and(collect($middleware)->contains(
            fn (string $name): bool => $name === ThrottleRequests::class.':api',
        ))->toBeTrue();

    $teacherRoute = Route::getRoutes()->getByName(
        'api.v1.me.attributions.index',
    );
    $teacherMiddleware = app(Router::class)->gatherRouteMiddleware(
        $teacherRoute,
    );

    expect($teacherMiddleware)->toContain(TenantMiddleware::class)
        ->and($teacherMiddleware)->toContain(EstablishmentMiddleware::class)
        ->and(collect($teacherMiddleware)->contains(
            fn (string $name): bool => $name === ThrottleRequests::class.':api',
        ))->toBeTrue();

    $teacherSlotsRoute = Route::getRoutes()->getByName(
        'api.v1.me.attributions.creneaux.index',
    );
    $teacherSlotsMiddleware = app(Router::class)->gatherRouteMiddleware(
        $teacherSlotsRoute,
    );

    expect($teacherSlotsMiddleware)->toContain(TenantMiddleware::class)
        ->and($teacherSlotsMiddleware)->toContain(EstablishmentMiddleware::class)
        ->and(collect($teacherSlotsMiddleware)->contains(
            fn (string $name): bool => $name === ThrottleRequests::class.':api',
        ))->toBeTrue();

    $gradeSheetRoute = Route::getRoutes()->getByName(
        'api.v1.me.attributions.creneaux.feuille-notes.show',
    );
    $gradeSheetMiddleware = app(Router::class)->gatherRouteMiddleware(
        $gradeSheetRoute,
    );

    expect($gradeSheetMiddleware)->toContain(TenantMiddleware::class)
        ->and($gradeSheetMiddleware)->toContain(EstablishmentMiddleware::class)
        ->and(collect($gradeSheetMiddleware)->contains(
            fn (string $name): bool => $name === ThrottleRequests::class.':api',
        ))->toBeTrue();

    $teacherNoteRoute = Route::getRoutes()->getByName(
        'api.v1.me.attributions.creneaux.inscriptions.note.update',
    );
    $teacherNoteMiddleware = app(Router::class)->gatherRouteMiddleware(
        $teacherNoteRoute,
    );

    expect($teacherNoteMiddleware)->toContain(TenantMiddleware::class)
        ->and($teacherNoteMiddleware)->toContain(EstablishmentMiddleware::class)
        ->and(collect($teacherNoteMiddleware)->contains(
            fn (string $name): bool => $name === ThrottleRequests::class.':api',
        ))->toBeTrue();
});
