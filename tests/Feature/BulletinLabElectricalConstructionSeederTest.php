<?php

use App\Domains\Bulletin\Services\ApercuEleveService;
use App\Models\Note;
use App\Models\SystemTemplateBulletin;
use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCalendarSeeder;
use Database\Seeders\BulletinLabConstructionDemoSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabElectricityDemoSeeder;
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
        BulletinLabTestDataSeeder::class, BulletinLabElectricityDemoSeeder::class, BulletinLabConstructionDemoSeeder::class]);
});

test('les huit classes electricite et construction ont leurs maxima et exemples complets', function (): void {
    foreach (['ELECTRICITE' => [[24, 140, 5440], [23, 134, 5120], [19, 110, 4320], [21, 122, 5200]],
        'CONSTRUCTION' => [[21, 124, 4080], [21, 124, 4080], [21, 124, 4080], [21, 124, 4320]]] as $option => $years) {
        foreach ($years as $index => [$subjects, $notes, $maximum]) {
            $year = $index + 1;
            $template = SystemTemplateBulletin::query()->where('code', $option.'_'.$year)->firstOrFail();
            $preview = app(ApercuEleveService::class)->forTemplate($template);
            expect($template->nodes()->where('type_node', 'MATIERE')->count())->toBe($subjects)
                ->and((int) $template->total_general_maxima)->toBe($maximum)
                ->and($preview)->not->toBeNull()->and($preview['notes'])->toHaveCount($notes)
                ->and($preview['eleve']['num_permanent'])->toBe(($option === 'ELECTRICITE' ? 'DEMOEL00' : 'DEMOCN00').$year)
                ->and($template->niveauMin->code)->toBe('HUM_'.$year)
                ->and($template->periodes()->where('type_periode', 'REPECHAGE')->where('actif', true)->count())->toBe($year === 4 ? 0 : 2);
            foreach ($preview['notes'] as $note) {
                $max = DB::table('system_template_maxima')->where('node_id', $note['node_id'])->where('periode_id', $note['periode_id'])->value('maxima');
                expect((float) $note['valeur'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual((float) $max);
            }
        }
    }
    $combined = SystemTemplateBulletin::query()->where('code', 'CONSTRUCTION_1_2')->firstOrFail();
    expect($combined->nodes()->where('type_node', 'MATIERE')->count())->toBe(21);
});

test('les deux laboratoires de quatrieme electricite restent distincts selon le PDF', function (): void {
    $template = SystemTemplateBulletin::query()->where('code', 'ELECTRICITE_4')->firstOrFail();
    $labs = $template->nodes()->with('parent')->where('libelle', 'Labo. électrique')->get()->sortBy(fn ($node): int => $node->parent->ordre)->values();
    expect($labs)->toHaveCount(2)->and($labs[0]->id)->not->toBe($labs[1]->id);
    foreach ($labs as $index => $lab) {
        $maxima = DB::table('system_template_maxima')->join('system_template_periodes', 'system_template_periodes.id', '=', 'system_template_maxima.periode_id')
            ->where('node_id', $lab->id)->pluck('maxima', 'code')->map(fn ($value): int => (int) $value)->all();
        expect($maxima['P1'])->toBe($index === 0 ? 50 : 100)->and($maxima['TOTAL_S1'])->toBe(200)->and($maxima['TOTAL_GENERAL'])->toBe(400);
        if ($index === 0) {
            expect($maxima['EXAM_S1'])->toBe(100)->and($maxima['EXAM_S2'])->toBe(100);
        } else {
            expect($maxima)->not->toHaveKey('EXAM_S1')->not->toHaveKey('EXAM_S2');
        }
    }
});

test('relancer les exemples electricite et construction ne duplique ni ne remplace les notes', function (): void {
    $identifiers = DB::table('eleves')->orderBy('id')->pluck('public_id', 'id')->all();
    $note = Note::query()->firstOrFail();
    $note->update(['valeur' => 1]);
    $counts = [DB::table('classes')->count(), DB::table('modeles_bulletin')->count(), DB::table('notes')->count(), DB::table('system_template_nodes')->count()];
    $this->seed([BulletinLabElectricityDemoSeeder::class, BulletinLabConstructionDemoSeeder::class]);
    expect(DB::table('eleves')->orderBy('id')->pluck('public_id', 'id')->all())->toBe($identifiers)
        ->and([DB::table('classes')->count(), DB::table('modeles_bulletin')->count(), DB::table('notes')->count(), DB::table('system_template_nodes')->count()])->toBe($counts)
        ->and((float) $note->fresh()->valeur)->toBe(1.0);
});

test('les exemples electricite et construction sont exclus de la production', function (): void {
    $this->app->instance('env', 'production');
    $count = DB::table('eleves')->count();
    app(BulletinLabElectricityDemoSeeder::class)->run();
    app(BulletinLabConstructionDemoSeeder::class)->run();
    expect(DB::table('eleves')->count())->toBe($count);
});
