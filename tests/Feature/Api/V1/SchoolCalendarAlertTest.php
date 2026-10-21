<?php

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function schoolAlertTenant(): Tenant
{
    return Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'Espace alertes calendrier',
        'slug' => 'alertes-calendrier-'.Str::lower(Str::random(8)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);
}

function schoolAlertSchool(Tenant $tenant, string $type): Etablissement
{
    return Etablissement::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Établissement '.$type,
        'type_etablissement' => $type,
        'actif' => true,
    ]);
}

function schoolAlertUser(Tenant $tenant, ?Etablissement $school, ?string $role): Utilisateur
{
    $user = Utilisateur::query()->create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $school?->id,
        'nom' => 'Acteur',
        'postnom' => 'Calendrier',
        'prenom' => 'Test',
        'email' => 'alerte-calendrier-'.Str::lower(Str::random(8)).'@example.test',
        'telephone' => '+243'.random_int(100000000, 999999999),
        'mot_de_passe' => Hash::make('password'),
        'actif' => true,
    ]);

    if ($role !== null) {
        $user->roles()->syncWithoutDetaching([
            Role::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $role],
                ['nom' => Str::headline($role), 'system_role' => false],
            )->getKey(),
        ]);
    }

    return $user;
}

function schoolAlertYear(CalendrierScolaire $calendar): AnneeScolaire
{
    return AnneeScolaire::query()->create([
        'calendrier_scolaire_id' => $calendar->id,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);
}

function schoolAlertCalendarWithEvents(): CalendrierScolaire
{
    $today = CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
    $calendar = CalendrierScolaire::query()->create([
        'code' => 'ALERTES_'.Str::upper(Str::random(6)),
        'libelle' => 'Calendrier alertes',
        'pays' => 'RDC',
        'date_debut' => $today->subMonth()->toDateString(),
        'date_fin' => $today->addMonths(10)->toDateString(),
        'officiel' => true,
        'actif' => true,
    ]);

    $calendar->evenements()->createMany([
        [
            'code' => 'RENTREE_M', 'nom' => 'Rentrée des classes maternelle', 'type_evenement' => 'RENTREE',
            'cycle_scolaire' => 'MATERNELLE', 'date_debut' => $today->addDays(10)->toDateString(), 'date_fin' => $today->addDays(10)->toDateString(),
            'audiences' => ['TOUS'], 'niveau_alerte' => 'INFORMATION', 'delai_alerte_jours' => 15,
        ],
        [
            'code' => 'ENAFEP', 'nom' => 'ENAFEP', 'type_evenement' => 'EXAMEN_NATIONAL',
            'cycle_scolaire' => 'PRIMAIRE', 'date_debut' => $today->addDays(20)->toDateString(), 'date_fin' => $today->addDays(21)->toDateString(),
            'audiences' => ['TOUS'], 'niveau_alerte' => 'CRITIQUE', 'delai_alerte_jours' => 30,
        ],
        [
            'code' => 'EXETAT', 'nom' => 'Examen d’État', 'type_evenement' => 'EXAMEN_NATIONAL',
            'cycle_scolaire' => 'SECONDAIRE_TECHNIQUE', 'date_debut' => $today->addDays(25)->toDateString(), 'date_fin' => $today->addDays(28)->toDateString(),
            'audiences' => ['DIRECTION'], 'niveau_alerte' => 'CRITIQUE', 'delai_alerte_jours' => 30,
        ],
    ]);

    return $calendar;
}

function schoolAlertRequest(Utilisateur $user, string $query = ''): string
{
    app('auth')->forgetGuards();

    return "/api/v1/pilotage/alertes{$query}";
}

test('each school type is piloted with its own official cycle without asking the user', function (string $schoolType, string $expectedCycle, string $expectedTitle) {
    $calendar = schoolAlertCalendarWithEvents();
    schoolAlertYear($calendar);
    $tenant = schoolAlertTenant();
    $school = schoolAlertSchool($tenant, $schoolType);
    $user = schoolAlertUser($tenant, $school, 'ADMIN_ECOLE');

    $response = $this->withToken($user->createToken('alertes', ["tenant:{$tenant->id}"])->plainTextToken)
        ->getJson(schoolAlertRequest($user));

    $response->assertOk()
        ->assertJsonPath('meta.cycle', $expectedCycle)
        ->assertJsonPath('meta.audience', 'DIRECTION')
        ->assertJsonPath('data.0.titre', $expectedTitle);
})->with([
    'maternelle' => ['MATERNELLE', 'MATERNELLE', 'Rentrée des classes maternelle'],
    'primaire' => ['PRIMAIRE', 'PRIMAIRE', 'ENAFEP'],
    'secondaire' => ['SECONDAIRE', 'SECONDAIRE_TECHNIQUE', 'Examen d’État'],
]);

test('a mixed school must choose the cycle it wants to pilot', function () {
    $calendar = schoolAlertCalendarWithEvents();
    schoolAlertYear($calendar);
    $tenant = schoolAlertTenant();
    $user = schoolAlertUser($tenant, schoolAlertSchool($tenant, 'MIXTE'), 'ADMIN_ECOLE');

    $this->withToken($user->createToken('alertes', ["tenant:{$tenant->id}"])->plainTextToken)
        ->getJson(schoolAlertRequest($user))
        ->assertUnprocessable()
        ->assertJsonPath('errors.cycle.0', 'Choisissez le cycle à piloter pour cet établissement mixte.');
});

test('a mixed school pilots the requested cycle', function () {
    $calendar = schoolAlertCalendarWithEvents();
    schoolAlertYear($calendar);
    $tenant = schoolAlertTenant();
    $user = schoolAlertUser($tenant, schoolAlertSchool($tenant, 'MIXTE'), 'ADMIN_ECOLE');

    $this->withToken($user->createToken('alertes', ["tenant:{$tenant->id}"])->plainTextToken)
        ->getJson(schoolAlertRequest($user, '?cycle=SECONDAIRE_TECHNIQUE'))
        ->assertOk()
        ->assertJsonPath('meta.cycle', 'SECONDAIRE_TECHNIQUE')
        ->assertJsonPath('data.0.titre', 'Examen d’État');
});

test('teaching staff receive the teacher audience while direction receives administrative alerts', function () {
    $calendar = schoolAlertCalendarWithEvents();
    schoolAlertYear($calendar);
    $tenant = schoolAlertTenant();
    $school = schoolAlertSchool($tenant, 'PRIMAIRE');
    $teacher = schoolAlertUser($tenant, $school, null);

    $this->withToken($teacher->createToken('alertes', ["tenant:{$tenant->id}"])->plainTextToken)
        ->getJson(schoolAlertRequest($teacher))
        ->assertOk()
        ->assertJsonPath('meta.audience', 'ENSEIGNANT')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.titre', 'ENAFEP');
});

test('the alert endpoint returns an empty pilotage when the active year has no calendar', function () {
    $tenant = schoolAlertTenant();
    $school = schoolAlertSchool($tenant, 'PRIMAIRE');
    $user = schoolAlertUser($tenant, $school, 'ADMIN_ECOLE');
    AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
        'active' => true,
        'cloturee' => false,
    ]);

    $this->withToken($user->createToken('alertes', ["tenant:{$tenant->id}"])->plainTextToken)
        ->getJson(schoolAlertRequest($user))
        ->assertOk()
        ->assertJsonPath('meta.cycle', 'PRIMAIRE')
        ->assertJsonPath('meta.calendrier_id', null)
        ->assertJsonCount(0, 'data');
});

