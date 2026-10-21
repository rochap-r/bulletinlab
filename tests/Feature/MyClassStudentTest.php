<?php

use App\Domains\Student\Services\StudentSpreadsheetService;
use App\Models\AnneePeriodeMapping;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
use App\Models\Bulletin;
use App\Models\CalendrierPeriode;
use App\Models\CalendrierScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\ResultatBulletin;
use App\Models\Role;
use App\Models\SystemTemplateMaximum;
use App\Models\SystemTemplateNode;
use App\Models\SystemTemplatePeriode;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/** @return array{class: Classe, teacher: Utilisateur, token: string, year: AnneeScolaire} */
function myStudentScope(): array
{
    $tenant = Tenant::query()->create(['uuid' => Str::uuid(), 'nom_espace' => 'École test', 'slug' => 'eleves-'.Str::random(8), 'type_tenant' => 'ECOLE', 'statut' => 'ACTIF']);
    $school = Etablissement::query()->create(['tenant_id' => $tenant->id, 'nom' => 'École', 'actif' => true]);
    $teacher = Utilisateur::factory()->create(['tenant_id' => $tenant->id, 'etablissement_id' => $school->id, 'doit_changer_mot_de_passe' => false]);
    $role = Role::query()->firstOrCreate(['tenant_id' => null, 'code' => 'ENSEIGNANT'], ['nom' => 'Enseignant', 'system_role' => true]);
    $teacher->roles()->attach($role);
    $cycle = DB::table('ref_cycles')->insertGetId(['code' => 'TEST', 'nom' => 'Cycle test', 'ordre' => 1, 'actif' => true]);
    $niveau = DB::table('ref_niveaux')->insertGetId(['cycle_id' => $cycle, 'code' => 'TEST1', 'nom' => 'Niveau test', 'numero' => 1, 'ordre' => 1]);
    $layout = DB::table('system_layouts_bulletin')->insertGetId(['code' => 'TEST', 'nom' => 'Layout test', 'actif' => true]);
    $template = DB::table('system_templates_bulletin')->insertGetId(['code' => 'TEST', 'nom' => 'Modèle test', 'cycle_id' => $cycle, 'layout_id' => $layout, 'type_decoupage' => 'TRIMESTRIEL', 'version' => 'test', 'actif' => true]);
    $year = AnneeScolaire::query()->create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true, 'cloturee' => false]);
    $class = Classe::query()->create(['tenant_id' => $tenant->id, 'etablissement_id' => $school->id, 'annee_scolaire_id' => $year->id, 'cycle_id' => $cycle, 'niveau_id' => $niveau, 'system_template_id' => $template, 'nom' => '3ème A', 'titulaire_id' => $teacher->id, 'active' => true]);
    ModeleBulletin::query()->create(['tenant_id' => $tenant->id, 'classe_id' => $class->id, 'annee_scolaire_id' => $year->id, 'system_template_id' => $template, 'layout_id' => $layout, 'nom' => 'Bulletin', 'type_decoupage' => 'TRIMESTRIEL', 'actif' => true]);
    $token = $teacher->createToken('teacher', ["tenant:{$tenant->id}"])->plainTextToken;

    return compact('class', 'teacher', 'year', 'token');
}

function myStudentWorkbook(Spreadsheet $book): UploadedFile
{
    ob_start();
    (new Xlsx($book))->save('php://output');
    $contents = ob_get_clean();
    $book->disconnectWorksheets();

    return UploadedFile::fake()->createWithContent('eleves.xlsx', $contents);
}

beforeEach(function () {
    $this->travelTo('2026-10-17 10:00:00');
});

