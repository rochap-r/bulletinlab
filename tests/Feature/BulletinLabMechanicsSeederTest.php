<?php

use App\Domains\Bulletin\Services\ApercuEleveService;
use App\Models\Note;
use App\Models\SystemTemplateBulletin;
use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabAutomotiveDemoSeeder;
use Database\Seeders\BulletinLabCalendarSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabGeneralMechanicsDemoSeeder;
use Database\Seeders\BulletinLabLayoutsSeeder;
use Database\Seeders\BulletinLabSchoolYearSeeder;
use Database\Seeders\BulletinLabTemplateCatalogSeeder;
use Database\Seeders\BulletinLabTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([BulletinLabCoreSeeder::class, BulletinLabAcademicSeeder::class, BulletinLabLayoutsSeeder::class,
        BulletinLabCalendarSeeder::class, BulletinLabSchoolYearSeeder::class, BulletinLabTemplateCatalogSeeder::class,
        BulletinLabTestDataSeeder::class, BulletinLabAutomotiveDemoSeeder::class, BulletinLabGeneralMechanicsDemoSeeder::class]);
});

test('les huit classes mecaniques respectent les cours maxima et notes des PDF', function (): void {
    foreach (['AUTO' => [[20, 116, 3280], [20, 118, 3360], [21, 124, 3200], [18, 106, 3600]],
        'GENERALE' => [[19, 112, 4000], [17, 100, 3680], [16, 94, 3520], [17, 100, 3520]]] as $option => $years) {
        foreach ($years as $index => [$subjects, $notes, $maximum]) {
            $year = $index + 1;
            $template = SystemTemplateBulletin::query()->where('code', 'MECANIQUE_'.$option.'_'.$year)->firstOrFail();
            $preview = app(ApercuEleveService::class)->forTemplate($template);
            expect($template->nodes()->where('type_node', 'MATIERE')->count())->toBe($subjects)
                ->and((int) $template->total_general_maxima)->toBe($maximum)
                ->and($preview)->not->toBeNull()->and($preview['notes'])->toHaveCount($notes)
                ->and($preview['eleve']['num_permanent'])->toBe(($option === 'AUTO' ? 'DEMOMA00' : 'DEMOMG00').$year)
                ->and($template->periodes()->where('type_periode', 'REPECHAGE')->where('actif', true)->count())->toBe($year === 4 ? 0 : 2);
            foreach ($preview['notes'] as $note) {
                $max = DB::table('system_template_maxima')->where('node_id', $note['node_id'])->where('periode_id', $note['periode_id'])->value('maxima');
                expect((float) $note['valeur'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual((float) $max);
            }
        }
    }
});

test('les ateliers ne recoivent pas de notes examen et conservent leur bareme imprime', function (): void {
    $template = SystemTemplateBulletin::query()->where('code', 'MECANIQUE_AUTO_1')->firstOrFail();
    $workshop = $template->nodes()->where('libelle', 'Atelier ajustage')->firstOrFail();
    $maxima = DB::table('system_template_maxima')->join('system_template_periodes', 'system_template_periodes.id', '=', 'system_template_maxima.periode_id')
        ->where('node_id', $workshop->id)->pluck('maxima', 'code')->map(fn ($value): int => (int) $value)->all();
    expect($maxima)->toBe(['P1' => 50, 'P2' => 50, 'TOTAL_S1' => 100, 'P3' => 50, 'P4' => 50, 'TOTAL_S2' => 100, 'TOTAL_GENERAL' => 200]);
});

test('relancer les seeders mecaniques preserve les identifiants et les notes modifiees', function (): void {
    $identifiers = DB::table('eleves')->orderBy('id')->pluck('public_id', 'id')->all();
    $note = Note::query()->firstOrFail();
    $note->update(['valeur' => 1]);
    $counts = [DB::table('classes')->count(), DB::table('modeles_bulletin')->count(), DB::table('notes')->count(), DB::table('system_template_nodes')->count()];
    $this->seed([BulletinLabAutomotiveDemoSeeder::class, BulletinLabGeneralMechanicsDemoSeeder::class]);
    expect(DB::table('eleves')->orderBy('id')->pluck('public_id', 'id')->all())->toBe($identifiers)
        ->and([DB::table('classes')->count(), DB::table('modeles_bulletin')->count(), DB::table('notes')->count(), DB::table('system_template_nodes')->count()])->toBe($counts)
        ->and((float) $note->fresh()->valeur)->toBe(1.0);
});

test('les seeders de demonstration mecaniques ne creent pas de donnees eleves en production', function (): void {
    $this->app->instance('env', 'production');
    $count = DB::table('eleves')->count();
    app(BulletinLabAutomotiveDemoSeeder::class)->run();
    app(BulletinLabGeneralMechanicsDemoSeeder::class)->run();
    expect(DB::table('eleves')->count())->toBe($count);
});
