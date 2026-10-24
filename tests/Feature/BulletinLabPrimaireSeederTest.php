<?php

use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabLayoutsSeeder;
use Database\Seeders\BulletinLabPrimaireSeeder;
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

function primaryTemplateId(string $code): int
{
    return (int) DB::table('system_templates_bulletin')->where('code', $code)->value('id');
}

test('le seeder configure les trois trimestres des quatre bulletins primaires', function (): void {
    $this->seed(BulletinLabPrimaireSeeder::class);

    foreach (['PRIMAIRE_ELEMENTAIRE_1_2', 'PRIMAIRE_MOYEN_3_4', 'PRIMAIRE_TERMINAL_5', 'PRIMAIRE_TERMINAL_6'] as $code) {
        $periods = DB::table('system_template_periodes')
            ->where('template_id', primaryTemplateId($code))
            ->orderBy('ordre')
            ->pluck('code')
            ->all();

        expect($periods)->toBe([
            'TRIMESTRE_1', 'P1', 'P2', 'EXAM_T1', 'TOTAL_T1',
            'TRIMESTRE_2', 'P3', 'P4', 'EXAM_T2', 'TOTAL_T2',
            'TRIMESTRE_3', 'P5', 'P6', 'EXAM_T3', 'TOTAL_T3',
            'TOTAL_GENERAL',
        ]);
    }
});

test('le seeder reproduit les maxima generaux des formulaires officiels', function (): void {
    $this->seed(BulletinLabPrimaireSeeder::class);

    $totals = [
        'PRIMAIRE_ELEMENTAIRE_1_2' => 3360,
        'PRIMAIRE_MOYEN_3_4' => 3600,
        'PRIMAIRE_TERMINAL_5' => 3720,
        'PRIMAIRE_TERMINAL_6' => 3720,
    ];

    foreach ($totals as $code => $expectedTotal) {
        $templateId = primaryTemplateId($code);
        $totalGeneralId = DB::table('system_template_periodes')
            ->where('template_id', $templateId)
            ->where('code', 'TOTAL_GENERAL')
            ->value('id');

        expect((int) DB::table('system_templates_bulletin')->where('id', $templateId)->value('total_general_maxima'))
            ->toBe($expectedTotal)
            ->and((int) DB::table('system_template_maxima')
                ->where('template_id', $templateId)
                ->where('periode_id', $totalGeneralId)
                ->sum('maxima'))
            ->toBe($expectedTotal);
    }
});

test('le seeder conserve les programmes distincts de chaque degre primaire', function (): void {
    $this->seed(BulletinLabPrimaireSeeder::class);

    $elementarySubjects = DB::table('system_template_nodes')
        ->where('template_id', primaryTemplateId('PRIMAIRE_ELEMENTAIRE_1_2'))
        ->where('type_node', 'MATIERE')
        ->pluck('libelle');
    $middleSubjects = DB::table('system_template_nodes')
        ->where('template_id', primaryTemplateId('PRIMAIRE_MOYEN_3_4'))
        ->where('type_node', 'MATIERE')
        ->pluck('libelle');
    $terminalSubjects = DB::table('system_template_nodes')
        ->where('template_id', primaryTemplateId('PRIMAIRE_TERMINAL_6'))
        ->where('type_node', 'MATIERE')
        ->pluck('libelle');

    expect($elementarySubjects)->toHaveCount(19)
        ->toContain('Sciences d’éveil', 'Vocabulaire')
        ->and($middleSubjects)->toHaveCount(24)
        ->toContain('Zoologie, botanique et informatique', 'Lecture-écriture en langue française')
        ->and($terminalSubjects)->toHaveCount(26)
        ->toContain('Physique et informatique', 'Anatomie, zoologie et botanique');
});

test('la sixieme primaire active le bloc ENAFEP', function (): void {
    $this->seed(BulletinLabPrimaireSeeder::class);

    $blocks = DB::table('system_template_blocs')
        ->join('system_blocs_speciaux', 'system_blocs_speciaux.id', '=', 'system_template_blocs.bloc_id')
        ->where('system_template_blocs.template_id', primaryTemplateId('PRIMAIRE_TERMINAL_6'))
        ->where('system_template_blocs.actif', true)
        ->orderBy('system_template_blocs.ordre')
        ->pluck('system_blocs_speciaux.code')
        ->all();

    expect($blocks)->toBe(['ENAFEP', 'RESULTAT_FINAL', 'SIGNATURES']);
});

test('le seeder primaire est idempotent et conserve les identifiants des noeuds', function (): void {
    $this->seed(BulletinLabPrimaireSeeder::class);

    $templateIds = DB::table('system_templates_bulletin')
        ->where('code', 'like', 'PRIMAIRE_%')
        ->pluck('id');
    $before = DB::table('system_template_nodes')
        ->whereIn('template_id', $templateIds)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $this->seed(BulletinLabPrimaireSeeder::class);

    $after = DB::table('system_template_nodes')
        ->whereIn('template_id', $templateIds)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($after)->toBe($before);
});
