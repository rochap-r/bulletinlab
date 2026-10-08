<?php

use App\Domains\Bulletin\Services\ApercuEleveService;
use App\Models\Note;
use App\Models\SystemTemplateBulletin;
use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCalendarSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabLayoutsSeeder;
use Database\Seeders\BulletinLabSchoolYearSeeder;
use Database\Seeders\BulletinLabScientificDemoSeeder;
use Database\Seeders\BulletinLabTemplateCatalogSeeder;
use Database\Seeders\BulletinLabTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        BulletinLabCoreSeeder::class, BulletinLabAcademicSeeder::class, BulletinLabLayoutsSeeder::class,
        BulletinLabCalendarSeeder::class, BulletinLabSchoolYearSeeder::class,
        BulletinLabTemplateCatalogSeeder::class, BulletinLabTestDataSeeder::class,
    ]);
});

test('les quatre bulletins scientifiques possedent chacun un eleve exemple et six notes par matiere dans la preview', function (): void {
    $this->seed(BulletinLabScientificDemoSeeder::class);

    foreach (range(1, 4) as $year) {
        $template = SystemTemplateBulletin::query()->where('code', 'SCIENTIFIQUE_'.$year)->firstOrFail();
        $classId = DB::table('classes')->where('system_template_id', $template->id)->value('id');
        expect(DB::table('inscriptions')->where('classe_id', $classId)->count())->toBe(1);
        $preview = app(ApercuEleveService::class)->forTemplate($template);
        expect($preview)->not->toBeNull()
            ->and($preview['eleve']['postnom'])->toBe('Exemple')
            ->and($preview['classe'])->toContain('Démonstration')
            ->and($preview['notes'])->toHaveCount(($year <= 2 ? 18 : 19) * 6);

        foreach ($preview['notes'] as $note) {
            $maximum = DB::table('system_template_maxima')->where('node_id', $note['node_id'])
                ->where('periode_id', $note['periode_id'])->value('maxima');
            expect((float) $note['valeur'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual((float) $maximum);
        }
    }
});

test('le seeder scientifique conserve les identifiants les notes modifiees et les autres eleves a la relance', function (): void {
    $this->seed(BulletinLabScientificDemoSeeder::class);
    $identifiers = DB::table('eleves')->orderBy('id')->pluck('public_id', 'id')->all();
    $classes = DB::table('classes')->count();
    $models = DB::table('modeles_bulletin')->count();
    $note = Note::query()->firstOrFail();
    $note->update(['valeur' => 1]);
    $notes = DB::table('notes')->count();
    $this->seed(BulletinLabScientificDemoSeeder::class);
    expect(DB::table('eleves')->orderBy('id')->pluck('public_id', 'id')->all())->toBe($identifiers)
        ->and(DB::table('classes')->count())->toBe($classes)
        ->and(DB::table('modeles_bulletin')->count())->toBe($models)
        ->and(DB::table('notes')->count())->toBe($notes)
        ->and((float) $note->fresh()->valeur)->toBe(1.0);
});

test('les maxima scientifiques imprimes ne sont pas corriges silencieusement', function (): void {
    $this->seed(BulletinLabScientificDemoSeeder::class);
    foreach ([['SCIENTIFIQUE_3', 'Systématique et Jardin.', 'EXAM_S1', 20], ['SCIENTIFIQUE_3', 'Systématique et Jardin.', 'TOTAL_GENERAL', 80], ['SCIENTIFIQUE_4', 'Anglais', 'P3', 40], ['SCIENTIFIQUE_4', 'Anglais', 'TOTAL_GENERAL', 320]] as [$code, $label, $periodCode, $maximum]) {
        $templateId = DB::table('system_templates_bulletin')->where('code', $code)->value('id');
        $nodeId = DB::table('system_template_nodes')->where('template_id', $templateId)->where('libelle', $label)->value('id');
        $periodId = DB::table('system_template_periodes')->where('template_id', $templateId)->where('code', $periodCode)->value('id');
        expect((int) DB::table('system_template_maxima')->where('node_id', $nodeId)->where('periode_id', $periodId)->value('maxima'))->toBe($maximum);
    }
});