test('a titular sees the general and per-course rankings ordered by highest result', function () {
    $scope = myStudentScope();
    $model = ModeleBulletin::query()->where('classe_id', $scope['class']->id)->firstOrFail();
    $period = ModelePeriode::query()->create([
        'tenant_id' => $scope['class']->tenant_id,
        'modele_bulletin_id' => $model->id,
        'code' => 'P1',
        'nom' => 'Première période',
        'type_periode' => 'PERIODE',
        'ordre' => 1,
        'saisissable' => true,
        'calculable' => false,
        'actif' => true,
    ]);
    $calendar = CalendrierScolaire::query()->create(['code' => 'TEST-2026', 'libelle' => 'Calendrier test', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'actif' => true]);
    $calendarPeriod = CalendrierPeriode::query()->create(['calendrier_scolaire_id' => $calendar->id, 'code' => 'P1', 'nom' => 'Première période', 'type_periode' => 'PERIODE', 'cycle_scolaire' => 'SECONDAIRE_TECHNIQUE', 'date_debut' => '2026-09-01', 'date_fin' => '2026-11-04', 'ordre' => 1, 'saisie_notes_ouverte' => true, 'periode_active' => true]);
    $scope['year']->update(['calendrier_scolaire_id' => $calendar->id]);
    AnneePeriodeMapping::query()->create(['annee_scolaire_id' => $scope['year']->id, 'calendrier_periode_id' => $calendarPeriod->id, 'code_periode_template' => 'P1', 'saisie_ouverte' => true, 'verrouillee' => false]);
    $french = ModeleNode::query()->create(['tenant_id' => $scope['class']->tenant_id, 'modele_bulletin_id' => $model->id, 'type_node' => 'MATIERE', 'libelle' => 'Français', 'ordre' => 1, 'afficher_ligne' => true, 'obligatoire' => true]);
    $math = ModeleNode::query()->create(['tenant_id' => $scope['class']->tenant_id, 'modele_bulletin_id' => $model->id, 'type_node' => 'MATIERE', 'libelle' => 'Mathématiques', 'ordre' => 2, 'afficher_ligne' => true, 'obligatoire' => true]);
    foreach ([$french, $math] as $node) {
        ModeleMaximum::query()->create(['tenant_id' => $scope['class']->tenant_id, 'modele_bulletin_id' => $model->id, 'node_id' => $node->id, 'periode_id' => $period->id, 'maxima' => 20, 'coefficient' => 1]);
    }

    $this->withToken($scope['token']);
    $students = $this->postJson("/api/v1/me/classes/{$scope['class']->id}/eleves", ['eleves' => [['nom' => 'Deuxième'], ['nom' => 'Premier'], ['nom' => 'Incomplet']]])->assertCreated()->json('data');
    $scores = [[14, 12], [18, 16], [19, null]];
    foreach ($students as $index => $enrollment) {
        $bulletin = Bulletin::query()->where('inscription_id', $enrollment['id'])->firstOrFail();
        foreach ([$french, $math] as $courseIndex => $node) {
            if ($scores[$index][$courseIndex] === null) {
                continue;
            }
            Note::query()->create(['tenant_id' => $scope['class']->tenant_id, 'bulletin_id' => $bulletin->id, 'node_id' => $node->id, 'periode_id' => $period->id, 'statut_resultat' => 'SAISIE', 'valeur' => $scores[$index][$courseIndex], 'saisie_par' => $scope['teacher']->id]);
        }
    }

    $response = $this->getJson("/api/v1/me/classes/{$scope['class']->id}/classements?periode_id={$period->id}")
        ->assertOk()
        ->assertJsonPath('data.classement_general.0.eleve.nom_complet', 'Premier')
        ->assertJsonPath('data.classement_general.0.rang', 1)
        ->assertJsonPath('data.classement_general.1.eleve.nom_complet', 'Deuxième')
        ->assertJsonPath('data.classement_general.2.eleve.nom_complet', 'Incomplet')
        ->assertJsonPath('data.classement_general.2.rang', null)
        ->assertJsonPath('data.nombre_eleves', 3)
        ->assertJsonPath('data.classement_general.0.pourcentage', 85);

    $this->getJson("/api/v1/me/classes/{$scope['class']->id}/classements")
        ->assertOk()
        ->assertJsonPath('data.periode.code', 'P1')
        ->assertJsonPath('data.selection_automatique', true);

    $semester = ModelePeriode::query()->create(['tenant_id' => $scope['class']->tenant_id, 'modele_bulletin_id' => $model->id, 'code' => 'TOTAL_S1', 'nom' => 'Premier semestre', 'type_periode' => 'SEMESTRE', 'ordre' => 2, 'saisissable' => false, 'calculable' => true, 'actif' => true]);
    foreach (array_slice($students, 0, 2) as $index => $enrollment) {
        $bulletin = Bulletin::query()->where('inscription_id', $enrollment['id'])->firstOrFail();
        ResultatBulletin::query()->create(['tenant_id' => $scope['class']->tenant_id, 'bulletin_id' => $bulletin->id, 'periode_id' => $semester->id, 'modele_bulletin_id' => $model->id, 'version' => 1, 'engine_version' => 'test', 'configuration_hash' => str_repeat('a', 64), 'input_hash' => str_repeat('b', 64), 'result_hash' => str_repeat('c', 64), 'total_obtenu' => $index === 0 ? 120 : 150, 'total_maxima' => 200, 'pourcentage' => $index === 0 ? 60 : 75, 'payload' => []]);
    }
    $this->getJson("/api/v1/me/classes/{$scope['class']->id}/classements?periode_id={$semester->id}")
        ->assertOk()
        ->assertJsonPath('data.periode.nom', 'Premier semestre')
        ->assertJsonPath('data.classement_general.0.eleve.nom_complet', 'Premier')
        ->assertJsonPath('data.classement_general.0.pourcentage', 75);

    AttributionCours::query()->create(['tenant_id' => $scope['class']->tenant_id, 'classe_id' => $scope['class']->id, 'modele_bulletin_id' => $model->id, 'node_id' => $french->id, 'enseignant_id' => $scope['teacher']->id, 'actif' => true]);
    $assignment = AttributionCours::query()->firstOrFail();
    $this->getJson("/api/v1/me/attributions/{$assignment->id}/classements?periode_id={$period->id}")
        ->assertOk()
        ->assertJsonPath('data.attribution.cours', 'Français')
        ->assertJsonPath('data.classement.0.eleve.nom_complet', 'Incomplet')
        ->assertJsonPath('data.classement.0.rang', 1);
});

