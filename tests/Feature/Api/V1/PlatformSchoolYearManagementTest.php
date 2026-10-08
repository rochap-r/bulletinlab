<?php

use App\Models\AnneeScolaire;
use App\Models\CalendrierPeriode;
use App\Models\CalendrierScolaire;
use App\Models\RolePlateforme;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

if (! function_exists('makePlatformYearAdminToken')) {
    function makePlatformYearAdminToken(): string
    {
        RolePlateforme::query()->updateOrCreate(
            ['code' => 'ADMIN_PLATEFORME'],
            ['nom' => 'Administrateur plateforme'],
        );

        $utilisateur = UtilisateurPlateforme::factory()->create([
            'email' => 'year-admin@plateforme.test',
            'mot_de_passe' => Hash::make('secret-password'),
            'actif' => true,
        ]);

        $utilisateur->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);

        return $utilisateur->createToken('year-test', ['context:platform'])->plainTextToken;
    }
}

if (! function_exists('makeCalendarForTests')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function makeCalendarForTests(array $attributes = []): CalendrierScolaire
    {
        $calendrier = CalendrierScolaire::query()->create([
            'code' => 'CAL_'.Str::upper(Str::random(6)),
            'libelle' => 'Calendrier test',
            'pays' => 'RDC',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'officiel' => true,
            'actif' => true,
            ...$attributes,
        ]);

        CalendrierPeriode::query()->create([
            'calendrier_scolaire_id' => $calendrier->id,
            'code' => 'P1',
            'nom' => 'Première période',
            'type_periode' => 'PERIODE',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-10-31',
            'ordre' => 1,
        ]);

        return $calendrier;
    }
}

test('the platform can list global school years with their calendar', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();
    AnneeScolaire::query()->create([
        'calendrier_scolaire_id' => $calendrier->id,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/platform/annees-scolaires')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.libelle', '2026-2027')
        ->assertJsonPath('data.0.active', true)
        ->assertJsonStructure([
            'data' => [[
                'id', 'libelle', 'date_debut', 'date_fin', 'active', 'cloturee',
                'calendrier' => ['id', 'code', 'libelle'],
            ]],
        ]);
});

test('the platform can create a draft school year within its calendar', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();

    $this->withToken($token)
        ->postJson('/api/v1/platform/annees-scolaires', [
            'calendrier_scolaire_id' => $calendrier->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ])
        ->assertCreated()
        ->assertJsonPath('data.libelle', '2026-2027')
        ->assertJsonPath('data.active', false)
        ->assertJsonPath('data.cloturee', false);

    $this->assertDatabaseHas('annees_scolaires', [
        'calendrier_scolaire_id' => $calendrier->id,
        'libelle' => '2026-2027',
        'active' => false,
    ]);
});

test('a school year is rejected outside its calendar bounds', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();

    $this->withToken($token)
        ->postJson('/api/v1/platform/annees-scolaires', [
            'calendrier_scolaire_id' => $calendrier->id,
            'libelle' => '2030-2031',
            'date_debut' => '2031-09-01',
            'date_fin' => '2032-07-31',
        ])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonValidationErrors(['date_debut']);
});

test('a school year is rejected when dates are inverted', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();

    $this->withToken($token)
        ->postJson('/api/v1/platform/annees-scolaires', [
            'calendrier_scolaire_id' => $calendrier->id,
            'libelle' => '2026-2027',
            'date_debut' => '2027-07-31',
            'date_fin' => '2026-09-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_fin']);
});

test('the school year libelle must follow the dddd-dddd convention', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();

    $this->withToken($token)
        ->postJson('/api/v1/platform/annees-scolaires', [
            'calendrier_scolaire_id' => $calendrier->id,
            'libelle' => 'Année 2026',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['libelle']);
});

test('duplicate years are rejected within the same calendar', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();
    AnneeScolaire::query()->create([
        'calendrier_scolaire_id' => $calendrier->id,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => false,
        'cloturee' => false,
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/platform/annees-scolaires', [
            'calendrier_scolaire_id' => $calendrier->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['libelle']);
});

test('a draft school year can be updated', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();
    $annee = AnneeScolaire::query()->create([
        'calendrier_scolaire_id' => $calendrier->id,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => false,
        'cloturee' => false,
    ]);

    $this->withToken($token)
        ->putJson("/api/v1/platform/annees-scolaires/{$annee->id}", [
            'calendrier_scolaire_id' => $calendrier->id,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-15',
            'date_fin' => '2027-07-31',
        ])
        ->assertOk()
        ->assertJsonPath('data.date_debut', '2026-09-15');
});

test('activation makes the school year the only active one', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();
    $first = AnneeScolaire::query()->create([
        'calendrier_scolaire_id' => $calendrier->id,
        'libelle' => '2025-2026',
        'date_debut' => '2025-09-01',
        'date_fin' => '2026-07-31',
        'active' => true,
        'cloturee' => false,
    ]);
    $second = AnneeScolaire::query()->create([
        'calendrier_scolaire_id' => $calendrier->id,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => false,
        'cloturee' => false,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/annees-scolaires/{$second->id}/activation")
        ->assertOk()
        ->assertJsonPath('data.active', true);

    $this->assertDatabaseHas('annees_scolaires', ['id' => $first->id, 'active' => false]);
    $this->assertDatabaseHas('annees_scolaires', ['id' => $second->id, 'active' => true]);
});

test('a closed school year cannot be activated', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();
    $annee = AnneeScolaire::query()->create([
        'calendrier_scolaire_id' => $calendrier->id,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => false,
        'cloturee' => true,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/annees-scolaires/{$annee->id}/activation")
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->assertDatabaseHas('annees_scolaires', ['id' => $annee->id, 'active' => false]);
});

test('closing a school year deactivates it', function () {
    $token = makePlatformYearAdminToken();
    $calendrier = makeCalendarForTests();
    $annee = AnneeScolaire::query()->create([
        'calendrier_scolaire_id' => $calendrier->id,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);

    $this->withToken($token)
        ->postJson("/api/v1/platform/annees-scolaires/{$annee->id}/cloture")
        ->assertOk()
        ->assertJsonPath('data.cloturee', true)
        ->assertJsonPath('data.active', false);

    $this->assertDatabaseHas('annees_scolaires', ['id' => $annee->id, 'active' => false]);
});

test('the platform can read the school calendars with their periods', function () {
    $token = makePlatformYearAdminToken();
    makeCalendarForTests(['code' => 'RDC_OFFICIEL']);

    $this->withToken($token)
        ->getJson('/api/v1/platform/calendriers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'RDC_OFFICIEL')
        ->assertJsonPath('data.0.periodes.0.code', 'P1')
        ->assertJsonStructure([
            'data' => [[
                'id', 'code', 'libelle', 'pays', 'date_debut', 'date_fin',
                'officiel', 'actif', 'periodes' => [['id', 'code', 'nom', 'type_periode']],
            ]],
        ]);
});

test('the school year endpoints require a platform context', function () {
    $this->getJson('/api/v1/platform/annees-scolaires')
        ->assertUnauthorized();

    $this->getJson('/api/v1/platform/calendriers')
        ->assertUnauthorized();
});