test('the school calendar acts as a daily compass and exposes the whole year for the school cycle', function () {
    $today = CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
    $calendar = schoolAlertCalendarWithEvents();
    schoolAlertYear($calendar);
    $calendar->periodes()->createMany([
        [
            'code' => 'P1_PRIMAIRE', 'nom' => 'Première période', 'type_periode' => 'PERIODE',
            'cycle_scolaire' => 'PRIMAIRE', 'date_debut' => $today->subDays(10)->toDateString(),
            'date_fin' => $today->addDays(10)->toDateString(), 'ordre' => 1,
            'saisie_notes_ouverte' => true, 'periode_active' => true,
        ],
        [
            'code' => 'EXAM_MAT', 'nom' => 'Évaluation maternelle', 'type_periode' => 'EXAMEN',
            'cycle_scolaire' => 'MATERNELLE', 'date_debut' => $today->subDay()->toDateString(),
            'date_fin' => $today->addDay()->toDateString(), 'ordre' => 1,
            'saisie_notes_ouverte' => false, 'periode_active' => true,
        ],
    ]);
    $tenant = schoolAlertTenant();
    $school = schoolAlertSchool($tenant, 'PRIMAIRE');
    $user = schoolAlertUser($tenant, $school, 'ADMIN_ECOLE');

    $this->withToken($user->createToken('calendrier', ["tenant:{$tenant->id}"])->plainTextToken)
        ->getJson('/api/v1/pilotage/calendrier')
        ->assertOk()
        ->assertJsonPath('data.date_reference', $today->toDateString())
        ->assertJsonPath('data.cycle', 'PRIMAIRE')
        ->assertJsonPath('data.audience', 'DIRECTION')
        ->assertJsonPath('data.annee.libelle', '2026-2027')
        ->assertJsonPath('data.boussole.statut', 'PERIODE')
        ->assertJsonPath('data.boussole.titre', 'Première période')
        ->assertJsonPath('data.periode_courante.code', 'P1_PRIMAIRE')
        ->assertJsonPath('data.prochain_evenement.titre', 'ENAFEP')
        ->assertJsonCount(1, 'data.periodes')
        ->assertJsonCount(1, 'data.evenements')
        ->assertJsonCount(1, 'data.alertes');
});

test('a mixed school calendar requires an explicit cycle', function () {
    $calendar = schoolAlertCalendarWithEvents();
    schoolAlertYear($calendar);
    $tenant = schoolAlertTenant();
    $user = schoolAlertUser($tenant, schoolAlertSchool($tenant, 'MIXTE'), 'ADMIN_ECOLE');
    $token = $user->createToken('calendrier-mixte', ["tenant:{$tenant->id}"])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/pilotage/calendrier')
        ->assertUnprocessable()
        ->assertJsonPath('errors.cycle.0', 'Choisissez le cycle à afficher pour cet établissement mixte.');

    $this->withToken($token)
        ->getJson('/api/v1/pilotage/calendrier?cycle=SECONDAIRE_TECHNIQUE')
        ->assertOk()
        ->assertJsonPath('data.cycle', 'SECONDAIRE_TECHNIQUE')
        ->assertJsonPath('data.evenements.0.titre', 'Examen d’État');
});