test('a titular creates an enrolled student and report card then reads edits and withdraws without deleting history', function () {
    $scope = myStudentScope();
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $this->withToken($scope['token']);
    $id = $this->postJson($url, ['eleves' => [['nom' => 'Mbuyi', 'prenom' => 'Alice', 'sexe' => 'F']]])
        ->assertCreated()->assertJsonPath('data.0.eleve.nom', 'Mbuyi')->json('data.0.id');
    expect(Bulletin::query()->where('inscription_id', $id)->count())->toBe(1);
    $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson("$url/$id")->assertOk()->assertJsonPath('data.eleve.prenom', 'Alice');
    $this->putJson("$url/$id", ['nom' => 'Mbuyi', 'prenom' => 'Alicia'])->assertOk()->assertJsonPath('data.eleve.prenom', 'Alicia');
    $this->deleteJson("$url/$id", ['motif' => 'Départ de la classe'])->assertOk()->assertJsonPath('data.statut', 'ABANDON');
    expect(Eleve::query()->count())->toBe(1)->and(Bulletin::query()->count())->toBe(1);
    $this->putJson("$url/$id", ['nom' => 'Autre'])->assertConflict();
});

test('the student list honours the requested page size and falls back to 25 for unsupported values', function () {
    $scope = myStudentScope();
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $this->withToken($scope['token']);
    $this->postJson($url, ['eleves' => array_map(fn (int $index) => ['nom' => "Eleve{$index}"], range(1, 60))])->assertCreated();

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.total', 60)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonCount(25, 'data');

    $this->getJson("$url?per_page=50")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(50, 'data');

    $this->getJson("$url?per_page=100")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonCount(60, 'data');

    $this->getJson("$url?per_page=30")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonCount(25, 'data');

    $this->getJson("$url?per_page=500")->assertUnprocessable();

    $this->getJson("$url?page=2")
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(25, 'data');

    $links = $this->getJson("$url?per_page=50")->assertOk()->json('meta.links');
    expect(collect($links)->pluck('url')->filter()->every(fn (string $link) => str_contains($link, 'per_page=50')))->toBeTrue();
});

