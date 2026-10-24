<?php

use App\Domains\Bulletin\Services\ApercuEleveService;
use App\Models\SystemTemplateBulletin;
use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCalendarSeeder;
use Database\Seeders\BulletinLabCommercialeSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabCtebSeeder;
use Database\Seeders\BulletinLabLayoutsSeeder;
use Database\Seeders\BulletinLabModelBulletinSeeder;
use Database\Seeders\BulletinLabScenarioEleveSeeder;
use Database\Seeders\BulletinLabSchoolYearSeeder;
use Database\Seeders\BulletinLabTemplateCatalogSeeder;
use Database\Seeders\BulletinLabTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function seedCtebDemoData(): void
{
    test()->seed([
        BulletinLabCoreSeeder::class,
        BulletinLabAcademicSeeder::class,
        BulletinLabLayoutsSeeder::class,
        BulletinLabCalendarSeeder::class,
        BulletinLabSchoolYearSeeder::class,
        BulletinLabTemplateCatalogSeeder::class,
        BulletinLabCtebSeeder::class,
        BulletinLabCommercialeSeeder::class,
        BulletinLabTestDataSeeder::class,
        BulletinLabModelBulletinSeeder::class,
        BulletinLabScenarioEleveSeeder::class,
    ]);
}

test('les configurations CTEB possedent chacune une classe et un eleve de demonstration', function (): void {
    seedCtebDemoData();

    foreach (['CTEB_7' => '7ème EB A', 'CTEB_8' => '8ème EB A'] as $templateCode => $className) {
        $template = SystemTemplateBulletin::query()->where('code', $templateCode)->firstOrFail();
        $classId = DB::table('classes')
            ->where('system_template_id', $template->id)
            ->where('nom', $className)
            ->value('id');

        expect($classId)->not->toBeNull()
            ->and(DB::table('inscriptions')->where('classe_id', $classId)->count())->toBe(1);
    }
});

test('chaque configuration CTEB expose un eleve avec des notes dans apercu', function (): void {
    seedCtebDemoData();

    foreach (['CTEB_7', 'CTEB_8'] as $templateCode) {
        $template = SystemTemplateBulletin::query()->where('code', $templateCode)->firstOrFail();
        $previewStudent = app(ApercuEleveService::class)->forTemplate($template);

        expect($previewStudent)->not->toBeNull()
            ->and($previewStudent['classe'])->toContain(str_replace('CTEB_', '', $templateCode).'ème EB')
            ->and($previewStudent['notes'])->not->toBeEmpty();
    }
});

test('le seeding des donnees CTEB reste idempotent', function (): void {
    seedCtebDemoData();
    $classCount = DB::table('classes')->whereIn('code', ['7EBA', '8EBA'])->count();
    $studentCount = DB::table('eleves')->whereIn('num_permanent', ['25EBA7001', '25EBA8001'])->count();

    seedCtebDemoData();

    expect(DB::table('classes')->whereIn('code', ['7EBA', '8EBA'])->count())->toBe($classCount)
        ->and(DB::table('eleves')->whereIn('num_permanent', ['25EBA7001', '25EBA8001'])->count())->toBe($studentCount);
});

test('le seeding ne nomme jamais un enseignant titulaire de deux classes la meme annee', function (): void {
    seedCtebDemoData();

    $classCounts = DB::table('classes')
        ->whereNotNull('titulaire_id')
        ->selectRaw('annee_scolaire_id, titulaire_id, COUNT(*) as total')
        ->groupBy('annee_scolaire_id', 'titulaire_id')
        ->pluck('total');

    expect($classCounts)->not->toBeEmpty()
        ->and($classCounts->every(fn (int|string $count): bool => (int) $count === 1))->toBeTrue();
});
