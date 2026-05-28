<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabLayoutsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('system_blocs_speciaux')->upsert([
            ['code'=>'REPECHAGE','nom'=>'Examen de repêchage'],
            ['code'=>'EXAMEN_ETAT','nom'=>'Examen d’Etat'],
            ['code'=>'ENAFEP','nom'=>'ENAFEP'],
            ['code'=>'TENASOSP','nom'=>'TENASOSP'],
            ['code'=>'RESULTAT_FINAL','nom'=>'Résultat final'],
            ['code'=>'JURY','nom'=>'Jury / Commission'],
            ['code'=>'SIGNATURES','nom'=>'Signatures'],
        ], ['code'], ['nom']);

        DB::table('system_layouts_bulletin')->upsert([
            ['code'=>'RDC_PRIMAIRE_TRIMESTRIEL','nom'=>'Primaire trimestriel RDC','vue_blade'=>'bulletins.layouts.rdc_primaire_trimestriel'],
            ['code'=>'RDC_CTEB_DOMAINES','nom'=>'CTEB 7e/8e avec domaines','vue_blade'=>'bulletins.layouts.rdc_cteb_domaines'],
            ['code'=>'RDC_SCIENTIFIQUE_DOMAINES','nom'=>'Humanités scientifiques avec domaines','vue_blade'=>'bulletins.layouts.rdc_scientifique_domaines'],
            ['code'=>'RDC_HUMANITES_STANDARD','nom'=>'Humanités standard avec repêchage','vue_blade'=>'bulletins.layouts.rdc_humanites_standard'],
            ['code'=>'RDC_HUMANITES_EXAMEN_ETAT','nom'=>'Humanités terminales avec examen d’Etat','vue_blade'=>'bulletins.layouts.rdc_humanites_examen_etat'],
        ], ['code'], ['nom','vue_blade']);
    }
}
