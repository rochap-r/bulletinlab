<?php

use App\Domains\Bulletin\Services\ApercuEleveService;
use App\Models\Note;
use App\Models\SystemTemplateBulletin;
use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCalendarSeeder;
use Database\Seeders\BulletinLabCommercialDemoSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
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
        BulletinLabTestDataSeeder::class, BulletinLabCommercialDemoSeeder::class]);
});

test('les quatre classes commerciales respectent les cours et maxima du formulaire officiel', function (): void {
    foreach ([[18, 104, 3360], [18, 104, 3360], [20, 116, 3400], [20, 116, 3400]] as $index => [$subjects, $notes, $maximum]) {
        $year = $index + 1;
        $template = SystemTemplateBulletin::query()->where('code', 'COMMERCIALE_'.$year)->firstOrFail();
        $preview = app(ApercuEleveService::class)->forTemplate($template);
        expect($template->nodes()->where('type_node', 'MATIERE')->count())->toBe($subjects)
            ->and((int) $template->total_general_maxima)->toBe($maximum)
            ->and($preview)->not->toBeNull()->and($preview['notes'])->toHaveCount($notes)
            ->and($preview['eleve']['num_permanent'])->toBe('DEMOCG00'.$year)
            ->and($template->periodes()->where('type_periode', 'REPECHAGE')->where('actif', true)->count())->toBe($year === 4 ? 0 : 2);
        foreach ($preview['notes'] as $note) {
            $max = DB::table('system_template_maxima')->where('node_id', $note['node_id'])->where('periode_id', $note['periode_id'])->value('maxima');
            expect((float) $note['valeur'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual((float) $max);
        }
    }
});

test('les activites et la pratique commerciales conservent les cases hachurees et totaux imprimes', function (): void {
    foreach (range(1, 4) as $year) {
        $template = SystemTemplateBulletin::query()->where('code', 'COMMERCIALE_'.$year)->firstOrFail();
        $nodes = $template->nodes()->where('type_node', 'MATIERE')->get()->filter(fn ($node): bool => (bool) ($node->metadata['examens_non_applicables'] ?? false));
        expect($nodes)->toHaveCount(2);
        foreach ($nodes as $node) {
            $maximum = $node->libelle === 'Pratique professionnelle' ? 100 : ($year <= 2 ? 20 : 10);
            $maxima = DB::table('system_template_maxima')->join('system_template_periodes', 'system_template_periodes.id', '=', 'system_template_maxima.periode_id')
                ->where('node_id', $node->id)->orderBy('system_template_periodes.ordre')->pluck('maxima', 'code')->map(fn ($value): int => (int) $value)->all();
            expect($maxima)->toBe(['P1' => $maximum, 'P2' => $maximum, 'TOTAL_S1' => 2 * $maximum,
                'P3' => $maximum, 'P4' => $maximum, 'TOTAL_S2' => 2 * $maximum, 'TOTAL_GENERAL' => 4 * $maximum]);
        }
    }
});

test('les exemples commerciaux conservent les identifiants clones et notes saisies apres relance', function (): void {
    $nodeIds = DB::table('modele_nodes')->orderBy('id')->pluck('system_node_id', 'id')->all();
    expect($nodeIds)->not->toBeEmpty();
    $note = Note::query()->firstOrFail();
    $note->update(['valeur' => 1]);
    $counts = [DB::table('classes')->count(), DB::table('modeles_bulletin')->count(), DB::table('notes')->count(), DB::table('system_template_nodes')->count()];
    $this->seed(BulletinLabCommercialDemoSeeder::class);
    expect(DB::table('modele_nodes')->orderBy('id')->pluck('system_node_id', 'id')->all())->toBe($nodeIds)
        ->and([DB::table('classes')->count(), DB::table('modeles_bulletin')->count(), DB::table('notes')->count(), DB::table('system_template_nodes')->count()])->toBe($counts)
        ->and((float) $note->fresh()->valeur)->toBe(1.0);
});

test('les exemples commerciaux ne creent aucun eleve en production', function (): void {
    $this->app->instance('env', 'production');
    $count = DB::table('eleves')->count();
    app(BulletinLabCommercialDemoSeeder::class)->run();
    expect(DB::table('eleves')->count())->toBe($count);
});
