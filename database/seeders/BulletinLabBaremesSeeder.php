<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabBaremesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('types_evaluations_globales')->upsert([
            ['code'=>'APPLICATION','nom'=>'Application'],
            ['code'=>'CONDUITE','nom'=>'Conduite'],
        ], ['code'], ['nom']);

        DB::table('baremes_appreciations')->upsert([
            ['type'=>'APPLICATION','code'=>'TB','libelle'=>'Très bien','ordre'=>1],
            ['type'=>'APPLICATION','code'=>'B','libelle'=>'Bien','ordre'=>2],
            ['type'=>'APPLICATION','code'=>'AB','libelle'=>'Assez bien','ordre'=>3],
            ['type'=>'APPLICATION','code'=>'M','libelle'=>'Médiocre','ordre'=>4],
            ['type'=>'CONDUITE','code'=>'TB','libelle'=>'Très bien','ordre'=>1],
            ['type'=>'CONDUITE','code'=>'B','libelle'=>'Bien','ordre'=>2],
            ['type'=>'CONDUITE','code'=>'AB','libelle'=>'Assez bien','ordre'=>3],
            ['type'=>'CONDUITE','code'=>'M','libelle'=>'Médiocre','ordre'=>4],
        ], ['tenant_id','type','code'], ['libelle','ordre']);

        DB::table('baremes_mentions')->upsert([
            ['min_pourcentage'=>0,'max_pourcentage'=>49.99,'mention'=>'Échec','decision_defaut'=>'DOUBLE','ordre'=>1],
            ['min_pourcentage'=>50,'max_pourcentage'=>59.99,'mention'=>'Passable','decision_defaut'=>'PASSE','ordre'=>2],
            ['min_pourcentage'=>60,'max_pourcentage'=>69.99,'mention'=>'Satisfaction','decision_defaut'=>'PASSE','ordre'=>3],
            ['min_pourcentage'=>70,'max_pourcentage'=>79.99,'mention'=>'Distinction','decision_defaut'=>'PASSE','ordre'=>4],
            ['min_pourcentage'=>80,'max_pourcentage'=>100,'mention'=>'Grande distinction','decision_defaut'=>'PASSE','ordre'=>5],
        ], ['tenant_id','cycle_id','system_template_id','min_pourcentage','max_pourcentage'], ['mention','decision_defaut','ordre']);
    }
}
