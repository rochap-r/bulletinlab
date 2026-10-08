<?php

use App\Domains\Bulletin\DTOs\BulletinCalculationInput;
use App\Domains\Bulletin\DTOs\BulletinCalculationSlot;
use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Domains\Bulletin\Services\DeterministicBulletinCalculationService;
use App\Domains\Bulletin\Services\PublishBulletinCalculationService;
use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Models\AnneeScolaire;
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
use App\Models\Publication;
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
    $this->mysqlPdo = connectToMysql();
});

afterEach(function () {
    if ($this->mysqlPdo instanceof PDO) {
        $this->mysqlPdo = null;
    }

    if (isset($this->mysqlSeeds)) {
        cleanupMysqlData($this->mysqlSeeds);
    }
});

it('delays a period lock until the note entry committing the class row finishes', function () {
    if (! $this->mysqlPdo) {
        $this->markTestSkipped('MySQL is not available for concurrency testing.');
    }

    $ids = seedMysqlData();

    $recorderPdo = connectToMysql();
    $recorderPdo->beginTransaction();

    $statement = $recorderPdo->prepare(
        'SELECT * FROM classes WHERE id = :id FOR UPDATE',
    );
    $statement->execute(['id' => $ids['classe']]);

    $lockerPdo = connectToMysql();
    $lockerPdo->exec('SET SESSION innodb_lock_wait_timeout = 2');

    expect(isLockBlocked(
        fn () => lockPeriodFromPdo($lockerPdo, $ids),
    ))->toBeTrue(
        'A period lock must wait for the class row held by the note entry.',
    );

    $recorderPdo->commit();
    $lockerPdo = null;

    $freshPdo = connectToMysql();
    lockPeriodFromPdo($freshPdo, $ids);

    expect(periodLockStateFromPdo($freshPdo, $ids))->toBe(true);

    $freshPdo = null;
    $recorderPdo = null;
});

it('rejects note recording once a concurrent period lock has won', function () {
    if (! $this->mysqlPdo) {
        $this->markTestSkipped('MySQL is not available for concurrency testing.');
    }

    $ids = seedMysqlData();

    $lockerPdo = connectToMysql();
    lockPeriodFromPdo($lockerPdo, $ids);

    $recorderPdo = connectToMysql();
    $recorderPdo->beginTransaction();

    expect(isPeriodLockedFromPdo($recorderPdo, $ids))->toBeTrue(
        'Note recording must observe the locked period before writing.',
    );

    $recorderPdo->rollBack();
    $lockerPdo = null;
    $recorderPdo = null;
});

it('blocks a publication while a calculation preview holds the bulletin row', function () {
    if (! $this->mysqlPdo) {
        $this->markTestSkipped('MySQL is not available for concurrency testing.');
    }

    $ids = seedMysqlData();

    $previewerPdo = connectToMysql();
    $previewerPdo->beginTransaction();

    $statement = $previewerPdo->prepare(
        'SELECT * FROM bulletins WHERE id = :id FOR UPDATE',
    );
    $statement->execute(['id' => $ids['bulletin']]);

    $publisherPdo = connectToMysql();
    $publisherPdo->exec('SET SESSION innodb_lock_wait_timeout = 2');

    expect(isLockBlocked(
        fn () => publishFromPdo($publisherPdo, $ids),
    ))->toBeTrue(
        'A concurrent publication must wait for the bulletin row held by the preview.',
    );

    $previewerPdo->rollBack();
    $publisherPdo = null;

    $freshPdo = connectToMysql();
    publishFromPdo($freshPdo, $ids);

    expect(isPublicationPresentFromPdo($freshPdo, $ids))->toBeTrue();

    $freshPdo = null;
    $previewerPdo = null;
});

