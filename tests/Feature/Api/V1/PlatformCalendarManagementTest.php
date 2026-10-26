<?php

use App\Models\AnneePeriodeMapping;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\RolePlateforme;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function calendarManagementPayload(): array
{
    return [
        'code' => 'CAL_TEST', 'libelle' => 'Calendrier test', 'pays' => 'RDC',
        'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
        'officiel' => true, 'actif' => true,
        'profils_cycles' => [[
            'cycle_scolaire' => 'PRIMAIRE', 'mode_decoupage' => 'TRIMESTRIEL',
            'jours_ouvrables_attendus' => 222, 'heures_minimales' => 900,
            'jours_classe' => ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI'],
        ]],
        'periodes' => [[
            'code' => 'P1', 'nom' => 'Première période', 'type_periode' => 'PERIODE',
            'cycle_scolaire' => 'PRIMAIRE',
            'date_debut' => '2026-09-01', 'date_fin' => '2026-10-31',
            'ordre' => 1, 'saisie_notes_ouverte' => false, 'periode_active' => false,
        ]],
        'evenements' => [[
            'code' => 'ENAFEP', 'nom' => 'ENAFEP', 'type_evenement' => 'EXAMEN_NATIONAL',
            'cycle_scolaire' => 'PRIMAIRE', 'date_debut' => '2027-06-08', 'date_fin' => '2027-06-09',
            'audiences' => ['TOUS'], 'niveau_alerte' => 'CRITIQUE', 'delai_alerte_jours' => 30,
            'description' => null,
        ]],
    ];
}

function publishableCalendarPayload(string $code = 'CAL_NATIONAL'): array
{
    $payload = calendarManagementPayload();
    $payload['code'] = $code;
    $payload['profils_cycles'] = [
        ...$payload['profils_cycles'],
        [...$payload['profils_cycles'][0], 'cycle_scolaire' => 'MATERNELLE'],
        [...$payload['profils_cycles'][0], 'cycle_scolaire' => 'SECONDAIRE_TECHNIQUE'],
    ];
    $payload['periodes'][0]['cycle_scolaire'] = 'TOUS';
    $payload['evenements'][0]['cycle_scolaire'] = 'TOUS';

    return $payload;
}

beforeEach(function () {
    RolePlateforme::query()->updateOrCreate(
        ['code' => 'ADMIN_PLATEFORME'],
        ['nom' => 'Administrateur plateforme'],
    );
    $admin = UtilisateurPlateforme::factory()->create(['actif' => true]);
    $admin->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);
    $this->withToken($admin->createToken('calendar-test', ['context:platform'])->plainTextToken);
});

