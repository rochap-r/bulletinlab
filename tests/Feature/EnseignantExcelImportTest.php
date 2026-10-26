<?php

use App\Models\Etablissement;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/**
 * @param  list<list<mixed>>  $rows  Première ligne = en-tête.
 */
function enseignantsXlsxUpload(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'enseignants-xlsx-');

    if ($temporaryPath === false) {
        throw new RuntimeException('Unable to create a temporary XLSX test file.');
    }

    try {
        (new Xlsx($spreadsheet))->save($temporaryPath);
        $contents = file_get_contents($temporaryPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the temporary XLSX test file.');
        }

        return UploadedFile::fake()->createWithContent('enseignants.xlsx', $contents);
    } finally {
        $spreadsheet->disconnectWorksheets();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}

function enseignantsXlsxHeaders(): array
{
    return ['nom', 'postnom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance', 'email', 'telephone', 'adresse', 'matricule', 'qualification', 'specialite', 'date_embauche'];
}

test('un gestionnaire importe des enseignants depuis un fichier Excel', function (): void {
    $tenant = createTenantForDirectUsers('enseignants-excel');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École C', 'type_etablissement' => 'PRIMAIRE']);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    globalRoleForDirectUsers('ENSEIGNANT');

    $file = enseignantsXlsxUpload([
        enseignantsXlsxHeaders(),
        ['Kabasele', '', 'Jean', 'M', '', '', '', '+243811000601', '', 'ENS-101', 'D6 Pédagogie', 'Français', ''],
        // Téléphone stocké en nombre par Excel : normalisé en +243...
        ['Mbuyi', '', 'Aline', 'F', '', '', '', 243811000602, '', 'ENS-102', '', '', ''],
        ['Invalide', '', '', '', '', '', '', 'mauvais-numero', '', '', '', '', ''],
    ]);

    $response = $this->withToken(tokenForDirectUsers($manager))->post('/api/v1/utilisateurs/import-enseignants-excel', [
        'fichier' => $file,
    ])->assertCreated()->json('data');

    expect($response['crees_count'])->toBe(2)
        ->and($response['erreurs_count'])->toBe(1)
        ->and($response['total'])->toBe(3)
        ->and($response['erreurs'][0]['ligne'])->toBe(4)
        ->and($response['crees'][0]['mot_de_passe_temporaire'])->toBeString()
        ->and(strlen($response['crees'][0]['mot_de_passe_temporaire']))->toBe(16)
        ->and($response['crees'][1]['mot_de_passe_temporaire'])->toBeString()
        ->and(strlen($response['crees'][1]['mot_de_passe_temporaire']))->toBe(16)
        ->and($response['crees'][0]['mot_de_passe_temporaire'])->not->toBe($response['crees'][1]['mot_de_passe_temporaire']);

    $this->assertDatabaseHas('utilisateurs', ['tenant_id' => $tenant->id, 'matricule' => 'ENS-101', 'telephone_normalise' => '+243811000601']);
    $this->assertDatabaseHas('utilisateurs', ['tenant_id' => $tenant->id, 'matricule' => 'ENS-102', 'telephone_normalise' => '+243811000602']);

    $created = Utilisateur::query()->where('tenant_id', $tenant->id)->where('matricule', 'ENS-101')->firstOrFail();
    expect($created->tenantRoleCodes())->toBe(['ENSEIGNANT']);
});

test('un gestionnaire importe des enseignants avec des dates dans différents formats', function (): void {
    $tenant = createTenantForDirectUsers('enseignants-excel-dates');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École Dates', 'type_etablissement' => 'PRIMAIRE']);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    globalRoleForDirectUsers('ENSEIGNANT');

    $dateEmbaucheSerial = 44927;
    $dateEmbaucheExpected = date('Y-m-d', PhpSpreadsheetDate::excelToTimestamp($dateEmbaucheSerial));

    $file = enseignantsXlsxUpload([
        enseignantsXlsxHeaders(),
        // date_naissance au format Y-m-d (texte) : déjà valide.
        ['Ilunga', 'Mbuyi', 'Sara', 'F', '1990-05-14', 'Kinshasa', 'sara@example.com', '+243811000701', '', 'ENS-301', 'Licence', 'Maths', ''],
        // date_naissance au format d/m/Y (texte) et date_embauche en numéro de série Excel.
        ['Kabasele', '', 'Jean', 'M', '03/11/1992', 'Lubumbashi', 'jean@example.com', '+243811000702', '', 'ENS-302', 'D6', 'Français', $dateEmbaucheSerial],
    ]);

    $response = $this->withToken(tokenForDirectUsers($manager))->post('/api/v1/utilisateurs/import-enseignants-excel', [
        'fichier' => $file,
    ])->assertCreated()->json('data');

    expect($response['crees_count'])->toBe(2)
        ->and($response['erreurs_count'])->toBe(0);

    $sara = Utilisateur::query()->where('tenant_id', $tenant->id)->where('matricule', 'ENS-301')->firstOrFail();
    expect($sara->date_naissance?->format('Y-m-d'))->toBe('1990-05-14');

    $jean = Utilisateur::query()->where('tenant_id', $tenant->id)->where('matricule', 'ENS-302')->firstOrFail();
    expect($jean->date_naissance?->format('Y-m-d'))->toBe('1992-11-03');
    expect($jean->date_embauche?->format('Y-m-d'))->toBe($dateEmbaucheExpected);
});