it('records a single publication for concurrent idempotent writes', function () {
    if (! $this->mysqlPdo) {
        $this->markTestSkipped('MySQL is not available for concurrency testing.');
    }

    $ids = seedMysqlData();

    $firstPdo = connectToMysql();
    $secondPdo = connectToMysql();

    publishFromPdo($firstPdo, $ids);
    publishFromPdo($secondPdo, $ids);

    $readerPdo = connectToMysql();
    $statement = $readerPdo->prepare(
        'SELECT COUNT(*) FROM publications
         WHERE bulletin_id = :bulletinId AND periode_id = :periodeId
           AND statut = \'PUBLIEE\'',
    );
    $statement->execute([
        'bulletinId' => $ids['bulletin'],
        'periodeId' => $ids['periode'],
    ]);

    expect((int) $statement->fetchColumn())->toBe(1);

    $readerPdo = null;
    $firstPdo = null;
    $secondPdo = null;
});

it('reads the persisted result after the configuration evolves', function () {
    prepareDatabase();

    $this->bulletin1->update(['verrouille' => false]);

    $service = app(PublishBulletinCalculationService::class);
    $first = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $this->maximum->update(['maxima' => 25.00]);

    $second = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $snapshots = ResultatBulletin::query()
        ->where('bulletin_id', $this->bulletin1->id)
        ->where('periode_id', $this->periode->id)
        ->orderBy('version')
        ->get();

    expect($snapshots)->toHaveCount(2);
    expect($snapshots[0]->configuration_hash)->not->toBe($snapshots[1]->configuration_hash);
    expect($snapshots[0]->result_hash)->not->toBe($snapshots[1]->result_hash);
    expect($snapshots[0]->total_obtenu)->toBe('16.00');
    expect($snapshots[0]->total_maxima)->toBe('20.00');
    expect($snapshots[0]->pourcentage)->toBe('80.00');
    expect($snapshots[1]->total_obtenu)->toBe('16.00');
    expect($snapshots[1]->total_maxima)->toBe('25.00');
    expect($snapshots[1]->pourcentage)->toBe('64.00');

    $publication = Publication::query()
        ->where('bulletin_id', $this->bulletin1->id)
        ->where('periode_id', $this->periode->id)
        ->latest('id')
        ->first();

    expect($publication->resultat_bulletin_id)->toBe($snapshots[1]->id);

    expect($first->configurationHash)->not->toBe($second->configurationHash);
    expect($first->resultHash)->not->toBe($second->resultHash);
});

it('keeps the archived snapshot readable when the evolved configuration invalidates the notes', function () {
    prepareDatabase();

    $this->bulletin1->update(['verrouille' => false]);

    $service = app(PublishBulletinCalculationService::class);
    $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $this->maximum->update(['maxima' => 10.00]);

    expect(
        fn () => $service->publish(
            $this->bulletin1,
            $this->classe,
            $this->periode,
            $this->user,
        ),
    )->toThrow(BulletinException::class);

    $snapshot = ResultatBulletin::query()
        ->where('bulletin_id', $this->bulletin1->id)
        ->where('periode_id', $this->periode->id)
        ->where('version', 1)
        ->first();

    expect($snapshot)->not->toBeNull();
    expect($snapshot->total_obtenu)->toBe('16.00');
    expect($snapshot->total_maxima)->toBe('20.00');
    expect($snapshot->pourcentage)->toBe('80.00');
    expect($snapshot->payload['result']['total_obtenu'])->toBe('16.00');
    expect($snapshot->payload['result']['total_maxima'])->toBe('20.00');
    expect($snapshot->payload['result']['pourcentage'])->toBe('80.00');
});

it('reproduces the historical result from the published snapshot payload', function () {
    prepareDatabase();

    $this->bulletin1->update(['verrouille' => false]);

    $service = app(PublishBulletinCalculationService::class);
    $first = $service->publish(
        $this->bulletin1,
        $this->classe,
        $this->periode,
        $this->user,
    );

    $this->maximum->update(['maxima' => 25.00]);

    $snapshot = ResultatBulletin::query()
        ->where('bulletin_id', $this->bulletin1->id)
        ->where('periode_id', $this->periode->id)
        ->where('version', 1)
        ->first();

    $input = $snapshot->payload['input'];

    $slots = array_map(
        fn (array $slot): BulletinCalculationSlot => new BulletinCalculationSlot(
            key: $slot['key'],
            nodeId: $slot['node_id'],
            nodeType: $slot['node_type'],
            maximumMinorUnits: $slot['maximum_minor_units'],
            coefficientHundredths: $slot['coefficient_hundredths'],
            status: $slot['status'] !== null
                ? NoteResultStatus::from($slot['status'])
                : null,
            valueMinorUnits: $slot['value_minor_units'],
        ),
        $input['slots'],
    );

    $replayedInput = new BulletinCalculationInput(
        bulletinId: $input['bulletin_id'],
        periodId: $input['periode_id'],
        configurationHash: $input['configuration_hash'],
        periodActive: $input['periode_active'],
        periodCalculable: $input['periode_calculable'],
        periodLocked: true,
        configurationLocked: true,
        slots: $slots,
    );

    $replayed = (new DeterministicBulletinCalculationService)->calculate($replayedInput);

    expect($replayed->resultHash)->toBe($first->resultHash);
});

