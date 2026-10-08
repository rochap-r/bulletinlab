<?php

use App\Domains\Bulletin\Services\ApercuEleveService;
use App\Models\Note;
use App\Models\SystemTemplateBulletin;
use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCalendarSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabLayoutsSeeder;
use Database\Seeders\BulletinLabPedagogyDemoSeeder;
use Database\Seeders\BulletinLabSchoolYearSeeder;
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
        BulletinLabPedagogyDemoSeeder::class,
    ]);
});

test('chaque classe pedagogique expose un eleve et les notes applicables dans la preview', function (): void {
    foreach ([1 => [20, 120, 4240], 2 => [22, 132, 4720], 3 => [22, 130, 5600], 4 => [22, 130, 5840]] as $year => [$subjects, $notes, $maximum]) {
        $template = SystemTemplateBulletin::query()->where('code', 'PEDAGOGIE_'.$year)->firstOrFail();
        $preview = app(ApercuEleveService::class)->forTemplate($template);
        expect($preview)->not->toBeNull()
            ->and($preview['classe'])->toContain('Pédagogie générale', 'Démonstration')
            ->and($preview['eleve']['num_permanent'])->toBe('DEMOPG00'.$year)
            ->and($preview['annee_scolaire'])->toBe('2026-2027')
            ->and($preview['notes'])->toHaveCount($notes)
            ->and((int) $template->total_general_maxima)->toBe($maximum)
            ->and($template->nodes()->where('type_node', 'MATIERE')->count())->toBe($subjects);
        foreach ($preview['notes'] as $note) {
            $max = DB::table('system_template_maxima')->where('node_id', $note['node_id'])->where('periode_id', $note['periode_id'])->value('maxima');
            expect((float) $note['valeur'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual((float) $max);
        }
    }
});

test('la pratique enseignement conserve les maxima imprimes et ne recoit aucune note examen', function (): void {
    foreach ([3, 4] as $year) {
        $template = SystemTemplateBulletin::query()->where('code', 'PEDAGOGIE_'.$year)->firstOrFail();
        $subject = $template->nodes()->where('libelle', 'Pratique d’Enseignement')->firstOrFail();
        $examIds = $template->periodes()->where('type_periode', 'EXAMEN')->pluck('id');
        expect(DB::table('system_template_maxima')->where('node_id', $subject->id)->whereIn('periode_id', $examIds)->count())->toBe(0);
        $maxima = DB::table('system_template_maxima')->join('system_template_periodes', 'system_template_periodes.id', '=', 'system_template_maxima.periode_id')
            ->where('system_template_maxima.node_id', $subject->id)->pluck('system_template_maxima.maxima', 'system_template_periodes.code')->map(fn ($value): int => (int) $value)->all();
        expect($maxima)->toMatchArray(['P1' => 100, 'P2' => 100, 'TOTAL_S1' => 200, 'P3' => 100, 'P4' => 100, 'TOTAL_S2' => 200, 'TOTAL_GENERAL' => 400]);
    }
    $terminalId = DB::table('system_templates_bulletin')->where('code', 'PEDAGOGIE_4')->value('id');
    expect(DB::table('system_template_periodes')->where('template_id', $terminalId)->where('type_periode', 'REPECHAGE')->where('actif', true)->count())->toBe(0);
});

test('la relance pedagogique conserve les autres eleves identifiants et notes modifiees', function (): void {
    $identifiers = DB::table('eleves')->orderBy('id')->pluck('public_id', 'id')->all();
    $classCount = DB::table('classes')->count();
    $modelCount = DB::table('modeles_bulletin')->count();
    $note = Note::query()->firstOrFail();
    $note->update(['valeur' => 1]);
    $noteCount = DB::table('notes')->count();
    $this->seed(BulletinLabPedagogyDemoSeeder::class);
    expect(DB::table('eleves')->orderBy('id')->pluck('public_id', 'id')->all())->toBe($identifiers)
        ->and(DB::table('classes')->count())->toBe($classCount)
        ->and(DB::table('modeles_bulletin')->count())->toBe($modelCount)
        ->and(DB::table('notes')->count())->toBe($noteCount)
        ->and((float) $note->fresh()->valeur)->toBe(1.0);
});
