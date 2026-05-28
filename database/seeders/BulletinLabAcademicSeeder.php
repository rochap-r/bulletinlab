<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabAcademicSeeder extends Seeder
{
    public function run(): void
    {
        $cycles = [
            ['code' => 'MATERNELLE', 'nom' => 'Maternelle', 'ordre' => 1],
            ['code' => 'PRIMAIRE', 'nom' => 'Primaire', 'ordre' => 2],
            ['code' => 'EB', 'nom' => 'Education de Base', 'ordre' => 3],
            ['code' => 'HUMANITES', 'nom' => 'Humanités', 'ordre' => 4],
        ];

        foreach ($cycles as $cycle) {
            DB::table('ref_cycles')->updateOrInsert(['code' => $cycle['code']], $cycle + ['created_at' => now(), 'updated_at' => now()]);
        }

        $cycleIds = DB::table('ref_cycles')->pluck('id', 'code');

        $degres = [
            ['cycle' => 'PRIMAIRE', 'code' => 'ELEMENTAIRE', 'nom' => 'Degré élémentaire', 'ordre' => 1],
            ['cycle' => 'PRIMAIRE', 'code' => 'MOYEN', 'nom' => 'Degré moyen', 'ordre' => 2],
            ['cycle' => 'PRIMAIRE', 'code' => 'TERMINAL_PRIMAIRE', 'nom' => 'Degré terminal primaire', 'ordre' => 3],
            ['cycle' => 'EB', 'code' => 'CTEB', 'nom' => 'Cycle terminal de l’éducation de base', 'ordre' => 1],
            ['cycle' => 'HUMANITES', 'code' => 'HUMANITES_GENERALES', 'nom' => 'Humanités générales', 'ordre' => 1],
            ['cycle' => 'HUMANITES', 'code' => 'HUMANITES_TECHNIQUES', 'nom' => 'Humanités techniques', 'ordre' => 2],
        ];

        foreach ($degres as $degre) {
            DB::table('ref_degres')->updateOrInsert(
                ['code' => $degre['code']],
                ['cycle_id' => $cycleIds[$degre['cycle']], 'nom' => $degre['nom'], 'ordre' => $degre['ordre'], 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $degreIds = DB::table('ref_degres')->pluck('id', 'code');

        $niveaux = [
            ['cycle'=>'PRIMAIRE','degre'=>'ELEMENTAIRE','code'=>'PRIMAIRE_1','nom'=>'1ère Primaire','numero'=>1,'ordre'=>1],
            ['cycle'=>'PRIMAIRE','degre'=>'ELEMENTAIRE','code'=>'PRIMAIRE_2','nom'=>'2ème Primaire','numero'=>2,'ordre'=>2],
            ['cycle'=>'PRIMAIRE','degre'=>'MOYEN','code'=>'PRIMAIRE_3','nom'=>'3ème Primaire','numero'=>3,'ordre'=>3],
            ['cycle'=>'PRIMAIRE','degre'=>'MOYEN','code'=>'PRIMAIRE_4','nom'=>'4ème Primaire','numero'=>4,'ordre'=>4],
            ['cycle'=>'PRIMAIRE','degre'=>'TERMINAL_PRIMAIRE','code'=>'PRIMAIRE_5','nom'=>'5ème Primaire','numero'=>5,'ordre'=>5],
            ['cycle'=>'PRIMAIRE','degre'=>'TERMINAL_PRIMAIRE','code'=>'PRIMAIRE_6','nom'=>'6ème Primaire','numero'=>6,'ordre'=>6],
            ['cycle'=>'EB','degre'=>'CTEB','code'=>'CTEB_7','nom'=>'7ème EB','numero'=>7,'ordre'=>7],
            ['cycle'=>'EB','degre'=>'CTEB','code'=>'CTEB_8','nom'=>'8ème EB','numero'=>8,'ordre'=>8],
            ['cycle'=>'HUMANITES','degre'=>'HUMANITES_GENERALES','code'=>'HUM_1','nom'=>'1ère Humanités','numero'=>1,'ordre'=>1],
            ['cycle'=>'HUMANITES','degre'=>'HUMANITES_GENERALES','code'=>'HUM_2','nom'=>'2ème Humanités','numero'=>2,'ordre'=>2],
            ['cycle'=>'HUMANITES','degre'=>'HUMANITES_GENERALES','code'=>'HUM_3','nom'=>'3ème Humanités','numero'=>3,'ordre'=>3],
            ['cycle'=>'HUMANITES','degre'=>'HUMANITES_GENERALES','code'=>'HUM_4','nom'=>'4ème Humanités','numero'=>4,'ordre'=>4],
        ];

        foreach ($niveaux as $niveau) {
            DB::table('ref_niveaux')->updateOrInsert(
                ['code' => $niveau['code']],
                [
                    'cycle_id' => $cycleIds[$niveau['cycle']],
                    'degre_id' => $degreIds[$niveau['degre']],
                    'nom' => $niveau['nom'],
                    'numero' => $niveau['numero'],
                    'ordre' => $niveau['ordre'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $options = [
            ['cycle'=>'HUMANITES','code'=>'COMMERCIALE_GESTION','nom'=>'Commerciale & Gestion'],
            ['cycle'=>'HUMANITES','code'=>'SCIENTIFIQUE_MATH_PHYSIQUE','nom'=>'Scientifique Mathématiques-Physique'],
            ['cycle'=>'HUMANITES','code'=>'PEDAGOGIE_GENERALE','nom'=>'Pédagogie Générale'],
            ['cycle'=>'HUMANITES','code'=>'ELECTRICITE_GENERALE','nom'=>'Electricité Générale'],
            ['cycle'=>'HUMANITES','code'=>'CONSTRUCTION','nom'=>'Construction'],
            ['cycle'=>'HUMANITES','code'=>'MECANIQUE_GENERALE','nom'=>'Mécanique Générale'],
            ['cycle'=>'HUMANITES','code'=>'MECANIQUE_AUTO','nom'=>'Mécanique Automobile'],
        ];

        foreach ($options as $option) {
            DB::table('ref_options')->updateOrInsert(
                ['code' => $option['code']],
                ['cycle_id' => $cycleIds[$option['cycle']], 'nom' => $option['nom'], 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $matieres = [
            'Religion','Education à la Vie','Ed. civ. & morale','Education physique & sportive','Géographie économique',
            'Droit','Correspondance commerciale anglaise','Correspondance commerciale française','Fiscalité',
            'Mathématiques financières','Mathématiques générales','Activités complémentaires','Anglais',
            'Documentation commerciale','Français','Informatique','Comptabilité générale','Pratique professionnelle',
            'Opérations des banques et crédits','Economie politique','Entrepreneuriat','Déontologie professionnelle',
            'Finances publiques','Economie de développement','Organisation des entreprises','Comptabilité analytique',
            'Arithmétique','Statistique','Géométrie','Algèbre','Anatomie','Botanique','Zoologie','Nutrition',
            'Sciences Physiques','Technologie','TIC','Dessin','Musique','Chimie','Physique','Biologie générale',
            'Expression orale','Expression écrite','Vocabulaire','Numération','Opérations','Problèmes',
            'Mesures des grandeurs','Formes géométriques','Sciences d’éveil','Arts plastiques','Arts dramatiques',
        ];

        foreach ($matieres as $matiere) {
            $code = strtoupper(str_replace([' ', 'à', 'é', 'è', 'ê', 'ç', '&', '.', '-', '/'], ['_', 'A', 'E', 'E', 'E', 'C', 'ET', '', '_', '_'], $matiere));
            DB::table('ref_matieres')->updateOrInsert(
                ['code' => $code],
                ['nom' => $matiere, 'nom_court' => mb_substr($matiere, 0, 80), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