function connectToMysql(): ?PDO
{
    $config = mysqlConnectionConfig();

    if (empty($config['host']) || empty($config['database'])) {
        return null;
    }

    try {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database'],
            ),
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        );
    } catch (PDOException) {
        return null;
    }
}

/**
 * @return array<string, mixed>
 */
function mysqlConnectionConfig(): array
{
    $config = config('database.connections.mysql');

    if (! is_array($config) || empty($config['host'])) {
        return [];
    }

    $database = (string) ($config['database'] ?? '');

    if ($database === '' || $database === ':memory:' || $database === 'laravel') {
        $database = readDotEnvValue('DB_DATABASE') ?: $database;
        $config['database'] = $database;
    }

    return $config;
}

function readDotEnvValue(string $key): ?string
{
    $path = base_path('.env');

    if (! is_file($path)) {
        return null;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);

        if (trim($name) !== $key) {
            continue;
        }

        $value = trim($value);

        if (preg_match('/^"(.*)"$/', $value, $matches) === 1
            || preg_match("/^'(.*)'$/", $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    return null;
}

function seedMysqlData(): array
{
    $pdo = connectToMysql();
    $now = now()->toDateTimeString();
    $prefix = 'CONC_'.strtoupper(substr(uniqid(), -6));
    $publicId = static fn (): string => (string) Str::ulid();

    $pdo->exec(
        "INSERT INTO ref_cycles (code, nom, ordre, created_at, updated_at)
         VALUES ('{$prefix}', 'Cycle {$prefix}', 1, '$now', '$now')",
    );
    $cycleId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO ref_degres (cycle_id, code, nom, ordre, created_at, updated_at)
         VALUES ($cycleId, '{$prefix}', 'Degré {$prefix}', 1, '$now', '$now')",
    );
    $degreId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO ref_niveaux (cycle_id, degre_id, code, nom, numero, ordre, created_at, updated_at)
         VALUES ($cycleId, $degreId, '{$prefix}', 'Niveau {$prefix}', 1, 1, '$now', '$now')",
    );
    $niveauId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO system_layouts_bulletin (code, nom, vue_blade, actif, created_at, updated_at)
         VALUES ('{$prefix}', 'Layout {$prefix}', 'test', 1, '$now', '$now')",
    );
    $layoutId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO system_templates_bulletin
            (code, nom, cycle_id, degre_id, layout_id, type_decoupage, total_general_maxima, actif, created_at, updated_at)
         VALUES ('{$prefix}', 'Template {$prefix}', $cycleId, $degreId, $layoutId, 'TRIMESTRIEL', 20.00, 1, '$now', '$now')",
    );
    $templateId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO system_template_periodes
            (template_id, code, nom, type_periode, ordre, saisissable, calculable, actif, created_at, updated_at)
         VALUES ($templateId, 'T1', 'Trimestre 1', 'TRIMESTRE', 1, 1, 1, 1, '$now', '$now')",
    );

    $pdo->exec(
        "INSERT INTO system_template_nodes
            (template_id, type_node, libelle, ordre, obligatoire, created_at, updated_at)
         VALUES ($templateId, 'MATIERE', 'Mathématiques', 1, 1, '$now', '$now')",
    );

    $pdo->exec(
        "INSERT INTO tenants (uuid, nom_espace, slug, type_tenant, statut, created_at, updated_at)
         VALUES ('".Str::uuid()."', 'Tenant {$prefix}', 'tenant-{$prefix}', 'ECOLE', 'ACTIF', '$now', '$now')",
    );
    $tenantId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO etablissements
            (tenant_id, nom, code_ministere, province, ville, public_id, created_at, updated_at)
         VALUES ($tenantId, 'École {$prefix}', '{$prefix}', 'Kinshasa', 'Kinshasa', '{$publicId()}', '$now', '$now')",
    );
    $etablissementId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO utilisateurs
            (tenant_id, etablissement_id, nom, postnom, prenom, telephone, email, mot_de_passe, actif, public_id, created_at, updated_at)
         VALUES ($tenantId, $etablissementId, 'Admin', '{$prefix}', 'User', '+24300000000', 'admin-{$prefix}@test.com',
                 '".Hash::make('password123')."', 1, '{$publicId()}', '$now', '$now')",
    );
    $userId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO roles (tenant_id, code, nom, system_role, created_at, updated_at)
         VALUES ($tenantId, 'ADMIN', 'Administrateur', 0, '$now', '$now')",
    );
    $roleId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO utilisateur_roles (utilisateur_id, role_id) VALUES ($userId, $roleId)",
    );

    $pdo->exec(
        "INSERT INTO calendriers_scolaires
            (code, libelle, pays, date_debut, date_fin, officiel, actif, created_at, updated_at)
         VALUES ('{$prefix}', 'Calendrier {$prefix}', 'RDC', '2026-09-01', '2027-07-31', 1, 1, '$now', '$now')",
    );
    $calendrierId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO annees_scolaires
            (calendrier_scolaire_id, libelle, date_debut, date_fin, active, created_at, updated_at)
         VALUES ($calendrierId, '2026-2027', '2026-09-01', '2027-07-31', 1, '$now', '$now')",
    );
    $anneeId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO classes
            (tenant_id, etablissement_id, annee_scolaire_id, cycle_id, degre_id, niveau_id, system_template_id, nom, active, public_id, created_at, updated_at)
         VALUES ($tenantId, $etablissementId, $anneeId, $cycleId, $degreId, $niveauId, $templateId, 'Classe {$prefix}', 1, '{$publicId()}', '$now', '$now')",
    );
    $classeId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO modeles_bulletin
            (tenant_id, classe_id, annee_scolaire_id, system_template_id, layout_id, nom, type_decoupage, total_general_maxima, verrouille, actif, public_id, created_at, updated_at)
         VALUES ($tenantId, $classeId, $anneeId, $templateId, $layoutId, 'Modèle {$prefix}', 'TRIMESTRIEL', 20.00, 0, 1, '{$publicId()}', '$now', '$now')",
    );
    $modeleId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO modele_periodes
            (tenant_id, modele_bulletin_id, system_periode_id, code, nom, type_periode, ordre, saisissable, calculable, actif, created_at, updated_at)
         VALUES ($tenantId, $modeleId, NULL, 'T1', 'Trimestre 1', 'TRIMESTRE', 1, 1, 1, 1, '$now', '$now')",
    );
    $periodeId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO modele_nodes
            (tenant_id, modele_bulletin_id, system_node_id, type_node, libelle, ordre, obligatoire, created_at, updated_at)
         VALUES ($tenantId, $modeleId, NULL, 'MATIERE', 'Mathématiques', 1, 1, '$now', '$now')",
    );
    $nodeId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO modele_maxima
            (tenant_id, modele_bulletin_id, node_id, periode_id, maxima, coefficient, created_at, updated_at)
         VALUES ($tenantId, $modeleId, $nodeId, $periodeId, 20.00, 1.00, '$now', '$now')",
    );

    $pdo->exec(
        "INSERT INTO eleves
            (tenant_id, etablissement_id, nom, postnom, prenom, sexe, date_naissance, lieu_naissance, actif, public_id, created_at, updated_at)
         VALUES ($tenantId, $etablissementId, 'Dupont', 'Jean', 'Marie', 'F', '2008-01-15', 'Kinshasa', 1, '{$publicId()}', '$now', '$now')",
    );
    $eleveId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO inscriptions
            (tenant_id, eleve_id, classe_id, annee_scolaire_id, numero_inscription, statut, date_inscription, public_id, created_at, updated_at)
         VALUES ($tenantId, $eleveId, $classeId, $anneeId, '{$prefix}', 'ACTIF', '2026-09-01', '{$publicId()}', '$now', '$now')",
    );
    $inscriptionId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO bulletins
            (tenant_id, inscription_id, classe_id, modele_bulletin_id, verrouille, public_id, created_at, updated_at)
         VALUES ($tenantId, $inscriptionId, $classeId, $modeleId, 0, '{$publicId()}', '$now', '$now')",
    );
    $bulletinId = (int) $pdo->lastInsertId();

    $pdo->exec(
        "INSERT INTO notes
            (tenant_id, bulletin_id, node_id, periode_id, statut_resultat, lock_version, valeur, public_id, created_at, updated_at)
         VALUES ($tenantId, $bulletinId, $nodeId, $periodeId, 'SAISIE', 0, 16.00, '{$publicId()}', '$now', '$now')",
    );

    $pdo->exec(
        "INSERT INTO baremes_mentions
            (tenant_id, cycle_id, system_template_id, min_pourcentage, max_pourcentage, mention, decision_defaut, ordre, actif, created_at, updated_at)
         VALUES ($tenantId, $cycleId, $templateId, 0.00, 49.99, 'INSUFFISANT', 'ECHOUE', 1, 1, '$now', '$now'),
                ($tenantId, $cycleId, $templateId, 50.00, 100.00, 'PASSABLE', 'PASSE', 2, 1, '$now', '$now')",
    );

    $ids = [
        'tenant' => $tenantId,
        'etablissement' => $etablissementId,
        'user' => $userId,
        'role' => $roleId,
        'annee' => $anneeId,
        'classe' => $classeId,
        'cycle' => $cycleId,
        'degre' => $degreId,
        'niveau' => $niveauId,
        'layout' => $layoutId,
        'template' => $templateId,
        'calendrier' => $calendrierId,
        'modele' => $modeleId,
        'periode' => $periodeId,
        'node' => $nodeId,
        'eleve' => $eleveId,
        'inscription' => $inscriptionId,
        'bulletin' => $bulletinId,
    ];

    test()->mysqlSeeds = $ids;

    return $ids;
}

