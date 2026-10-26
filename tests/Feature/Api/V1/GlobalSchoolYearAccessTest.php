<?php

use App\Domains\SchoolYear\Services\CloseEstablishmentSchoolYearService;
use App\Domains\SchoolYear\Services\OpenEstablishmentSchoolYearService;
use App\Models\AnneeScolaire;
use App\Models\Etablissement;
use App\Models\OuvertureAnnuelle;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createGlobalSchoolYear(): AnneeScolaire
{
    return AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);
}

function createGlobalSchoolYearTenant(): Tenant
{
    return Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'Tenant Année Globale',
        'slug' => 'annee-globale-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
}

function createGlobalSchoolYearEstablishment(
    Tenant $tenant,
    array $attributes = [],
): Etablissement {
    return Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Établissement Année Globale',
        'actif' => true,
        ...$attributes,
    ]);
}

/**
 * @return array{user: Utilisateur, token: string}
 */
function createGlobalSchoolYearAdmin(
    Tenant $tenant,
    Etablissement $establishment,
): array {
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $establishment->id,
        'nom' => 'Admin',
        'postnom' => 'Année',
        'prenom' => 'Globale',
        'email' => 'annee-globale-'.Str::lower(Str::random(8)).'@example.test',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);

    $role = Role::query()->firstOrCreate(
        [
            'tenant_id' => $tenant->id,
            'code' => 'ADMIN_ECOLE',
        ],
        [
            'nom' => 'Administrateur école',
            'system_role' => false,
        ],
    );
    $user->roles()->syncWithoutDetaching($role);

    return [
        'user' => $user,
        'token' => $user->createToken(
            'annee-globale',
            ["tenant:{$tenant->id}"],
        )->plainTextToken,
    ];
}

test('a single global school year is shared across establishments', function () {
    $year = createGlobalSchoolYear();
    $adminA = createGlobalSchoolYearAdmin(
        $tenantA = createGlobalSchoolYearTenant(),
        $establishmentA = createGlobalSchoolYearEstablishment($tenantA),
    );
    $adminB = createGlobalSchoolYearAdmin(
        $tenantB = createGlobalSchoolYearTenant(),
        $establishmentB = createGlobalSchoolYearEstablishment($tenantB),
    );

    $this->withToken($adminA['token'])
        ->getJson('/api/v1/annees-scolaires/courante')
        ->assertOk()
        ->assertJsonPath('data.id', $year->getKey());

    $this->withToken($adminB['token'])
        ->getJson('/api/v1/annees-scolaires/courante')
        ->assertOk()
        ->assertJsonPath('data.id', $year->getKey());

    $this->assertDatabaseCount('annees_scolaires', 1);
    expect($establishmentB->tenant_id)->not->toBe($establishmentA->tenant_id);
});

test('the current school year is readable without a selected establishment', function () {
    $year = createGlobalSchoolYear();
    $tenant = createGlobalSchoolYearTenant();
    $admin = createGlobalSchoolYearAdmin(
        $tenant,
        createGlobalSchoolYearEstablishment($tenant),
    );
    $admin['user']->update(['etablissement_id' => null]);

    $this->withToken($admin['token'])
        ->getJson('/api/v1/annees-scolaires/courante')
        ->assertOk()
        ->assertJsonPath('data.id', $year->getKey());
});

test('the current school year route fails closed when no school year matches today', function () {
    $tenant = createGlobalSchoolYearTenant();
    $admin = createGlobalSchoolYearAdmin(
        $tenant,
        createGlobalSchoolYearEstablishment($tenant),
    );

    $this->withToken($admin['token'])
        ->getJson('/api/v1/annees-scolaires/courante')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json');
});

test('an establishment cannot create or close the global school year', function () {
    $year = createGlobalSchoolYear();
    $tenant = createGlobalSchoolYearTenant();
    $admin = createGlobalSchoolYearAdmin(
        $tenant,
        createGlobalSchoolYearEstablishment($tenant),
    );

    $this->withToken($admin['token'])
        ->postJson('/api/v1/annees-scolaires', ['libelle' => '2027-2028'])
        ->assertMethodNotAllowed();

    $this->withToken($admin['token'])
        ->postJson(
            "/api/v1/annees-scolaires/{$year->getKey()}/cloture",
            ['motif' => 'Interdit'],
        )
        ->assertNotFound();

    $this->assertDatabaseCount('annees_scolaires', 1);
    $this->assertDatabaseHas('annees_scolaires', [
        'id' => $year->getKey(),
        'active' => true,
        'cloturee' => false,
    ]);
});

test('a closed opening stays local and leaves the global school year active', function () {
    $year = createGlobalSchoolYear();
    $admin = createGlobalSchoolYearAdmin(
        $tenant = createGlobalSchoolYearTenant(),
        $establishment = createGlobalSchoolYearEstablishment($tenant),
    );

    $this->withToken($admin['token'])
        ->postJson('/api/v1/ouvertures-annuelles', [
            'annee_scolaire_id' => $year->getKey(),
        ])
        ->assertCreated();

    $this->withToken($admin['token'])
        ->postJson('/api/v1/ouvertures-annuelles', [
            'annee_scolaire_id' => $year->getKey(),
        ])
        ->assertCreated();

    $ouverture = OuvertureAnnuelle::query()
        ->where('etablissement_id', $establishment->getKey())
        ->firstOrFail();

    $this->withToken($admin['token'])
        ->postJson(
            "/api/v1/ouvertures-annuelles/{$ouverture->getKey()}/cloture",
            ['motif' => 'Année terminée pour cet établissement'],
        )
        ->assertOk()
        ->assertJsonPath('data.statut', 'CLOTUREE')
        ->assertJsonPath('data.annee_scolaire_id', $year->getKey());

    $this->assertDatabaseCount('ouvertures_annuelles', 1);
    $this->assertDatabaseHas('ouvertures_annuelles', [
        'etablissement_id' => $establishment->getKey(),
        'annee_scolaire_id' => $year->getKey(),
        'statut' => 'CLOTUREE',
    ]);
    $this->assertDatabaseHas('annees_scolaires', [
        'id' => $year->getKey(),
        'active' => true,
        'cloturee' => false,
    ]);
});

test('openings stay independent per establishment and leave the global school year active', function () {
    $year = createGlobalSchoolYear();
    $adminA = createGlobalSchoolYearAdmin(
        $tenantA = createGlobalSchoolYearTenant(),
        $establishmentA = createGlobalSchoolYearEstablishment($tenantA),
    );
    $adminB = createGlobalSchoolYearAdmin(
        $tenantB = createGlobalSchoolYearTenant(),
        $establishmentB = createGlobalSchoolYearEstablishment($tenantB),
    );

    $openService = new OpenEstablishmentSchoolYearService;
    $closeService = new CloseEstablishmentSchoolYearService;

    $closeService->close(
        $openService->open($establishmentA, $adminA['user'], $year->getKey()),
        $adminA['user'],
        'Fin locale pour cet établissement',
    );

    $openService->open($establishmentB, $adminB['user'], $year->getKey());

    $this->assertDatabaseCount('ouvertures_annuelles', 2);
    $this->assertDatabaseHas('ouvertures_annuelles', [
        'etablissement_id' => $establishmentA->getKey(),
        'statut' => 'CLOTUREE',
    ]);
    $this->assertDatabaseHas('ouvertures_annuelles', [
        'etablissement_id' => $establishmentB->getKey(),
        'statut' => 'OUVERTE',
    ]);
    $this->assertDatabaseHas('annees_scolaires', [
        'id' => $year->getKey(),
        'active' => true,
        'cloturee' => false,
    ]);
});
