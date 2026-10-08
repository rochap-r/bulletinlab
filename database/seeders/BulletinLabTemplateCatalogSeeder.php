<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabTemplateCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = DB::table('ref_cycles')->pluck('id', 'code');
        $degre = DB::table('ref_degres')->pluck('id', 'code');
        $niveau = DB::table('ref_niveaux')->pluck('id', 'code');
        $option = DB::table('ref_options')->pluck('id', 'code');
        $layout = DB::table('system_layouts_bulletin')->pluck('id', 'code');

        $templates = [
            ['PRIMAIRE_ELEMENTAIRE_1_2', 'Primaire degré élémentaire 1ère-2ème', 'PRIMAIRE', 'ELEMENTAIRE', null, 'PRIMAIRE_1', 'PRIMAIRE_2', 'RDC_PRIMAIRE_TRIMESTRIEL', 'TRIMESTRIEL', 3360],
            ['PRIMAIRE_MOYEN_3_4', 'Primaire degré moyen 3ème-4ème', 'PRIMAIRE', 'MOYEN', null, 'PRIMAIRE_3', 'PRIMAIRE_4', 'RDC_PRIMAIRE_TRIMESTRIEL', 'TRIMESTRIEL', 3600],
            ['PRIMAIRE_TERMINAL_5', 'Primaire 5ème', 'PRIMAIRE', 'TERMINAL_PRIMAIRE', null, 'PRIMAIRE_5', 'PRIMAIRE_5', 'RDC_PRIMAIRE_TRIMESTRIEL', 'TRIMESTRIEL', null],
            ['PRIMAIRE_TERMINAL_6', 'Primaire 6ème', 'PRIMAIRE', 'TERMINAL_PRIMAIRE', null, 'PRIMAIRE_6', 'PRIMAIRE_6', 'RDC_PRIMAIRE_TRIMESTRIEL', 'TRIMESTRIEL', null],
            ['CTEB_7', '7ème EB CTEB', 'EB', 'CTEB', null, 'CTEB_7', 'CTEB_7', 'RDC_CTEB_DOMAINES', 'SEMESTRIEL', 3200],
            ['CTEB_8', '8ème EB CTEB', 'EB', 'CTEB', null, 'CTEB_8', 'CTEB_8', 'RDC_CTEB_DOMAINES', 'SEMESTRIEL', 3360],
            ['COMMERCIALE_1', '1ère Commerciale & Gestion', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'COMMERCIALE_GESTION', 'HUM_1', 'HUM_1', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['COMMERCIALE_2', '2ème Commerciale & Gestion', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'COMMERCIALE_GESTION', 'HUM_2', 'HUM_2', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['COMMERCIALE_3', '3ème Commerciale & Gestion', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'COMMERCIALE_GESTION', 'HUM_3', 'HUM_3', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['COMMERCIALE_4', '4ème Commerciale & Gestion', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'COMMERCIALE_GESTION', 'HUM_4', 'HUM_4', 'RDC_HUMANITES_EXAMEN_ETAT', 'SEMESTRIEL', null],
            ['SCIENTIFIQUE_1_2', 'Humanités scientifiques 1ère-2ème', 'HUMANITES', 'HUMANITES_GENERALES', 'SCIENTIFIQUE_MATH_PHYSIQUE', 'HUM_1', 'HUM_2', 'RDC_SCIENTIFIQUE_DOMAINES', 'SEMESTRIEL', null],
            ['SCIENTIFIQUE_1', 'Humanités scientifiques 1ère', 'HUMANITES', 'HUMANITES_GENERALES', 'SCIENTIFIQUE_MATH_PHYSIQUE', 'HUM_1', 'HUM_1', 'RDC_SCIENTIFIQUE_DOMAINES', 'SEMESTRIEL', null],
            ['SCIENTIFIQUE_2', 'Humanités scientifiques 2ème', 'HUMANITES', 'HUMANITES_GENERALES', 'SCIENTIFIQUE_MATH_PHYSIQUE', 'HUM_2', 'HUM_2', 'RDC_SCIENTIFIQUE_DOMAINES', 'SEMESTRIEL', null],
            ['SCIENTIFIQUE_3', 'Humanités scientifiques 3ème', 'HUMANITES', 'HUMANITES_GENERALES', 'SCIENTIFIQUE_MATH_PHYSIQUE', 'HUM_3', 'HUM_3', 'RDC_SCIENTIFIQUE_DOMAINES', 'SEMESTRIEL', null],
            ['SCIENTIFIQUE_4', 'Humanités scientifiques 4ème', 'HUMANITES', 'HUMANITES_GENERALES', 'SCIENTIFIQUE_MATH_PHYSIQUE', 'HUM_4', 'HUM_4', 'RDC_SCIENTIFIQUE_DOMAINES', 'SEMESTRIEL', null],
            ['PEDAGOGIE_1', '1ère Pédagogie Générale', 'HUMANITES', 'HUMANITES_GENERALES', 'PEDAGOGIE_GENERALE', 'HUM_1', 'HUM_1', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['PEDAGOGIE_2', '2ème Pédagogie Générale', 'HUMANITES', 'HUMANITES_GENERALES', 'PEDAGOGIE_GENERALE', 'HUM_2', 'HUM_2', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['PEDAGOGIE_3', '3ème Pédagogie Générale', 'HUMANITES', 'HUMANITES_GENERALES', 'PEDAGOGIE_GENERALE', 'HUM_3', 'HUM_3', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['PEDAGOGIE_4', '4ème Pédagogie Générale', 'HUMANITES', 'HUMANITES_GENERALES', 'PEDAGOGIE_GENERALE', 'HUM_4', 'HUM_4', 'RDC_HUMANITES_EXAMEN_ETAT', 'SEMESTRIEL', null],
            ['ELECTRICITE_1', '1ère Electricité Générale', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'ELECTRICITE_GENERALE', 'HUM_1', 'HUM_1', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['ELECTRICITE_2', '2ème Electricité Générale', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'ELECTRICITE_GENERALE', 'HUM_2', 'HUM_2', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['ELECTRICITE_3', '3ème Electricité Générale', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'ELECTRICITE_GENERALE', 'HUM_3', 'HUM_3', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['ELECTRICITE_4', '4ème Electricité Générale', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'ELECTRICITE_GENERALE', 'HUM_4', 'HUM_4', 'RDC_HUMANITES_EXAMEN_ETAT', 'SEMESTRIEL', null],
            ['CONSTRUCTION_1_2', '1ère-2ème Construction', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'CONSTRUCTION', 'HUM_1', 'HUM_2', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['CONSTRUCTION_3', '3ème Construction', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'CONSTRUCTION', 'HUM_3', 'HUM_3', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['CONSTRUCTION_4', '4ème Construction', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'CONSTRUCTION', 'HUM_4', 'HUM_4', 'RDC_HUMANITES_EXAMEN_ETAT', 'SEMESTRIEL', null],
            ['MECANIQUE_GENERALE_1', '1ère Mécanique Générale', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'MECANIQUE_GENERALE', 'HUM_1', 'HUM_1', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['MECANIQUE_GENERALE_2', '2ème Mécanique Générale', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'MECANIQUE_GENERALE', 'HUM_2', 'HUM_2', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['MECANIQUE_GENERALE_3', '3ème Mécanique Générale', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'MECANIQUE_GENERALE', 'HUM_3', 'HUM_3', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['MECANIQUE_GENERALE_4', '4ème Mécanique Générale', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'MECANIQUE_GENERALE', 'HUM_4', 'HUM_4', 'RDC_HUMANITES_EXAMEN_ETAT', 'SEMESTRIEL', null],
            ['MECANIQUE_AUTO_1', '1ère Mécanique Automobile', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'MECANIQUE_AUTO', 'HUM_1', 'HUM_1', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['MECANIQUE_AUTO_2', '2ème Mécanique Automobile', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'MECANIQUE_AUTO', 'HUM_2', 'HUM_2', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['MECANIQUE_AUTO_3', '3ème Mécanique Automobile', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'MECANIQUE_AUTO', 'HUM_3', 'HUM_3', 'RDC_HUMANITES_STANDARD', 'SEMESTRIEL', null],
            ['MECANIQUE_AUTO_4', '4ème Mécanique Automobile', 'HUMANITES', 'HUMANITES_TECHNIQUES', 'MECANIQUE_AUTO', 'HUM_4', 'HUM_4', 'RDC_HUMANITES_EXAMEN_ETAT', 'SEMESTRIEL', null],
        ];

        foreach ($templates as [$code,$nom,$cycleCode,$degreCode,$optionCode,$niveauMin,$niveauMax,$layoutCode,$decoupage,$total]) {
            DB::table('system_templates_bulletin')->updateOrInsert(
                ['code' => $code],
                [
                    'nom' => $nom,
                    'cycle_id' => $cycle[$cycleCode],
                    'degre_id' => $degre[$degreCode] ?? null,
                    'option_id' => $optionCode ? ($option[$optionCode] ?? null) : null,
                    'niveau_min_id' => $niveau[$niveauMin] ?? null,
                    'niveau_max_id' => $niveau[$niveauMax] ?? null,
                    'layout_id' => $layout[$layoutCode],
                    'type_decoupage' => $decoupage,
                    'total_general_maxima' => $total,
                    'version' => '2024-2025',
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $bloc = DB::table('system_blocs_speciaux')->pluck('id', 'code');
        foreach (DB::table('system_templates_bulletin')->get() as $tpl) {
            $isExamEtat = str_contains($tpl->code, '_4') && ! str_starts_with($tpl->code, 'SCIENTIFIQUE_');
            $blocs = $isExamEtat ? ['EXAMEN_ETAT', 'RESULTAT_FINAL', 'SIGNATURES'] : ['REPECHAGE', 'SIGNATURES'];
            foreach ($blocs as $i => $code) {
                if (! isset($bloc[$code])) {
                    continue;
                }
                DB::table('system_template_blocs')->updateOrInsert(
                    ['template_id' => $tpl->id, 'bloc_id' => $bloc[$code]],
                    ['ordre' => $i + 1, 'actif' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}