test('student operations require the actual titular and a teaching role for every endpoint', function (string $defect) {
    $scope = myStudentScope();
    if ($defect === 'not-titular') {
        $scope['class']->update(['titulaire_id' => null]);
    } elseif ($defect === 'no-role') {
        $scope['teacher']->syncRoles([]);
    } else {
        $scope['teacher']->update(['actif' => false]);
    }
    $this->withToken($scope['token']);
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $this->getJson($url)->assertForbidden();
    $this->getJson("$url/1")->assertForbidden();
    $this->postJson($url, ['eleves' => [['nom' => 'Interdit']]])->assertForbidden();
    $this->putJson("$url/1", ['nom' => 'Interdit'])->assertForbidden();
    $this->deleteJson("$url/1", ['motif' => 'Interdit'])->assertForbidden();
    $this->getJson("$url/excel/modele")->assertForbidden();
    $this->postJson("$url/excel/apercu")->assertForbidden();
    expect(Eleve::query()->count())->toBe(0);
})->with(['not-titular', 'no-role', 'inactive']);

test('a titular cannot access another school or forge a student enrollment from another class', function () {
    $scope = myStudentScope();
    $other = $scope['class']->replicate(['public_id']);
    $other->nom = 'Autre classe';
    $other->save();
    $student = Eleve::query()->create(['tenant_id' => $other->tenant_id, 'etablissement_id' => $other->etablissement_id, 'nom' => 'Autre élève']);
    $enrollment = Inscription::query()->create(['tenant_id' => $other->tenant_id, 'classe_id' => $other->id, 'annee_scolaire_id' => $other->annee_scolaire_id, 'eleve_id' => $student->id, 'statut' => 'ACTIF']);
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $this->withToken($scope['token']);
    $this->getJson("$url/$enrollment->id")->assertNotFound();
    $this->putJson("$url/$enrollment->id", ['nom' => 'Volé'])->assertNotFound();
    $this->deleteJson("$url/$enrollment->id", ['motif' => 'Volé'])->assertNotFound();
    $school = Etablissement::query()->create(['tenant_id' => $other->tenant_id, 'nom' => 'Autre école', 'actif' => true]);
    $other->update(['etablissement_id' => $school->id]);
    $this->getJson("/api/v1/me/classes/$other->id/eleves")->assertNotFound();
});

test('student batch is atomic duplicate safe and rejects client controlled context', function () {
    $scope = myStudentScope();
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $this->withToken($scope['token']);
    $this->postJson($url, ['eleves' => [['nom' => 'Même'], ['nom' => 'Même']]])->assertUnprocessable();
    expect(Eleve::query()->count())->toBe(0)->and(Inscription::query()->count())->toBe(0)->and(Bulletin::query()->count())->toBe(0);
    $this->postJson($url, ['eleves' => [['nom' => 'Valide', 'classe_id' => 1]]])->assertUnprocessable();
    $this->postJson($url, ['eleves' => [['nom' => 'Valide', 'actif' => false]]])->assertUnprocessable();
    $this->postJson($url, ['eleves' => [['nom' => 'Valide', 'sexe' => 'X']]])->assertUnprocessable();
    $scope['year']->update(['cloturee' => true]);
    $this->postJson($url, ['eleves' => [['nom' => 'Valide']]])->assertConflict();
    expect(Eleve::query()->count())->toBe(0);
});

