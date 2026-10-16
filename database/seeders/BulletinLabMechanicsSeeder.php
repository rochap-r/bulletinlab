<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

class BulletinLabMechanicsSeeder extends BulletinLabCommercialeSeeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['AUTO', 'GENERALE'] as $option) {
                foreach (range(1, 4) as $year) {
                    $code = 'MECANIQUE_'.$option.'_'.$year;
                    $this->seedSemestrielPeriods($code);
                    $groups = $option === 'AUTO' ? $this->automotiveGroups($year) : $this->generalGroups($year);
                    $this->seedNodes($code, $groups);
                    $templateId = $this->templateId($code);
                    if ($templateId === null) {
                        continue;
                    }
                    DB::table('system_template_nodes')->where('template_id', $templateId)->where('type_node', 'GROUPE_MAXIMA')->update(['libelle' => 'MAXIMA']);
                    $practiceIds = DB::table('system_template_nodes')->where('template_id', $templateId)->where('type_node', 'MATIERE')
                        ->whereIn('libelle', end($groups)['subjects'])->pluck('id');
                    DB::table('system_template_nodes')->whereIn('id', $practiceIds)->update(['metadata' => json_encode(['examens_non_applicables' => true], JSON_THROW_ON_ERROR)]);
                    $examIds = DB::table('system_template_periodes')->where('template_id', $templateId)->where('type_periode', 'EXAMEN')->pluck('id');
                    DB::table('system_template_maxima')->whereIn('node_id', $practiceIds)->whereIn('periode_id', $examIds)->delete();
                    DB::table('system_template_periodes')->where('template_id', $templateId)->where('type_periode', 'REPECHAGE')->update(['actif' => $year !== 4]);
                    $generalId = DB::table('system_template_periodes')->where('template_id', $templateId)->where('code', 'TOTAL_GENERAL')->value('id');
                    DB::table('system_templates_bulletin')->where('id', $templateId)->update([
                        'total_general_maxima' => DB::table('system_template_maxima')->where('template_id', $templateId)->where('periode_id', $generalId)->sum('maxima'),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    /** @return array<string, int> */
    protected function maximaForGroup(int $maximum): array
    {
        if ($maximum < 0) {
            $maximum = abs($maximum);

            return ['P1' => $maximum, 'P2' => $maximum, 'TOTAL_S1' => 2 * $maximum,
                'P3' => $maximum, 'P4' => $maximum, 'TOTAL_S2' => 2 * $maximum, 'TOTAL_GENERAL' => 4 * $maximum];
        }

        return ['P1' => $maximum, 'P2' => $maximum, 'EXAM_S1' => 2 * $maximum, 'TOTAL_S1' => 4 * $maximum,
            'P3' => $maximum, 'P4' => $maximum, 'EXAM_S2' => 2 * $maximum, 'TOTAL_S2' => 4 * $maximum, 'TOTAL_GENERAL' => 8 * $maximum];
    }

    /** @return list<array{max: int, subjects: list<string>}> */
    private function automotiveGroups(int $year): array
    {
        $groups = match ($year) {
            1 => [
                [10, ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale', 'Histoire', 'Géographie', 'Physique', 'Chimie', 'Informatique', 'Métallurgie']],
                [20, ['Anglais', 'Electronique', 'Techno. automobile']],
                [30, ['Electricité générale', 'Dessin industriel', 'Mécanique générale', 'Techno. ajustage']],
                [40, ['Français']], [50, ['Mathématiques']], [50, ['T.P. labo.', 'Atelier ajustage']],
            ],
            2 => [
                [10, ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale', 'Physique', 'Métallurgie', 'Informatique', 'Actualités']],
                [20, ['Anglais', 'Chimie', 'Connaiss. des matériaux', 'Economie professionnelle', 'Electronique', 'Electricité générale', 'Moteur thermique', 'Techno. automobile']],
                [30, ['Dessin industriel', 'Mécanique générale']], [40, ['Français', 'Mathématique']], [100, ['T.P. labo.']],
            ],
            3 => [
                [10, ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale', 'Automation', 'T.H.P.', 'Anglais', 'Métallurgie', 'Informatique', 'Actualités']],
                [20, ['Techno. automobile', 'Moteur thermique', 'Eléments des machines', 'Electronique', 'Résistance matérielle', 'Mécanique appliquée', 'Electricité auto.', 'Automate programmable']],
                [30, ['Dessin industriel', 'Français']], [40, ['Mathématiques']], [100, ['Atelier Auto.']],
            ],
            4 => [
                [10, ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale', 'Anglais', 'Informatique', 'Actualités', 'Electronique']],
                [20, ['Résist.+ Eléments matér.', 'Mécanique appliquée', 'Organisation', 'Construction']],
                [30, ['Dessin industriel']], [40, ['Electricité auto.', 'Moteur thermique', 'Techno. Auto.']],
                [50, ['Français', 'Mathématiques']], [100, ['Atelier Auto.']],
            ],
        };

        return $this->withPracticeMaxima($groups);
    }

    /** @return list<array{max: int, subjects: list<string>}> */
    private function generalGroups(int $year): array
    {
        $groups = match ($year) {
            1 => [
                [10, ['Education à la Vie', 'Ed. civ. & morale', 'Religion (1)']],
                [20, ['Actualités (1)', 'Anglais', 'Chimie', 'Biologie (1)', 'Electricité générale', 'Informatique (1)', 'Physique', 'Métallurgie', 'Histoire', 'Géographie']],
                [40, ['Dessin industriel', 'Mécanique générale', 'Technologie mécanique']],
                [50, ['Français', 'Mathématiques']], [100, ['Prat. Profes. (Atelier) (1)']],
            ],
            2 => [
                [10, ['Education à la Vie', 'Ed. civ. & morale', 'Religion (1)']],
                [20, ['Physique', 'Actualités Géo. Hist. (1)', 'Chimie', 'Biologie', 'Matériaux (métallurgie)', 'Anglais', 'Electricité générale', 'Informatique (1)']],
                [40, ['Dessin industriel', 'Mécanique générale', 'Techno mécanique']],
                [50, ['Français', 'Mathématiques']], [100, ['Pratique profes. (atelier) (1)']],
            ],
            3 => [
                [10, ['Education à la Vie (1)', 'Ed. civ. & morale', 'Religion (1)']],
                [20, ['Anglais', 'Actualités Géo. Hist. (1)', 'Eléments des machines', 'Informatique', 'Matériaux (métallurgie)', 'Technologie']],
                [30, ['Electricité générale', 'Mécanique appliquée']], [40, ['Dessin industriel', 'Résistance']],
                [50, ['Français', 'Mathématiques']], [100, ['Pratique profes. (Atelier) (1)']],
            ],
            4 => [
                [10, ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale']],
                [20, ['Actualités Géo. Hist. (1)', 'Anglais', 'Construction', 'Organisation', 'Technologie mécanique', 'Trans. Hydro. Pneuma.(T.H.P.)', 'Informatique']],
                [30, ['Français', 'Electricité générale', 'Résist. + éléments machines']],
                [40, ['Dessin industriel', 'Mécanique appliquée']], [50, ['Mathématiques']], [100, ['Pratique profes. (Atelier) (1)']],
            ],
        };

        return $this->withPracticeMaxima($groups);
    }

    /**
     * @param  list<array{0: int, 1: list<string>}>  $groups
     * @return list<array{max: int, subjects: list<string>}>
     */
    private function withPracticeMaxima(array $groups): array
    {
        $mapped = array_map(fn (array $group): array => ['max' => $group[0], 'subjects' => $group[1]], $groups);
        $last = count($mapped) - 1;
        $mapped[$last]['max'] = -$mapped[$last]['max'];

        return $mapped;
    }
}
