<?php

use Database\Seeders\ProductionReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('production reference data is idempotent and preserves operational state', function (): void {
    config()->set('seeding.platform_admin_password', 'initial-platform-password');

    $this->seed(ProductionReferenceSeeder::class);

    $countsAfterFirstRun = collect([
        'plans',
        'ref_cycles',
        'ref_degres',
        'ref_niveaux',
        'ref_options',
        'ref_matieres',
        'calendriers_scolaires',
        'calendrier_periodes',
        'calendrier_evenements',
        'annees_scolaires',
        'annee_periode_mappings',
        'system_templates_bulletin',
        'system_template_nodes',
        'system_template_periodes',
        'system_template_maxima',
        'types_evaluations',
        'roles_plateforme',
        'utilisateurs_plateforme',
    ])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

    $calendarId = DB::table('calendriers_scolaires')->where('code', 'RDC_2026_2027')->value('id');
    $periodId = DB::table('calendrier_periodes')
        ->where('calendrier_scolaire_id', $calendarId)
        ->where('code', 'P1')
        ->value('id');
    $schoolYearId = DB::table('annees_scolaires')->where('libelle', '2026-2027')->value('id');
    $mappingId = DB::table('annee_periode_mappings')
        ->where('annee_scolaire_id', $schoolYearId)
        ->where('calendrier_periode_id', $periodId)
        ->value('id');
    $passwordHash = DB::table('utilisateurs_plateforme')
        ->where('email', 'admin@bulletinlab.app')
        ->value('mot_de_passe');

    DB::table('calendriers_scolaires')->where('id', $calendarId)->update(['actif' => false]);
    DB::table('calendrier_periodes')->where('id', $periodId)->update([
        'saisie_notes_ouverte' => true,
        'periode_active' => true,
    ]);
    DB::table('annees_scolaires')->where('id', $schoolYearId)->update([
        'active' => false,
        'cloturee' => true,
    ]);
    DB::table('annee_periode_mappings')->where('id', $mappingId)->update([
        'saisie_ouverte' => true,
        'verrouillee' => true,
    ]);

    config()->set('seeding.platform_admin_password', 'replacement-platform-password');
    $this->seed(ProductionReferenceSeeder::class);

    $countsAfterSecondRun = $countsAfterFirstRun->keys()
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

    expect($countsAfterSecondRun->all())->toBe($countsAfterFirstRun->all())
        ->and(DB::table('tenants')->count())->toBe(0)
        ->and(DB::table('etablissements')->count())->toBe(0)
        ->and(DB::table('utilisateurs')->count())->toBe(0)
        ->and(DB::table('classes')->count())->toBe(0)
        ->and((bool) DB::table('calendriers_scolaires')->where('id', $calendarId)->value('actif'))->toBeFalse()
        ->and((bool) DB::table('calendrier_periodes')->where('id', $periodId)->value('saisie_notes_ouverte'))->toBeTrue()
        ->and((bool) DB::table('calendrier_periodes')->where('id', $periodId)->value('periode_active'))->toBeTrue()
        ->and((bool) DB::table('annees_scolaires')->where('id', $schoolYearId)->value('active'))->toBeFalse()
        ->and((bool) DB::table('annees_scolaires')->where('id', $schoolYearId)->value('cloturee'))->toBeTrue()
        ->and((bool) DB::table('annee_periode_mappings')->where('id', $mappingId)->value('saisie_ouverte'))->toBeTrue()
        ->and((bool) DB::table('annee_periode_mappings')->where('id', $mappingId)->value('verrouillee'))->toBeTrue()
        ->and(DB::table('utilisateurs_plateforme')->where('email', 'admin@bulletinlab.app')->value('mot_de_passe'))->toBe($passwordHash)
        ->and(Hash::check('replacement-platform-password', $passwordHash))->toBeFalse();
});
