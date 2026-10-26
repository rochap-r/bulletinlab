<?php

use App\Enums\CalendarCycle;
use App\Models\CalendrierScolaire;
use App\Models\RolePlateforme;
use App\Models\UtilisateurPlateforme;
use Database\Seeders\BulletinLabCalendarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    RolePlateforme::query()->updateOrCreate(
        ['code' => 'ADMIN_PLATEFORME'],
        ['nom' => 'Administrateur plateforme'],
    );
    $admin = UtilisateurPlateforme::factory()->create(['actif' => true]);
    $admin->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);
    $this->withToken($admin->createToken('calendar-pilotage-test', ['context:platform'])->plainTextToken);
});

test('calendar alerts are filtered by cycle audience and warning window', function () {
    $calendar = CalendrierScolaire::query()->create([
        'code' => 'PILOTAGE_TEST',
        'libelle' => 'Pilotage test',
        'pays' => 'RDC',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-02',
        'officiel' => true,
        'actif' => true,
    ]);
    $calendar->evenements()->createMany([
        [
            'code' => 'ENAFEP', 'nom' => 'ENAFEP', 'type_evenement' => 'EXAMEN_NATIONAL',
            'cycle_scolaire' => 'PRIMAIRE', 'date_debut' => '2027-06-08', 'date_fin' => '2027-06-09',
            'audiences' => ['TOUS'], 'niveau_alerte' => 'CRITIQUE', 'delai_alerte_jours' => 30,
        ],
        [
            'code' => 'EXETAT', 'nom' => 'Examen d’État', 'type_evenement' => 'EXAMEN_NATIONAL',
            'cycle_scolaire' => 'SECONDAIRE_TECHNIQUE', 'date_debut' => '2027-06-21', 'date_fin' => '2027-06-24',
            'audiences' => ['TOUS'], 'niveau_alerte' => 'CRITIQUE', 'delai_alerte_jours' => 30,
        ],
        [
            'code' => 'RAPPORT', 'nom' => 'Rapport de direction', 'type_evenement' => 'ADMINISTRATIF',
            'cycle_scolaire' => 'TOUS', 'date_debut' => '2027-06-01', 'date_fin' => '2027-06-01',
            'audiences' => ['DIRECTION'], 'niveau_alerte' => 'VIGILANCE', 'delai_alerte_jours' => 14,
        ],
    ]);

    $this->getJson("/api/v1/platform/calendriers/{$calendar->id}/alertes?cycle=PRIMAIRE&audience=DIRECTION&date=2027-05-25")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.titre', 'ENAFEP')
        ->assertJsonPath('data.0.niveau', 'CRITIQUE')
        ->assertJsonPath('data.1.titre', 'Rapport de direction');

    $this->getJson("/api/v1/platform/calendriers/{$calendar->id}/alertes?cycle=PRIMAIRE&audience=ENSEIGNANT&date=2027-05-25")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.titre', 'ENAFEP');
});

test('official seed exposes the three ministry use cases', function () {
    $this->seed(BulletinLabCalendarSeeder::class);

    $calendar = CalendrierScolaire::query()
        ->with(['profilsCycles', 'periodes', 'evenements'])
        ->where('code', 'RDC_2026_2027')
        ->firstOrFail();

    expect($calendar->date_fin)->toBe('2027-07-02')
        ->and($calendar->profilsCycles)->toHaveCount(3)
        ->and($calendar->profilsCycles->pluck('jours_ouvrables_attendus', 'cycle_scolaire.value')->all())->toMatchArray([
            'MATERNELLE' => 192,
            'PRIMAIRE' => 222,
            'SECONDAIRE_TECHNIQUE' => 222,
        ])
        ->and($calendar->periodes->firstWhere('code', 'SEMESTRE_1')?->nom)->toBe('Premier semestre')
        ->and($calendar->evenements->firstWhere('code', 'CONGE_T1')?->nom)->toBe('Congé de détente de novembre')
        ->and($calendar->evenements->firstWhere('code', 'VACANCES_T1')?->nom)->toBe('Vacances de fin d’année')
        ->and($calendar->evenements->firstWhere('code', 'CONGE_T2')?->nom)->toBe('Congé de détente de février')
        ->and($calendar->evenements->firstWhere('code', 'VACANCES_T2')?->nom)->toBe('Vacances de mars et avril')
        ->and($calendar->evenements->pluck('code'))->toContain('ENAFEP', 'TENASOSP', 'EXETAT_SESSION')
        ->and($calendar->periodes->where('cycle_scolaire', CalendarCycle::Maternelle))->not->toBeEmpty()
        ->and($calendar->periodes->where('cycle_scolaire', CalendarCycle::Primaire))->not->toBeEmpty()
        ->and($calendar->periodes->where('cycle_scolaire', CalendarCycle::SecondaireTechnique))->not->toBeEmpty();
});
