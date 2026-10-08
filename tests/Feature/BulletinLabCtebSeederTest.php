<?php

use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabCtebSeeder;
use Database\Seeders\BulletinLabLayoutsSeeder;
use Database\Seeders\BulletinLabTemplateCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(BulletinLabCoreSeeder::class);
    $this->seed(BulletinLabAcademicSeeder::class);
    $this->seed(BulletinLabLayoutsSeeder::class);
    $this->seed(BulletinLabTemplateCatalogSeeder::class);
});

function ctebTemplateId(string $code): int
{
    return (int) DB::table('system_templates_bulletin')->where('code', $code)->value('id');
}

test('le seeder configure les maxima officiels des septieme et huitieme EB', function (): void {
    $this->seed(BulletinLabCtebSeeder::class);

    expect(DB::table('system_templates_bulletin')->where('code', 'CTEB_7')->value('total_general_maxima'))
        ->toEqual(3200)
        ->and(DB::table('system_templates_bulletin')->where('code', 'CTEB_8')->value('total_general_maxima'))
        ->toEqual(3360);

    foreach (['CTEB_7' => 3200, 'CTEB_8' => 3360] as $code => $expectedTotal) {
        $templateId = ctebTemplateId($code);
        $totalGeneralId = DB::table('system_template_periodes')
            ->where('template_id', $templateId)
            ->where('code', 'TOTAL_GENERAL')
            ->value('id');

        expect((int) DB::table('system_template_maxima')
            ->where('template_id', $templateId)
            ->where('periode_id', $totalGeneralId)
            ->sum('maxima'))->toBe($expectedTotal);
    }
});

test('le seeder reproduit les matieres distinctes du bulletin CTEB', function (): void {
    $this->seed(BulletinLabCtebSeeder::class);

    $seventhYearSubjects = DB::table('system_template_nodes')
        ->where('template_id', ctebTemplateId('CTEB_7'))
        ->where('type_node', 'MATIERE')
        ->pluck('libelle');
    $eighthYearSubjects = DB::table('system_template_nodes')
        ->where('template_id', ctebTemplateId('CTEB_8'))
        ->where('type_node', 'MATIERE')
        ->pluck('libelle');

    expect($seventhYearSubjects)->toHaveCount(20)
        ->toContain('Français', 'Zoologie')
        ->not->toContain('Nutrition', 'Chimie')
        ->and($eighthYearSubjects)->toHaveCount(22)
        ->toContain('Nutrition', 'Chimie');
});

test('la huitieme EB contient les blocs TENASOSP et jury', function (): void {
    $this->seed(BulletinLabCtebSeeder::class);

    $blocks = DB::table('system_template_blocs')
        ->join('system_blocs_speciaux', 'system_blocs_speciaux.id', '=', 'system_template_blocs.bloc_id')
        ->where('system_template_blocs.template_id', ctebTemplateId('CTEB_8'))
        ->orderBy('system_template_blocs.ordre')
        ->pluck('system_blocs_speciaux.code')
        ->all();

    expect($blocks)->toBe(['REPECHAGE', 'TENASOSP', 'RESULTAT_FINAL', 'JURY', 'SIGNATURES']);
});

test('le seeder CTEB est idempotent et conserve les identifiants des noeuds', function (): void {
    $this->seed(BulletinLabCtebSeeder::class);

    $before = DB::table('system_template_nodes')
        ->whereIn('template_id', [ctebTemplateId('CTEB_7'), ctebTemplateId('CTEB_8')])
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $this->seed(BulletinLabCtebSeeder::class);

    $after = DB::table('system_template_nodes')
        ->whereIn('template_id', [ctebTemplateId('CTEB_7'), ctebTemplateId('CTEB_8')])
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($after)->toBe($before);
});