test('Excel template preview is read only and confirmation enrolls all students', function () {
    $scope = myStudentScope();
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $this->withToken($scope['token']);
    $this->getJson("$url/excel/modele")->assertOk()->assertDownload('modele-eleves.xlsx');
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->getSheet(0)->fromArray(['Mbuyi', 'Kalala', 'Alice', 'F', '2012-01-20'], null, 'A2');
    $book->getSheet(0)->fromArray(['Ilunga', null, 'Jean', 'M'], null, 'A3');
    $preview = $this->post("$url/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(2, 'data.lignes')->assertJsonCount(0, 'data.erreurs')->json('data');
    expect(Eleve::query()->count())->toBe(0);
    $this->postJson($url, ['eleves' => array_column($preview['lignes'], 'eleve')])->assertCreated()->assertJsonCount(2, 'data');
    expect(Eleve::query()->count())->toBe(2)->and(Bulletin::query()->count())->toBe(2);
    $this->postJson($url, ['eleves' => array_column($preview['lignes'], 'eleve')])->assertUnprocessable();
    expect(Eleve::query()->count())->toBe(2);
});

test('the template ships two example pupils that the preview ignores until they are replaced', function () {
    $scope = myStudentScope();
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    expect($book->getSheet(0)->getCell('A2')->getValue())->toStartWith('EXEMPLE')
        ->and($book->getSheet(0)->getCell('A3')->getValue())->toStartWith('EXEMPLE')
        ->and($book->getSheet(0)->getCell('E2')->getValue())->toBe('2012-04-17')
        ->and($book->getSheet(0)->getCell('G2')->getValue())->toBeNull();
    $this->withToken($scope['token']);
    $this->post("$url/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(0, 'data.lignes')->assertJsonCount(1, 'data.erreurs')
        ->assertJsonPath('data.erreurs.0.message', 'Seules les lignes d’exemple sont présentes : remplacez-les par vos élèves, puis vérifiez à nouveau.');
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->getSheet(0)->fromArray(['Vraie', 'Learner', 'Trésor', 'M', '2012-05-06'], null, 'A4');
    $this->post("$url/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(1, 'data.lignes')->assertJsonCount(0, 'data.erreurs')
        ->assertJsonPath('data.lignes.0.ligne', 4);
    expect(Eleve::query()->count())->toBe(0);
});

test('Excel reports invalid rows formulas and duplicates without writing', function () {
    $scope = myStudentScope();
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    foreach ([2, 3] as $line) {
        foreach (range('A', 'K') as $column) {
            $book->getSheet(0)->setCellValueExplicit("$column$line", null, DataType::TYPE_NULL);
        }
    }
    $book->getSheet(0)->fromArray(['Mbuyi', null, null, 'X'], null, 'A2');
    $book->getSheet(0)->fromArray(['Mbuyi'], null, 'A3');
    $book->getSheet(0)->fromArray(['=1+1'], null, 'A4');
    $this->withToken($scope['token'])->post("/api/v1/me/classes/{$scope['class']->id}/eleves/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(3, 'data.erreurs');
    expect(Eleve::query()->count())->toBe(0);
});

test('Excel refuses wrong class headers empty and oversized workbooks', function (string $defect) {
    $scope = myStudentScope();
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->getSheet(0)->setCellValue('A2', 'Mbuyi');
    if ($defect === 'class') {
        $book->getSheet(1)->setCellValue('A1', 'wrong');
    } elseif ($defect === 'headers') {
        $book->getSheet(0)->setCellValue('A1', 'wrong');
    } else {
        for ($line = 2; $line <= 202; $line++) {
            $book->getSheet(0)->setCellValue("A$line", "Élève $line");
        }
    }
    $this->withToken($scope['token'])->post("/api/v1/me/classes/{$scope['class']->id}/eleves/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])->assertUnprocessable();
    expect(Eleve::query()->count())->toBe(0);
})->with(['class', 'headers', 'size']);

test('Excel accepts a simple workbook without the hidden class sheet', function () {
    $scope = myStudentScope();
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->removeSheetByIndex(1);
    $book->getSheet(0)->setTitle('Feuil1');
    for ($index = 1; $index <= 5; $index++) {
        $book->getSheet(0)->fromArray(["Élève test $index", 'Exemple', 'Alice', 'F', '2012-01-20', 'Ville test', "TEST-$index", 'Parent test', 'Parent exemple', '+243000000000', 'Adresse de test'], null, 'A'.($index + 1));
    }
    $book->getSheet(0)->setCellValue('E1', "Date de\nnaissance (AAAA-MM-JJ)");
    $book->getSheet(0)->setCellValue('J1', "Téléphone du\nparent");
    $this->withToken($scope['token'])->post("/api/v1/me/classes/{$scope['class']->id}/eleves/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(5, 'data.lignes')->assertJsonCount(0, 'data.erreurs')
        ->assertJsonPath('data.lignes.0.eleve.nom', 'Élève test 1');
    expect(Eleve::query()->count())->toBe(0);
});

test('Excel ignores empty formatted cells outside the template and accepts reordered sheets', function () {
    $scope = myStudentScope();
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->getSheet(0)->setCellValue('A2', 'Mbuyi');
    $book->getSheet(0)->getStyle('Z1000')->getFont()->setBold(true);
    $book->getSheet(1)->getStyle('D300')->getFont()->setBold(true);
    $book->setIndexByName('Classe', 0);
    $this->withToken($scope['token'])->post("/api/v1/me/classes/{$scope['class']->id}/eleves/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(1, 'data.lignes')->assertJsonCount(0, 'data.erreurs');
});

test('Excel counts populated students rather than their physical row numbers', function () {
    $scope = myStudentScope();
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->getSheet(0)->setCellValue('A2', 'Mbuyi');
    $book->getSheet(0)->setCellValue('A300', 'Kanyinda');
    $this->withToken($scope['token'])->post("/api/v1/me/classes/{$scope['class']->id}/eleves/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonCount(2, 'data.lignes')->assertJsonPath('data.lignes.1.ligne', 300);
});

test('Excel never silently discards populated columns outside the expected fields', function () {
    $scope = myStudentScope();
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->getSheet(0)->setCellValue('A2', 'Mbuyi');
    $book->getSheet(0)->setCellValue('Z300', 'Donnée inattendue');
    $this->withToken($scope['token'])->post("/api/v1/me/classes/{$scope['class']->id}/eleves/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('fichier');
});

test('missing report card configuration rolls back the student and enrollment together', function () {
    $scope = myStudentScope();
    ModeleBulletin::query()->where('classe_id', $scope['class']->id)->update(['actif' => false]);
    $this->withToken($scope['token'])->postJson("/api/v1/me/classes/{$scope['class']->id}/eleves", ['eleves' => [['nom' => 'Sans modèle']]])->assertConflict();
    expect(Eleve::query()->count())->toBe(0)->and(Inscription::query()->count())->toBe(0);
});

test('student import initializes the class report card configuration once from its assigned global template', function () {
    $scope = myStudentScope();
    ModeleBulletin::query()->where('classe_id', $scope['class']->id)->delete();
    $templateId = $scope['class']->system_template_id;
    $node = SystemTemplateNode::query()->create(['template_id' => $templateId, 'type_node' => 'MATIERE', 'libelle' => 'Cours test', 'ordre' => 1]);
    $period = SystemTemplatePeriode::query()->create(['template_id' => $templateId, 'code' => 'P1', 'nom' => 'Période test', 'type_periode' => 'PERIODE', 'ordre' => 1]);
    SystemTemplateMaximum::query()->create(['template_id' => $templateId, 'node_id' => $node->id, 'periode_id' => $period->id, 'maxima' => 20, 'coefficient' => 1]);
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->getSheet(0)->setCellValue('A2', 'Premier test')->setCellValue('A3', 'Deuxième test');
    $this->withToken($scope['token']);
    $report = $this->post("$url/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])->assertOk()->json('data');
    expect(ModeleBulletin::query()->where('classe_id', $scope['class']->id)->count())->toBe(0);
    $this->postJson($url, ['eleves' => array_column($report['lignes'], 'eleve')])
        ->assertCreated()->assertJsonCount(2, 'data');
    $model = ModeleBulletin::query()->where('classe_id', $scope['class']->id)->sole();
    expect($model->actif)->toBeTrue()->and($model->system_template_id)->toBe($scope['class']->system_template_id)
        ->and(Bulletin::query()->where('modele_bulletin_id', $model->id)->count())->toBe(2)
        ->and($model->nodes()->sole()->system_node_id)->toBe($node->id)
        ->and($model->periodes()->sole()->system_periode_id)->toBe($period->id)
        ->and((float) $model->maxima()->sole()->maxima)->toBe(20.0);
});

test('student import does not initialize a disabled global template', function () {
    $scope = myStudentScope();
    ModeleBulletin::query()->where('classe_id', $scope['class']->id)->delete();
    $templateId = $scope['class']->system_template_id;
    $scope['class']->systemTemplate()->update(['actif' => false]);
    $this->withToken($scope['token'])->postJson("/api/v1/me/classes/{$scope['class']->id}/eleves", ['eleves' => [['nom' => 'Élève test']]])->assertConflict();
    expect($scope['class']->refresh()->system_template_id)->toBe($templateId)
        ->and(ModeleBulletin::query()->where('classe_id', $scope['class']->id)->count())->toBe(0)
        ->and(Eleve::query()->count())->toBe(0);
});

test('initializing report card configuration is rolled back with a failed student batch', function () {
    $scope = myStudentScope();
    ModeleBulletin::query()->where('classe_id', $scope['class']->id)->delete();
    $this->withToken($scope['token'])->postJson("/api/v1/me/classes/{$scope['class']->id}/eleves", ['eleves' => [['nom' => 'Doublon test'], ['nom' => 'Doublon test']]])->assertUnprocessable();
    expect(ModeleBulletin::query()->where('classe_id', $scope['class']->id)->count())->toBe(0)
        ->and(Eleve::query()->count())->toBe(0)->and(Bulletin::query()->count())->toBe(0);
});

test('an Excel preview grants no write authority after titular replacement', function () {
    $scope = myStudentScope();
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $book->getSheet(0)->setCellValue('A2', 'Nouveau');
    $this->withToken($scope['token']);
    $report = $this->post("$url/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])->assertOk()->json('data');
    $scope['class']->update(['titulaire_id' => null]);
    $this->postJson($url, ['eleves' => array_column($report['lignes'], 'eleve')])->assertForbidden();
    expect(Eleve::query()->count())->toBe(0);
});

test('closed school year blocks editing withdrawing and previewing existing student data', function () {
    $scope = myStudentScope();
    $url = "/api/v1/me/classes/{$scope['class']->id}/eleves";
    $this->withToken($scope['token']);
    $id = $this->postJson($url, ['eleves' => [['nom' => 'Mbuyi']]])->assertCreated()->json('data.0.id');
    $scope['year']->update(['cloturee' => true]);
    $this->putJson("$url/$id", ['nom' => 'Autre'])->assertConflict();
    $this->deleteJson("$url/$id", ['motif' => 'Départ'])->assertConflict();
    $book = app(StudentSpreadsheetService::class)->template($scope['class']);
    $this->post("$url/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])->assertConflict();
    expect(Inscription::query()->findOrFail($id)->statut)->toBe('ACTIF')->and(Eleve::query()->firstOrFail()->nom)->toBe('Mbuyi');
});

test('a titular import binds every student to the class they hold and refuses a class they only teach', function () {
    $scope = myStudentScope();
    $titulaireClass = $scope['class'];
    $teachingClass = $titulaireClass->replicate(['public_id']);
    $teachingClass->nom = '4ème B';
    $teachingClass->titulaire_id = null;
    $teachingClass->save();
    $teachingUrl = "/api/v1/me/classes/{$teachingClass->id}/eleves";
    $titulaireUrl = "/api/v1/me/classes/{$titulaireClass->id}/eleves";
    $this->withToken($scope['token']);

    $this->postJson($teachingUrl, ['eleves' => [['nom' => 'Élève interdit']]])->assertForbidden();
    $this->getJson($teachingUrl)->assertForbidden();
    $this->getJson("$teachingUrl/excel/modele")->assertForbidden();
    $book = app(StudentSpreadsheetService::class)->template($teachingClass);
    $book->getSheet(0)->setCellValue('A2', 'Élève interdit');
    $this->post("$teachingUrl/excel/apercu", ['fichier' => myStudentWorkbook($book)], ['Accept' => 'application/json'])->assertForbidden();
    expect(Eleve::query()->count())->toBe(0)->and(Inscription::query()->count())->toBe(0);

    $this->postJson($titulaireUrl, ['eleves' => [['nom' => 'Mbuyi'], ['nom' => 'Kalala']]])
        ->assertCreated()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.classe_id', $titulaireClass->id)
        ->assertJsonPath('data.1.classe_id', $titulaireClass->id);

    expect(Inscription::query()->pluck('classe_id')->unique()->all())->toBe([$titulaireClass->id])
        ->and(Inscription::query()->where('classe_id', $titulaireClass->id)->count())->toBe(2);
});

test('an existing school student is never overwritten or re-enrolled by an import', function () {
    $scope = myStudentScope();
    $student = Eleve::query()->create(['tenant_id' => $scope['class']->tenant_id, 'etablissement_id' => $scope['class']->etablissement_id, 'nom' => 'Déjà connu', 'telephone_parent' => 'original']);
    $this->withToken($scope['token'])->postJson("/api/v1/me/classes/{$scope['class']->id}/eleves", ['eleves' => [['nom' => 'Déjà connu', 'telephone_parent' => 'remplacé']]])->assertUnprocessable();
    expect($student->refresh()->telephone_parent)->toBe('original')->and(Inscription::query()->count())->toBe(0);
});