test('calendar CRUD preserves period identifiers and deletes unused calendars', function () {
    $payload = calendarManagementPayload();
    $created = $this->postJson('/api/v1/platform/calendriers', $payload)->assertCreated();
    $id = $created->json('data.id');
    $periodId = $created->json('data.periodes.0.id');
    $this->getJson('/api/v1/platform/calendriers/'.$id)->assertOk()->assertJsonPath('data.utilise', false);
    $this->getJson('/api/v1/platform/calendriers')->assertOk()->assertJsonCount(1, 'data');
    $payload['periodes'][0]['id'] = $periodId;
    $payload['periodes'][0]['nom'] = 'Période modifiée';
    $payload['periodes'][] = [...$payload['periodes'][0], 'id' => null, 'code' => 'E1', 'nom' => 'Examen', 'type_periode' => 'EXAMEN', 'ordre' => 2];
    $this->putJson('/api/v1/platform/calendriers/'.$id, $payload)->assertOk()
        ->assertJsonPath('data.periodes.0.id', $periodId)->assertJsonCount(2, 'data.periodes');
    array_pop($payload['periodes']);
    $this->putJson('/api/v1/platform/calendriers/'.$id, $payload)->assertOk()->assertJsonCount(1, 'data.periodes');
    $this->deleteJson('/api/v1/platform/calendriers/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('calendriers_scolaires', ['id' => $id]);
    $this->assertDatabaseMissing('calendrier_periodes', ['id' => $periodId]);
});

test('used calendars protect dates periods and deletion while allowing metadata changes', function () {
    $payload = calendarManagementPayload();
    $created = $this->postJson('/api/v1/platform/calendriers', $payload)->assertCreated();
    $id = $created->json('data.id');
    $payload['periodes'][0]['id'] = $created->json('data.periodes.0.id');
    $payload['profils_cycles'][0]['id'] = $created->json('data.profils_cycles.0.id');
    $payload['evenements'][0]['id'] = $created->json('data.evenements.0.id');
    AnneeScolaire::query()->create(['calendrier_scolaire_id' => $id, 'libelle' => '2026-2027', 'date_debut' => $payload['date_debut'], 'date_fin' => $payload['date_fin'], 'active' => false, 'cloturee' => true]);
    $this->getJson('/api/v1/platform/calendriers/'.$id)->assertOk()->assertJsonPath('data.utilise', true)->assertJsonPath('data.annees_count', 1);
    $payload['libelle'] = 'Libellé modifié';
    $payload['actif'] = false;
    $this->putJson('/api/v1/platform/calendriers/'.$id, $payload)->assertOk()->assertJsonPath('data.actif', false);
    $this->putJson('/api/v1/platform/calendriers/'.$id, [...$payload, 'date_fin' => '2027-08-01'])->assertStatus(409);
    $this->putJson('/api/v1/platform/calendriers/'.$id, [...$payload, 'periodes' => []])->assertStatus(409);
    $this->deleteJson('/api/v1/platform/calendriers/'.$id)->assertStatus(409);
    expect(CalendrierScolaire::findOrFail($id)->periodes()->count())->toBe(1);
});

test('calendar validation rejects inconsistent dates and duplicate periods', function () {
    $payload = calendarManagementPayload();
    $payload['periodes'][0]['date_fin'] = '2028-01-01';
    $this->postJson('/api/v1/platform/calendriers', $payload)->assertUnprocessable()->assertJsonValidationErrors('periodes.0.date_fin');
    $payload = calendarManagementPayload();
    $payload['periodes'][] = $payload['periodes'][0];
    $this->postJson('/api/v1/platform/calendriers', $payload)->assertUnprocessable()->assertJsonValidationErrors(['periodes.0.code', 'periodes.0.ordre']);
    $payload = calendarManagementPayload();
    $payload['periodes'][0]['id'] = 999999;
    $this->postJson('/api/v1/platform/calendriers', $payload)->assertUnprocessable()->assertJsonValidationErrors('periodes.0.id');
    $this->assertDatabaseCount('calendriers_scolaires', 0);
});

test('calendar validation rejects two active periods and unknown fields', function () {
    $payload = calendarManagementPayload();
    $payload['periodes'][0]['periode_active'] = true;
    $payload['periodes'][] = [...$payload['periodes'][0], 'code' => 'P2', 'ordre' => 2];
    $this->postJson('/api/v1/platform/calendriers', $payload)->assertUnprocessable()->assertJsonValidationErrors('periodes');
    $this->postJson('/api/v1/platform/calendriers', [...calendarManagementPayload(), 'tenant_id' => 123])->assertUnprocessable();
});

test('calendar profiles may share school days but reject a duplicate within one cycle', function () {
    $payload = publishableCalendarPayload();
    $this->postJson('/api/v1/platform/calendriers', $payload)->assertCreated();

    $payload['code'] = 'CAL_DUPLICATE_DAY';
    $payload['profils_cycles'][0]['jours_classe'][] = 'LUNDI';
    $this->postJson('/api/v1/platform/calendriers', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profils_cycles.0.jours_classe');
});

test('calendar period codes can be swapped without replacing their identifiers', function () {
    $payload = calendarManagementPayload();
    $payload['periodes'][] = [...$payload['periodes'][0], 'code' => 'P2', 'ordre' => 2];
    $created = $this->postJson('/api/v1/platform/calendriers', $payload)->assertCreated();
    $id = $created->json('data.id');
    foreach ($payload['periodes'] as $index => &$period) {
        $period['id'] = $created->json('data.periodes.'.$index.'.id');
        $period['code'] = $index === 0 ? 'P2' : 'P1';
    }
    unset($period);
    $this->putJson('/api/v1/platform/calendriers/'.$id, $payload)->assertOk()->assertJsonPath('data.periodes.0.code', 'P2')->assertJsonPath('data.periodes.0.id', $payload['periodes'][0]['id']);
});

test('calendar linked through a period mapping cannot be deleted', function () {
    $created = $this->postJson('/api/v1/platform/calendriers', calendarManagementPayload())->assertCreated();
    $year = AnneeScolaire::query()->create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => false, 'cloturee' => false]);
    $mapping = AnneePeriodeMapping::query()->create(['annee_scolaire_id' => $year->id, 'calendrier_periode_id' => $created->json('data.periodes.0.id'), 'code_periode_template' => 'P1']);
    $this->getJson('/api/v1/platform/calendriers/'.$created->json('data.id'))->assertOk()->assertJsonPath('data.annees_count', 0)->assertJsonPath('data.utilise', true);
    $this->deleteJson('/api/v1/platform/calendriers/'.$created->json('data.id'))->assertStatus(409);
    $this->assertDatabaseHas('annee_periode_mappings', ['id' => $mapping->id]);
});

