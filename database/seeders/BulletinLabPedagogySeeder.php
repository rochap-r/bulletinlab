<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

class BulletinLabPedagogySeeder extends BulletinLabCommercialeSeeder
{
    public function run(): void
    {
        foreach (range(1, 4) as $year) {
            $code = 'PEDAGOGIE_'.$year;
            $this->seedSemestrielPeriods($code);
            $this->seedNodes($code, $this->groups($year));
            $templateId = $this->templateId($code);

            if ($templateId === null) {
                continue;
            }

            DB::table('system_template_nodes')->where('template_id', $templateId)
                ->where('type_node', 'GROUPE_MAXIMA')->update(['libelle' => 'MAXIMA']);
            $practiceIds = DB::table('system_template_nodes')->where('template_id', $templateId)
                ->where('libelle', 'Pratique d’Enseignement')->pluck('id');
            DB::table('system_template_nodes')->whereIn('id', $practiceIds)->update([
                'metadata' => json_encode(['examens_non_applicables' => true], JSON_THROW_ON_ERROR),
            ]);
            DB::table('system_template_periodes')->where('template_id', $templateId)
                ->whereIn('code', ['REPECHAGE', 'REPECHAGE_SIGN'])->update(['actif' => $year !== 4]);
            $generalId = DB::table('system_template_periodes')->where('template_id', $templateId)->where('code', 'TOTAL_GENERAL')->value('id');
            DB::table('system_templates_bulletin')->where('id', $templateId)->update([
                'total_general_maxima' => DB::table('system_template_maxima')->where('template_id', $templateId)->where('periode_id', $generalId)->sum('maxima'),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array<string, int> */
    protected function maximaForGroup(int $m): array
    {
        return $m === 100
            ? ['P1' => 100, 'P2' => 100, 'TOTAL_S1' => 200, 'P3' => 100, 'P4' => 100, 'TOTAL_S2' => 200, 'TOTAL_GENERAL' => 400]
            : parent::maximaForGroup($m);
    }

    /** @return list<array{max: int, subjects: list<string>}> */
    private function groups(int $year): array
    {
        return match ($year) {
            1 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale']],
                ['max' => 20, 'subjects' => ['Biologie', 'Dessin', 'Education music. / Théâtrale', 'Education phys. & sportive', 'Géographie', 'Histoire', 'Informatique', 'Langues nationales', 'Sociologie africaine', 'Travaux musicale/Ecriture']],
                ['max' => 40, 'subjects' => ['Chimie', 'Mathématiques', 'Pédagogie', 'Physique', 'Psychologie']],
                ['max' => 50, 'subjects' => ['Anglais', 'Français']],
            ],
            2 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale']],
                ['max' => 20, 'subjects' => ['Chimie', 'Dessin', 'Educat. phys. & sportive', 'Economie politique', 'Educat. music./Théâtrale', 'Géographie', 'Histoire', 'Informatique', 'Langues nationales', 'Microbiologie', 'Physique']],
                ['max' => 40, 'subjects' => ['Didactique des disciplines', 'Didactique générale', 'Mathématiques', 'Pédagogie', 'Psychologie', 'Travail manuel / Ecriture']],
                ['max' => 50, 'subjects' => ['Anglais', 'Français']],
            ],
            3 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la Vie', 'Ed. civ. & morale', 'Informatique']],
                ['max' => 20, 'subjects' => ['Biologie', 'Education music./Théâtrale', 'Education phys. & sportive', 'Esthétique', 'Géographie', 'Histoire', 'Physique']],
                ['max' => 40, 'subjects' => ['Anglais', 'Langues nationales', 'Mathématiques']],
                ['max' => 50, 'subjects' => ['Français', 'Dessin', 'Didactique des disciplines', 'Didactique générale', 'Pédagogie', 'Psychologie', 'Travail manuel / Ecriture']],
                ['max' => 100, 'subjects' => ['Pratique d’Enseignement']],
            ],
            4 => [
                ['max' => 10, 'subjects' => ['Religion (1)', 'Education à la vie', 'Ed. civ. & morale', 'Informatique']],
                ['max' => 20, 'subjects' => ['Biologie', 'Education phys. & sportive', 'Géographie', 'Histoire', 'Philosophie', 'Physique']],
                ['max' => 40, 'subjects' => ['Anglais', 'Langues nationales', 'Mathématiques']],
                ['max' => 50, 'subjects' => ['Français', 'Dessin', 'Didactique des disciplines', 'Didactique générale', 'Ed. musicale / Théâtrale', 'Pédagogie', 'Psychologie', 'Travail manuel / Ecriture']],
                ['max' => 100, 'subjects' => ['Pratique d’Enseignement']],
            ],
        };
    }
}
