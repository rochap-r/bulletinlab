<?php

use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\ModeleBulletin;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createTenantForVieScolaire(string $slug): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => Str::headline($slug),
        'slug' => $slug,
        'type_tenant' => 'GROUPE_ECOLES',
        'statut' => 'ACTIF',
    ]);
}

function vieScolaireRole(string $code): Role
{
    return Role::query()->firstOrCreate(
        ['tenant_id' => null, 'name' => $code],
        ['code' => $code, 'nom' => Str::headline($code), 'guard_name' => 'web', 'system_role' => true],
    );
}

function vieScolaireUser(Tenant $tenant, string $role, ?Etablissement $etablissement = null): Utilisateur
{
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement?->id,
        'nom' => 'Acteur',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('secret-password'),
        'actif' => true,
    ]);
    $user->assignRole(vieScolaireRole($role));

    return $user;
}

function vieScolaireSchool(Tenant $tenant, string $nom, string $type): Etablissement
{
    return Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => $nom,
        'type_etablissement' => $type,
    ]);
}

function vieScolaireCatalog(): array
{
    $cycleId = DB::table('ref_cycles')->insertGetId([
        'code' => 'VIE_SCOLAIRE', 'nom' => 'Cycle test', 'ordre' => 1, 'actif' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $niveauId = DB::table('ref_niveaux')->insertGetId([
        'cycle_id' => $cycleId, 'degre_id' => null, 'code' => 'VIE_SCOLAIRE_1',
        'nom' => 'Niveau 1', 'numero' => 1, 'ordre' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $layoutId = DB::table('system_layouts_bulletin')->insertGetId([
        'code' => 'VIE_SCOLAIRE_LAYOUT', 'nom' => 'Layout test', 'actif' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $templateId = DB::table('system_templates_bulletin')->insertGetId([
        'code' => 'VIE_SCOLAIRE_TEMPLATE', 'nom' => 'Modèle test', 'cycle_id' => $cycleId,
        'layout_id' => $layoutId, 'type_decoupage' => 'TRIMESTRIEL', 'version' => 'test',
        'actif' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $year = AnneeScolaire::query()->create([
        'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
        'active' => true, 'cloturee' => false,
    ]);

    return ['cycle_id' => $cycleId, 'niveau_id' => $niveauId, 'layout_id' => $layoutId, 'template_id' => $templateId, 'year_id' => $year->id];
}

function vieScolaireHeaders(Utilisateur $user, ?Etablissement $school = null): array
{
    $headers = ['Authorization' => 'Bearer '.$user->createToken('vie-scolaire', ["tenant:{$user->tenant_id}"])->plainTextToken];

    if ($school !== null) {
        $headers['X-Etablissement'] = (string) $school->id;
    }

    return $headers;
}

test('the catalog exposes school years, cycles with niveaux and templates', function (): void {
    $tenant = createTenantForVieScolaire('vie-catalogue');
    $school = vieScolaireSchool($tenant, 'École A', 'PRIMAIRE');
    $manager = vieScolaireUser($tenant, 'GESTIONNAIRE_ECOLE', $school);
    vieScolaireCatalog();

    $this->withHeaders(vieScolaireHeaders($manager))
        ->getJson('/api/v1/referentiels')
        ->assertOk()
        ->assertJsonPath('data.annees_scolaires.0.libelle', '2026-2027')
        ->assertJsonPath('data.cycles.0.code', 'VIE_SCOLAIRE')
        ->assertJsonPath('data.cycles.0.niveaux.0.code', 'VIE_SCOLAIRE_1')
        ->assertJsonPath('data.modeles.0.code', 'VIE_SCOLAIRE_TEMPLATE');
});

test('an administrator manages classes of the selected school', function (): void {
    $tenant = createTenantForVieScolaire('vie-classes');
    $schoolA = vieScolaireSchool($tenant, 'École A', 'PRIMAIRE');
    $schoolB = vieScolaireSchool($tenant, 'École B', 'SECONDAIRE');
    $admin = vieScolaireUser($tenant, 'ADMIN_TENANT');
    $catalog = vieScolaireCatalog();

    $payload = [
        'annee_scolaire_id' => $catalog['year_id'],
        'cycle_id' => $catalog['cycle_id'],
        'niveau_id' => $catalog['niveau_id'],
        'nom' => '6e Primaire A',
    ];

    $classId = $this->withHeaders(vieScolaireHeaders($admin, $schoolA))
        ->postJson('/api/v1/classes', $payload)
        ->assertCreated()
        ->assertJsonPath('data.nom', '6e Primaire A')
        ->assertJsonPath('data.system_template_id', $catalog['template_id'])
        ->json('data.id');

    // Visible dans l'école sélectionnée, invisible depuis l'autre.
    $this->withHeaders(vieScolaireHeaders($admin, $schoolA))
        ->getJson('/api/v1/classes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nom', '6e Primaire A');

    $this->withHeaders(vieScolaireHeaders($admin, $schoolB))
        ->getJson('/api/v1/classes')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // Archivage avec motif.
    $this->withHeaders(vieScolaireHeaders($admin, $schoolA))
        ->postJson("/api/v1/classes/{$classId}/archivage", ['motif' => 'Fin de test.'])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

test('a secretariat manages students of their own school but not classes', function (): void {
    $tenant = createTenantForVieScolaire('vie-secretariat');
    $school = vieScolaireSchool($tenant, 'École A', 'PRIMAIRE');
    $secretary = vieScolaireUser($tenant, 'SECRETARIAT', $school);
    $catalog = vieScolaireCatalog();

    $this->withHeaders(vieScolaireHeaders($secretary))
        ->postJson('/api/v1/eleves', ['nom' => 'Mbuyi', 'prenom' => 'Jean'])
        ->assertCreated()
        ->assertJsonPath('data.nom', 'Mbuyi');

    $this->withHeaders(vieScolaireHeaders($secretary))
        ->postJson('/api/v1/classes', [
            'annee_scolaire_id' => $catalog['year_id'],
            'cycle_id' => $catalog['cycle_id'],
            'niveau_id' => $catalog['niveau_id'],
            'nom' => '6e Primaire A',
        ])
        ->assertForbidden();
});

test('a manager manages classes of their own school without header', function (): void {
    $tenant = createTenantForVieScolaire('vie-manager-classes');
    $school = vieScolaireSchool($tenant, 'École A', 'PRIMAIRE');
    $manager = vieScolaireUser($tenant, 'GESTIONNAIRE_ECOLE', $school);
    $catalog = vieScolaireCatalog();

    $this->withHeaders(vieScolaireHeaders($manager))
        ->postJson('/api/v1/classes', [
            'annee_scolaire_id' => $catalog['year_id'],
            'cycle_id' => $catalog['cycle_id'],
            'niveau_id' => $catalog['niveau_id'],
            'nom' => '5e Primaire A',
        ])
        ->assertCreated()
        ->assertJsonPath('data.nom', '5e Primaire A');
});

test('an administrator enrolls students of the selected school', function (): void {
    $tenant = createTenantForVieScolaire('vie-eleves');
    $schoolA = vieScolaireSchool($tenant, 'École A', 'PRIMAIRE');
    $schoolB = vieScolaireSchool($tenant, 'École B', 'SECONDAIRE');
    $admin = vieScolaireUser($tenant, 'ADMIN_TENANT');
    $catalog = vieScolaireCatalog();

    $classId = $this->withHeaders(vieScolaireHeaders($admin, $schoolA))
        ->postJson('/api/v1/classes', [
            'annee_scolaire_id' => $catalog['year_id'],
            'cycle_id' => $catalog['cycle_id'],
            'niveau_id' => $catalog['niveau_id'],
            'nom' => '6e Primaire A',
        ])
        ->assertCreated()
        ->json('data.id');

    expect(ModeleBulletin::query()->where('classe_id', $classId)
        ->where('annee_scolaire_id', $catalog['year_id'])->count())->toBe(1);

    $studentId = $this->withHeaders(vieScolaireHeaders($admin, $schoolA))
        ->postJson('/api/v1/eleves', ['nom' => 'Mbuyi', 'prenom' => 'Jean'])
        ->assertCreated()
        ->assertJsonPath('data.nom', 'Mbuyi')
        ->json('data.id');

    // L'élève d'une autre école ne peut pas être inscrit ici.
    $otherStudent = Eleve::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $schoolB->id,
        'nom' => 'Autre',
        'actif' => true,
    ]);

    $this->withHeaders(vieScolaireHeaders($admin, $schoolA))
        ->postJson("/api/v1/classes/{$classId}/inscriptions", ['eleve_id' => $otherStudent->id])
        ->assertUnprocessable();

    $this->withHeaders(vieScolaireHeaders($admin, $schoolA))
        ->postJson("/api/v1/classes/{$classId}/inscriptions", ['eleve_id' => $studentId])
        ->assertCreated()
        ->assertJsonPath('data.classe_id', $classId);

    $this->withHeaders(vieScolaireHeaders($admin, $schoolA))
        ->getJson('/api/v1/eleves')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