test('l’import Excel ignore la colonne rôle historique et rejette un en-tête invalide', function (): void {
    $tenant = createTenantForDirectUsers('enseignants-excel-role');
    $school = Etablissement::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'nom' => 'École D', 'type_etablissement' => 'PRIMAIRE']);
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE', $school);
    globalRoleForDirectUsers('ENSEIGNANT');

    $withLegacyRole = enseignantsXlsxUpload([
        [...enseignantsXlsxHeaders(), 'role'],
        ['Kabasele', '', 'Jean', 'M', '', '', '', '+243811000701', '', 'ENS-201', '', '', '', 'TITULAIRE'],
    ]);

    $this->withToken(tokenForDirectUsers($manager))->post('/api/v1/utilisateurs/import-enseignants-excel', [
        'fichier' => $withLegacyRole,
    ])->assertCreated()->assertJsonPath('data.crees_count', 1);

    $created = Utilisateur::query()->where('tenant_id', $tenant->id)->where('matricule', 'ENS-201')->firstOrFail();
    expect($created->tenantRoleCodes())->toBe(['ENSEIGNANT']);

    $badHeader = enseignantsXlsxUpload([
        ['nom', 'telephone_bis'],
        ['Kabasele', '+243811000702'],
    ]);

    $this->withToken(tokenForDirectUsers($manager))->post('/api/v1/utilisateurs/import-enseignants-excel', [
        'fichier' => $badHeader,
    ])->assertUnprocessable()->assertJsonValidationErrors(['fichier']);

    $notExcel = UploadedFile::fake()->createWithContent('enseignants.txt', 'nom,telephone');
    $this->withToken(tokenForDirectUsers($manager))->post('/api/v1/utilisateurs/import-enseignants-excel', [
        'fichier' => $notExcel,
    ])->assertUnprocessable()->assertJsonValidationErrors(['fichier']);
});

test('le modèle Excel d’import des enseignants se télécharge', function (): void {
    $tenant = createTenantForDirectUsers('enseignants-excel-modele');
    $manager = userForDirectUsers($tenant, 'GESTIONNAIRE_ECOLE');
    globalRoleForDirectUsers('ENSEIGNANT');

    $response = $this->withToken(tokenForDirectUsers($manager))->get('/api/v1/utilisateurs/import-enseignants/modele');
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))->toContain('modele-import-enseignants.xlsx');

    ob_start();
    $response->baseResponse->sendContent();
    $contents = (string) ob_get_clean();

    $temporaryPath = tempnam(sys_get_temp_dir(), 'enseignants-modele-');
    expect($temporaryPath)->not->toBeFalse();
    file_put_contents($temporaryPath, $contents);

    try {
        $loaded = IOFactory::load($temporaryPath);
        $sheet = $loaded->getActiveSheet()->toArray();
        expect(array_map(fn (mixed $cell): string => trim((string) $cell), $sheet[0]))->toBe(enseignantsXlsxHeaders());
        expect(count($sheet))->toBeGreaterThanOrEqual(3);

        // Téléphone stocké en texte, pas en nombre/formule.
        $activeSheet = $loaded->getActiveSheet();
        expect($activeSheet->getCell('H2')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and((string) $activeSheet->getCell('H2')->getValue())->toBe('+243811000501')
            ->and($activeSheet->getCell('H3')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($activeSheet->getStyle('H2')->getNumberFormat()->getFormatCode())->toBe(NumberFormat::FORMAT_TEXT);
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});
