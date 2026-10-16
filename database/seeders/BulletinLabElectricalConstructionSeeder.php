<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

class BulletinLabElectricalConstructionSeeder extends BulletinLabCommercialeSeeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->ensureConstructionClasses();
            foreach (['ELECTRICITE', 'CONSTRUCTION'] as $option) {
                foreach (range(1, 4) as $year) {
                    $this->seedConfiguration($option.'_'.$year, $option === 'ELECTRICITE' ? $this->electricityGroups($year) : $this->constructionGroups($year), $year === 4);
                }
            }
            $this->seedConfiguration('CONSTRUCTION_1_2', $this->constructionGroups(1), false);
        });
    }

    private function ensureConstructionClasses(): void
    {
        $combined = DB::table('system_templates_bulletin')->where('code', 'CONSTRUCTION_1_2')->first();
        if ($combined === null) {
            return;
        }
        foreach ([1, 2] as $year) {
            $levelId = DB::table('ref_niveaux')->where('code', 'HUM_'.$year)->value('id');
            DB::table('system_templates_bulletin')->updateOrInsert(['code' => 'CONSTRUCTION_'.$year], [
                'nom' => ($year === 1 ? '1ère' : '2ème').' Construction',
                'cycle_id' => $combined->cycle_id, 'degre_id' => $combined->degre_id, 'option_id' => $combined->option_id,
                'niveau_min_id' => $levelId, 'niveau_max_id' => $levelId, 'layout_id' => $combined->layout_id,
                'type_decoupage' => 'SEMESTRIEL', 'version' => $combined->version, 'actif' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $templateId = $this->templateId('CONSTRUCTION_'.$year);
            foreach (DB::table('system_template_blocs')->where('template_id', $combined->id)->get() as $block) {
                DB::table('system_template_blocs')->updateOrInsert(['template_id' => $templateId, 'bloc_id' => $block->bloc_id],
                    ['ordre' => $block->ordre, 'actif' => $block->actif, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** @param list<array{max: int, subjects: list<string>}> $groups */
    private function seedConfiguration(string $code, array $groups, bool $terminal): void
    {
        $templateId = $this->templateId($code);
        if ($templateId === null) {
            return;
        }
        $this->seedSemestrielPeriods($code);
        $this->seedNodes($code, $groups);
        DB::table('system_template_nodes')->where('template_id', $templateId)->where('type_node', 'GROUPE_MAXIMA')->update(['libelle' => 'MAXIMA']);
        $practiceGroupId = DB::table('system_template_nodes')->where('template_id', $templateId)->whereNull('parent_id')->where('ordre', count($groups))->value('id');
        $practiceIds = DB::table('system_template_nodes')->where('template_id', $templateId)->where('parent_id', $practiceGroupId)->pluck('id');
        DB::table('system_template_nodes')->whereIn('id', $practiceIds)->update(['metadata' => json_encode(['examens_non_applicables' => true], JSON_THROW_ON_ERROR)]);
        $examIds = DB::table('system_template_periodes')->where('template_id', $templateId)->where('type_periode', 'EXAMEN')->pluck('id');
        DB::table('system_template_maxima')->whereIn('node_id', $practiceIds)->whereIn('periode_id', $examIds)->delete();
        DB::table('system_template_periodes')->where('template_id', $templateId)->where('type_periode', 'REPECHAGE')->update(['actif' => ! $terminal]);
        $generalId = DB::table('system_template_periodes')->where('template_id', $templateId)->where('code', 'TOTAL_GENERAL')->value('id');
        DB::table('system_templates_bulletin')->where('id', $templateId)->update([
            'total_general_maxima' => DB::table('system_template_maxima')->where('template_id', $templateId)->where('periode_id', $generalId)->sum('maxima'),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, int> */
    protected function maximaForGroup(int $maximum): array
    {
        if ($maximum === 100) {
            return ['P1' => 100, 'P2' => 100, 'TOTAL_S1' => 200, 'P3' => 100, 'P4' => 100, 'TOTAL_S2' => 200, 'TOTAL_GENERAL' => 400];
        }

        return parent::maximaForGroup($maximum);
    }

    /** @return list<array{max: int, subjects: list<string>}> */
    private function electricityGroups(int $year): array
    {
        return match ($year) {
            1 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale', 'Biologie (1)']],
                ['max' => 20, 'subjects' => ['Anglais', 'Chimie', 'Electronique numérique', 'Géographie / actualités', 'Histoire', 'Informatique', 'Instr. méth. Mesures', 'Mécanisme', 'Physique', 'Techno. mécanique']],
                ['max' => 40, 'subjects' => ['Dessin électrique', 'Dessin industriel', 'Electricité générale', 'Mécanique générale', 'Techno. électr.', 'Electronique']],
                ['max' => 50, 'subjects' => ['Français', 'Mathématiques']],
                ['max' => 100, 'subjects' => ['Atelier électrique', 'Atelier ajustage']],
            ],
            2 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale', 'Biologie (1)']],
                ['max' => 20, 'subjects' => ['Anglais', 'Chimie', 'Electronique numérique', 'Géographie / actualités', 'Histoire (1)', 'Informatique', 'Instr. méth. Mesures', 'Mécanisme', 'Physique', 'Techno mécanique (1)']],
                ['max' => 40, 'subjects' => ['Dessin électrique', 'Dessin industriel', 'Electricité générale', 'Mécanique générale (1)', 'Techno. électr.']],
                ['max' => 50, 'subjects' => ['Français', 'Mathématiques']],
                ['max' => 100, 'subjects' => ['Atelier électrique', 'Labo. électrique']],
            ],
            3 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale', 'Biologie (1)']],
                ['max' => 20, 'subjects' => ['Actualités', 'Anglais', 'Automate progr. (1)', 'Electronique + labo.', 'Informatique', 'Instr. méth. Mesures', 'Résistances des matér.']],
                ['max' => 40, 'subjects' => ['Dessin électrique', 'Electricité générale', 'Machines électriques', 'Techno. électrique']],
                ['max' => 50, 'subjects' => ['Français', 'Mathématiques']],
                ['max' => 100, 'subjects' => ['Atelier électrique', 'Labo. électrique']],
            ],
            4 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale']],
                ['max' => 20, 'subjects' => ['Actualités', 'Anglais', 'Application élect.', 'Automation', 'Circuit logique (1)', 'Informatique', 'Législation (1)', 'Organisation']],
                ['max' => 40, 'subjects' => ['Electronique + labo.', 'Machine électrique', 'Mécanique appliquée', 'Schéma']],
                ['max' => 50, 'subjects' => ['Dessin électrique', 'Français', 'Labo. électrique', 'Mathématiques']],
                ['max' => 100, 'subjects' => ['Atelier électrique', 'Labo. électrique']],
            ],
        };
    }

    /** @return list<array{max: int, subjects: list<string>}> */
    private function constructionGroups(int $year): array
    {
        return match ($year) {
            1, 2 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale']],
                ['max' => 20, 'subjects' => ['Anglais', 'Chimie', 'Connaissance des matér.', 'Géographie / actualités', 'Graphostatique', 'Histoire', 'Informatique', 'Mécanique appliquée', 'Mécanique', 'Physique', 'Résistance des matériaux', 'Techno. de construction', 'Topographie', 'Voies de communication']],
                ['max' => 50, 'subjects' => ['Dessin de construction', 'Français', 'Mathématiques']],
                ['max' => 100, 'subjects' => ['Pratique professionnelle']],
            ],
            3 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale']],
                ['max' => 20, 'subjects' => ['Actualités', 'Anglais', 'Assainissement', 'Béton armé', 'Electricité', 'Graphostatique', 'Informatique', 'Mécanique', 'Outillage de chantier', 'Procédés des construct.', 'Résistance des mater.', 'Techno. de construction', 'Topographie', 'Voies de communication']],
                ['max' => 50, 'subjects' => ['Dessin de construction', 'Français', 'Mathématiques']],
                ['max' => 100, 'subjects' => ['Pratique professionnelle']],
            ],
            4 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Ed. civ. & morale', 'Education à la Vie']],
                ['max' => 20, 'subjects' => ['Actualités', 'Anglais', 'Éd. Physique & sportive', 'Electricité', 'Informatique', 'Législation', 'Organisation', 'Outillage', 'Résistance des mater.', 'Technologie', 'Topographie', 'Voies de communication']],
                ['max' => 40, 'subjects' => ['Béton armé', 'Français', 'Procédés de construction']],
                ['max' => 50, 'subjects' => ['Dessin', 'Mathématiques']],
                ['max' => 100, 'subjects' => ['Pratique professionnelle']],
            ],
        };
    }
}