test('a complete national calendar can be published once as the global school year', function () {
    $calendar = $this->postJson('/api/v1/platform/calendriers', publishableCalendarPayload())
        ->assertCreated()
        ->json('data');

    $this->postJson("/api/v1/platform/calendriers/{$calendar['id']}/publication")
        ->assertCreated()
        ->assertJsonPath('data.libelle', '2026-2027')
        ->assertJsonPath('data.calendrier_scolaire_id', $calendar['id'])
        ->assertJsonPath('data.active', false)
        ->assertJsonPath('meta.created', true)
        ->assertJsonPath('meta.scope', 'GLOBAL');

    $this->postJson("/api/v1/platform/calendriers/{$calendar['id']}/publication")
        ->assertOk()
        ->assertJsonPath('meta.created', false);

    $this->getJson('/api/v1/platform/calendriers')
        ->assertOk()
        ->assertJsonPath('data.0.annees_scolaires.0.libelle', '2026-2027');

    $this->assertDatabaseCount('annees_scolaires', 1);
});

test('publication requires a complete program for all three school cycles', function () {
    $calendar = $this->postJson('/api/v1/platform/calendriers', calendarManagementPayload())
        ->assertCreated()
        ->json('data');

    $this->postJson("/api/v1/platform/calendriers/{$calendar['id']}/publication")
        ->assertConflict()
        ->assertJsonPath('detail', fn (string $detail): bool => str_contains($detail, 'maternelle')
            && str_contains($detail, 'secondaire/technique'));

    $this->assertDatabaseCount('annees_scolaires', 0);
});

test('only one national calendar can be published for a school year', function () {
    $first = $this->postJson('/api/v1/platform/calendriers', publishableCalendarPayload('CAL_NATIONAL_A'))
        ->assertCreated()
        ->json('data');
    $second = $this->postJson('/api/v1/platform/calendriers', publishableCalendarPayload('CAL_NATIONAL_B'))
        ->assertCreated()
        ->json('data');

    $this->postJson("/api/v1/platform/calendriers/{$first['id']}/publication")
        ->assertCreated();
    $this->postJson("/api/v1/platform/calendriers/{$second['id']}/publication")
        ->assertConflict()
        ->assertJsonPath('detail', 'L’année scolaire 2026-2027 utilise déjà un autre calendrier national.');

    $this->assertDatabaseCount('annees_scolaires', 1);
});