function cleanupMysqlData(array $ids): void
{
    $pdo = connectToMysql();

    if (! $pdo instanceof PDO) {
        return;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $delete = static function (string $table, string $column, int $value) use ($pdo): void {
        $pdo->exec("DELETE FROM {$table} WHERE {$column} = {$value}");
    };

    foreach (['baremes_mentions', 'notes', 'resultats_bulletins', 'publications',
        'suivis_encodage_notes', 'verrouillages_periodes', 'bulletins', 'inscriptions',
        'eleves', 'modele_maxima', 'modele_nodes', 'modele_periodes', 'modeles_bulletin',
        'classes', 'utilisateurs', 'roles', 'etablissements'] as $table) {
        $delete($table, 'tenant_id', $ids['tenant']);
    }

    $delete('annees_scolaires', 'id', $ids['annee']);

    $delete('tenants', 'id', $ids['tenant']);

    $delete('utilisateur_roles', 'utilisateur_id', $ids['user']);

    $delete('system_template_nodes', 'template_id', $ids['template']);
    $delete('system_template_periodes', 'template_id', $ids['template']);
    $delete('system_templates_bulletin', 'id', $ids['template']);
    $delete('system_layouts_bulletin', 'id', $ids['layout']);

    $delete('calendriers_scolaires', 'id', $ids['calendrier']);

    $delete('ref_niveaux', 'id', $ids['niveau']);
    $delete('ref_degres', 'id', $ids['degre']);
    $delete('ref_cycles', 'id', $ids['cycle']);

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

function lockPeriodFromPdo(PDO $pdo, array $ids): void
{
    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        'SELECT * FROM classes WHERE id = :id FOR UPDATE',
    );
    $statement->execute(['id' => $ids['classe']]);

    $existStatement = $pdo->prepare(
        'SELECT id FROM verrouillages_periodes
         WHERE tenant_id = :tenant AND classe_id = :classe AND periode_id = :periode
         FOR UPDATE',
    );
    $existStatement->execute([
        'tenant' => $ids['tenant'],
        'classe' => $ids['classe'],
        'periode' => $ids['periode'],
    ]);
    $existingId = $existStatement->fetchColumn();

    if ($existingId !== false) {
        $pdo->prepare(
            'UPDATE verrouillages_periodes
             SET verrouille = 1, verrouille_par = :user, date_verrouillage = NOW()
             WHERE id = :id',
        )->execute(['user' => $ids['user'], 'id' => $existingId]);
    } else {
        $pdo->prepare(
            'INSERT INTO verrouillages_periodes
                (tenant_id, classe_id, periode_id, verrouille, verrouille_par, date_verrouillage, created_at, updated_at)
             VALUES (:tenant, :classe, :periode, 1, :user, NOW(), NOW(), NOW())',
        )->execute([
            'tenant' => $ids['tenant'],
            'classe' => $ids['classe'],
            'periode' => $ids['periode'],
            'user' => $ids['user'],
        ]);
    }

    $pdo->commit();
}

function isPeriodLockedFromPdo(PDO $pdo, array $ids): bool
{
    $statement = $pdo->prepare(
        'SELECT verrouille FROM verrouillages_periodes
         WHERE tenant_id = :tenant AND classe_id = :classe AND periode_id = :periode
         FOR UPDATE',
    );
    $statement->execute([
        'tenant' => $ids['tenant'],
        'classe' => $ids['classe'],
        'periode' => $ids['periode'],
    ]);

    return (bool) $statement->fetchColumn();
}

function periodLockStateFromPdo(PDO $pdo, array $ids): bool
{
    $statement = $pdo->prepare(
        'SELECT verrouille FROM verrouillages_periodes
         WHERE tenant_id = :tenant AND classe_id = :classe AND periode_id = :periode',
    );
    $statement->execute([
        'tenant' => $ids['tenant'],
        'classe' => $ids['classe'],
        'periode' => $ids['periode'],
    ]);

    return (bool) $statement->fetchColumn();
}

function isLockBlocked(callable $attempt): bool
{
    try {
        $attempt();

        return false;
    } catch (PDOException $exception) {
        return str_contains(
            strtolower($exception->getMessage()),
            'lock wait timeout',
        );
    }
}

function publishFromPdo(PDO $pdo, array $ids): void
{
    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        'SELECT * FROM bulletins WHERE id = :id FOR UPDATE',
    );
    $statement->execute(['id' => $ids['bulletin']]);

    $existsStatement = $pdo->prepare(
        "SELECT id FROM publications
         WHERE bulletin_id = :bulletin AND periode_id = :periode AND statut = 'PUBLIEE'
         ORDER BY id DESC LIMIT 1
         FOR UPDATE",
    );
    $existsStatement->execute([
        'bulletin' => $ids['bulletin'],
        'periode' => $ids['periode'],
    ]);

    if ($existsStatement->fetchColumn() === false) {
        $pdo->prepare(
            'UPDATE bulletins
             SET total_obtenu = 16.00, total_maxima = 20.00, pourcentage = 80.00,
                 verrouille = 1, calcule_at = NOW(), updated_at = NOW()
             WHERE id = :id',
        )->execute(['id' => $ids['bulletin']]);

        $pdo->prepare(
            "INSERT INTO resultats_bulletins
                (tenant_id, bulletin_id, periode_id, modele_bulletin_id, version, engine_version,
                 configuration_hash, input_hash, result_hash, total_obtenu, total_maxima, pourcentage,
                 mention, decision, rang, nombre_eleves_classe, payload, created_at, updated_at)
             VALUES (:tenant, :bulletin, :periode, :modele, 1, '1.0.0',
                     :configHash, :inputHash, :resultHash, 16.00, 20.00, 80.00,
                     'PASSABLE', 'PASSE', 1, 1, :payload, NOW(), NOW())",
        )->execute([
            'tenant' => $ids['tenant'],
            'bulletin' => $ids['bulletin'],
            'periode' => $ids['periode'],
            'modele' => $ids['modele'],
            'configHash' => str_repeat('a', 64),
            'inputHash' => str_repeat('b', 64),
            'resultHash' => str_repeat('c', 64),
            'payload' => json_encode([
                'schema' => 'current-v1-snapshot',
                'engine_version' => '1.0.0',
                'configuration_hash' => str_repeat('a', 64),
                'input_hash' => str_repeat('b', 64),
                'result_hash' => str_repeat('c', 64),
                'input' => [
                    'bulletin_id' => $ids['bulletin'],
                    'periode_id' => $ids['periode'],
                    'configuration_hash' => str_repeat('a', 64),
                    'periode_active' => true,
                    'periode_calculable' => true,
                    'slots' => [],
                ],
                'result' => [
                    'total_obtenu' => '16.00',
                    'total_maxima' => '20.00',
                    'pourcentage' => '80.00',
                    'mention' => 'PASSABLE',
                    'decision' => 'PASSE',
                    'decision_statut' => 'EN_COURS',
                    'rang' => 1,
                    'nombre_eleves_classe' => 1,
                ],
            ]),
        ]);
        $resultatId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO publications
                (tenant_id, bulletin_id, periode_id, resultat_bulletin_id, checksum, engine_version,
                 statut, publiee_par, publiee_at, created_at, updated_at)
             VALUES (:tenant, :bulletin, :periode, :resultat, :checksum, '1.0.0',
                     'PUBLIEE', :user, NOW(), NOW(), NOW())",
        )->execute([
            'tenant' => $ids['tenant'],
            'bulletin' => $ids['bulletin'],
            'periode' => $ids['periode'],
            'resultat' => $resultatId,
            'checksum' => str_repeat('c', 64),
            'user' => $ids['user'],
        ]);
    }

    $pdo->commit();
}

