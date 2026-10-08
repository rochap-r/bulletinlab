<?php

use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCommercialeSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabLayoutsSeeder;
use Database\Seeders\BulletinLabTemplateCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BulletinLabCoreSeeder::class);
    $this->seed(BulletinLabAcademicSeeder::class);
    $this->seed(BulletinLabLayoutsSeeder::class);
    $this->seed(BulletinLabTemplateCatalogSeeder::class);
});

function commercialNodeCounts(): array
{
    return DB::table('system_template_nodes')
        ->whereIn('template_id', DB::table('system_templates_bulletin')->where('code', 'like', 'COMMERCIALE_%')->pluck('id'))
        ->groupBy('template_id')
        ->selectRaw('template_id, count(*) as total')
        ->pluck('total', 'template_id')
        ->all();
}

test('le seeder commercial ne duplique pas les noeuds a la seconde execution', function () {
    $this->seed(BulletinLabCommercialeSeeder::class);
    $apresPremiere = commercialNodeCounts();

    $this->seed(BulletinLabCommercialeSeeder::class);
    $apresSeconde = commercialNodeCounts();

    expect($apresPremiere)->not->toBeEmpty();
    expect($apresSeconde)->toBe($apresPremiere);
});

test('le seeder commercial conserve les identifiants de noeuds existants', function () {
    $this->seed(BulletinLabCommercialeSeeder::class);
    $avant = DB::table('system_template_nodes')->orderBy('id')->pluck('id')->all();

    $this->seed(BulletinLabCommercialeSeeder::class);
    $apres = DB::table('system_template_nodes')->orderBy('id')->pluck('id')->all();

    expect($apres)->toBe($avant);
});

test('le seeder commercial laisse une seule racine par branche', function () {
    $this->seed(BulletinLabCommercialeSeeder::class);

    $racines = DB::table('system_template_nodes')
        ->whereNull('parent_id')
        ->whereIn('template_id', DB::table('system_templates_bulletin')->where('code', 'like', 'COMMERCIALE_%')->pluck('id'))
        ->get(['template_id', 'ordre']);

    $doublons = $racines
        ->groupBy(fn (object $node): string => $node->template_id.'-'.$node->ordre)
        ->filter(fn (object $groupe): bool => $groupe->count() > 1);

    expect($doublons)->toBeEmpty();
});

test('le seeder commercial conserve les ids de noeuds references par un modele', function () {
    $this->seed(BulletinLabCommercialeSeeder::class);

    $avant = DB::table('modele_nodes')->orderBy('id')->pluck('system_node_id')->all();

    $this->seed(BulletinLabCommercialeSeeder::class);

    expect(DB::table('modele_nodes')->orderBy('id')->pluck('system_node_id')->all())->toBe($avant);
});