function isPublicationPresentFromPdo(PDO $pdo, array $ids): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM publications
         WHERE bulletin_id = :bulletin AND periode_id = :periode AND statut = 'PUBLIEE'",
    );
    $statement->execute(['bulletin' => $ids['bulletin'], 'periode' => $ids['periode']]);

    return (int) $statement->fetchColumn() > 0;
}

function prepareDatabase(): void
{
    $cycleId = DB::table('ref_cycles')->insertGetId([
        'code' => 'HUMANITES_CONCURRENCY',
        'nom' => 'Humanités Concurrency',
        'ordre' => 6,
    ]);

    $degreId = DB::table('ref_degres')->insertGetId([
        'cycle_id' => $cycleId,
        'code' => 'HUMANITES_GENERALES_CONCURRENCY',
        'nom' => 'Humanités générales',
        'ordre' => 1,
    ]);

    $niveauId = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId,
        'degre_id' => $degreId,
        'code' => 'HUM_1_CONCURRENCY',
        'nom' => '1ère Humanités',
        'numero' => 1,
        'ordre' => 1,
    ]);

    $layoutId = DB::table('system_layouts_bulletin')->insertGetId([
        'code' => 'TEST_LAYOUT_CONCURRENCY',
        'nom' => 'Layout Test Concurrency',
        'vue_blade' => 'test',
        'actif' => true,
    ]);

    $templateId = DB::table('system_templates_bulletin')->insertGetId([
        'code' => 'TEST_TEMPLATE_CONCURRENCY',
        'nom' => 'Template Concurrency',
        'cycle_id' => $cycleId,
        'degre_id' => $degreId,
        'layout_id' => $layoutId,
        'type_decoupage' => 'TRIMESTRIEL',
        'total_general_maxima' => 20.00,
        'actif' => true,
    ]);

    test()->tenant = Tenant::create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École Test Concurrency',
        'slug' => 'ecole-test-concurrency',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    test()->etablissement = Etablissement::create([
        'tenant_id' => test()->tenant->id,
        'nom' => 'Complexe Scolaire Concurrency',
        'code_ministere' => 'CSC9',
        'province' => 'Kinshasa',
        'ville' => 'Kinshasa',
    ]);

    test()->role = Role::create([
        'tenant_id' => test()->tenant->id,
        'code' => 'ADMIN',
        'nom' => 'Administrateur',
    ]);

    test()->user = Utilisateur::create([
        'tenant_id' => test()->tenant->id,
        'etablissement_id' => test()->etablissement->id,
        'nom' => 'Admin',
        'postnom' => 'Test',
        'prenom' => 'User',
        'telephone' => '+243000000004',
        'email' => 'admin-concurrency@test.com',
        'mot_de_passe' => Hash::make('password123'),
        'actif' => true,
    ]);

    test()->user->roles()->attach(test()->role->id);

    $calendrierId = DB::table('calendriers_scolaires')->insertGetId([
        'code' => 'RDC_TEST_CONCURRENCY',
        'libelle' => 'Calendrier Test Concurrency',
        'pays' => 'RDC',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'officiel' => true,
        'actif' => true,
    ]);

    test()->anneeScolaire = AnneeScolaire::create([
        'calendrier_scolaire_id' => $calendrierId,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
    ]);

    test()->classe = Classe::create([
        'tenant_id' => test()->tenant->id,
        'etablissement_id' => test()->etablissement->id,
        'annee_scolaire_id' => test()->anneeScolaire->id,
        'cycle_id' => $cycleId,
        'niveau_id' => $niveauId,
        'degre_id' => $degreId,
        'system_template_id' => $templateId,
        'nom' => '1ère Commerciale Concurrency',
        'active' => true,
    ]);

    test()->modeleBulletin = ModeleBulletin::create([
        'tenant_id' => test()->tenant->id,
        'classe_id' => test()->classe->id,
        'annee_scolaire_id' => test()->anneeScolaire->id,
        'system_template_id' => $templateId,
        'layout_id' => $layoutId,
        'nom' => 'Modèle Concurrency',
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

    test()->periode = ModelePeriode::create([
        'tenant_id' => test()->tenant->id,
        'modele_bulletin_id' => test()->modeleBulletin->id,
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

    test()->node = ModeleNode::create([
        'tenant_id' => test()->tenant->id,
        'modele_bulletin_id' => test()->modeleBulletin->id,
        'system_node_id' => $systemNodeId,
        'type_node' => 'MATIERE',
        'libelle' => 'Mathématiques',
        'ordre' => 1,
        'obligatoire' => true,
    ]);

    test()->maximum = ModeleMaximum::create([
        'tenant_id' => test()->tenant->id,
        'modele_bulletin_id' => test()->modeleBulletin->id,
        'node_id' => test()->node->id,
        'periode_id' => test()->periode->id,
        'maxima' => 20.00,
        'coefficient' => 1.00,
    ]);

    test()->eleve1 = Eleve::create([
        'tenant_id' => test()->tenant->id,
        'etablissement_id' => test()->etablissement->id,
        'nom' => 'Dupont',
        'postnom' => 'Jean',
        'prenom' => 'Marie',
        'sexe' => 'F',
        'date_naissance' => '2008-01-15',
        'lieu_naissance' => 'Kinshasa',
    ]);

    test()->inscription = Inscription::create([
        'tenant_id' => test()->tenant->id,
        'classe_id' => test()->classe->id,
        'annee_scolaire_id' => test()->anneeScolaire->id,
        'eleve_id' => test()->eleve1->id,
        'statut' => 'ACTIF',
    ]);

    test()->bulletin1 = Bulletin::create([
        'tenant_id' => test()->tenant->id,
        'inscription_id' => test()->inscription->id,
        'classe_id' => test()->classe->id,
        'modele_bulletin_id' => test()->modeleBulletin->id,
    ]);

    Note::create([
        'tenant_id' => test()->tenant->id,
        'bulletin_id' => test()->bulletin1->id,
        'node_id' => test()->node->id,
        'periode_id' => test()->periode->id,
        'valeur' => 16.00,
        'statut_resultat' => 'SAISIE',
    ]);

    BaremeMention::create([
        'tenant_id' => test()->tenant->id,
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
        'tenant_id' => test()->tenant->id,
        'cycle_id' => $cycleId,
        'system_template_id' => $templateId,
        'min_pourcentage' => 50.00,
        'max_pourcentage' => 100.00,
        'mention' => 'PASSABLE',
        'decision_defaut' => 'PASSE',
        'ordre' => 2,
        'actif' => true,
    ]);
}
