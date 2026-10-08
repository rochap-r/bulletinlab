<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

class BulletinLabScientificSeeder extends BulletinLabCtebSeeder
{
    public function run(): void
    {
        foreach (range(1, 4) as $year) {
            $code = 'SCIENTIFIQUE_'.$year;
            if ($year <= 2 && $this->templateId($code) === null) {
                $legacy = DB::table('system_templates_bulletin')->where('code', 'SCIENTIFIQUE_1_2')->first();
                if ($legacy !== null) {
                    $attributes = (array) $legacy;
                    unset($attributes['id']);
                    $levelId = DB::table('ref_niveaux')->where('code', 'HUM_'.$year)->value('id');
                    DB::table('system_templates_bulletin')->insert(array_replace($attributes, [
                        'code' => $code, 'nom' => 'Humanités scientifiques '.($year === 1 ? '1ère' : '2ème'),
                        'niveau_min_id' => $levelId, 'niveau_max_id' => $levelId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]));
                }
            }
            $this->seedPeriods($code);
            $domains = $this->domains($year);
            $this->seedTemplate($code, $domains);
            $this->seedPrintedMaxima($code, $year, $domains);
            $this->seedSpecialBlocks($code, ['REPECHAGE', 'SIGNATURES']);
        }
    }

    /** @return array<int, array{name: string, groups: array<int, array{name: string|null, subjects: array<int, array{0: string, 1: int}>}>}> */
    private function domains(int $year): array
    {
        $mathematics = $year <= 2
            ? [['Algèbre, Stat. & Analy.', 40], ['Géometrie et Trigo.', 20], ['Dessin scientifique', 10]]
            : [['Algèbre, Stat. & Analy.', 30], ['Géometrie', 20], ['Trigo. & Statistiques', 10], ['Dessin scientifique', 10]];
        $lifeSciences = $year <= 2
            ? [['Biologie générale', 20], ['Microbiologie', 10], ['Géologie', 10]]
            : [['Biologie générale', 20], ['Systématique et Jardin.', 20], ['Géologie / Ecologie', 20]];

        return [
            ['name' => 'Domaine des sciences', 'groups' => [
                ['name' => 'Sous-domaine des mathématiques', 'subjects' => $mathematics],
                ['name' => 'Sous-domaine des sciences de la vie et de la Terre', 'subjects' => $lifeSciences],
                ['name' => 'Sous-domaine des sciences physiques, technologie et technologies de l’information et de la communication (TIC)', 'subjects' => [['Chimie', 30], ['Physique', 30], ['Tech. d’Info et Com (TIC)', 10]]],
            ]],
            ['name' => 'Domaine des langues', 'groups' => [['name' => null, 'subjects' => [['Français', 50], ['Anglais', 30]]]]],
            ['name' => 'Domaine de l’univers social et environnement', 'groups' => [['name' => null, 'subjects' => [
                ['Ed. civ. & morale', 10], ['Géographie', 20], ['Histoire', 20], ['Education à la vie (1)', 10],
                [$year === 4 ? 'Philosophie' : 'Sociologie africaine', 20], ['Religion (1)', 10],
            ]]]],
            ['name' => 'Domaine du développement personnel', 'groups' => [['name' => null, 'subjects' => [['Educat. phys. & sport.', 10]]]]],
        ];
    }

    /** @param array<int, array{name: string, groups: array<int, array{name: string|null, subjects: array<int, array{0: string, 1: int}>}>}> $domains */
    private function seedPrintedMaxima(string $code, int $year, array $domains): void
    {
        $templateId = $this->templateId($code);

        if ($templateId === null) {
            return;
        }

        $nodes = DB::table('system_template_nodes')->where('template_id', $templateId)->get();
        $periodIds = DB::table('system_template_periodes')->where('template_id', $templateId)->pluck('id', 'code');

        foreach ($domains as $domain) {
            $domainNode = $nodes->firstWhere('libelle', $domain['name']);

            foreach ($domain['groups'] as $group) {
                $parent = $group['name'] === null ? $domainNode : $nodes->firstWhere('libelle', $group['name']);
                $subtotal = $nodes->first(fn (object $node): bool => $node->parent_id === $parent->id && $node->type_node === 'SOUS_TOTAL');
                $maximum = array_sum(array_column($group['subjects'], 1));
                $printed = $this->periodMaxima($maximum);

                if ($group['name'] === 'Sous-domaine des sciences de la vie et de la Terre') {
                    if ($year === 3) {
                        $printed = array_replace($this->periodMaxima(40), ['P1' => 60, 'P2' => 60, 'P3' => 60, 'P4' => 60]);
                    } elseif ($year === 4) {
                        $printed = array_replace($this->periodMaxima(60), ['P1' => 40, 'P2' => 40, 'P3' => 40, 'P4' => 40]);
                    }
                }

                if ($year === 4 && $domain['name'] === 'Domaine de l’univers social et environnement') {
                    $printed['P3'] = 80;
                    $printed['P4'] = 80;
                }

                $this->saveMaxima($templateId, $subtotal->id, $periodIds->all(), $printed);
            }
        }

        if ($year === 3) {
            foreach (['Systématique et Jardin.', 'Géologie / Ecologie'] as $label) {
                $this->saveMaxima($templateId, $nodes->firstWhere('libelle', $label)->id, $periodIds->all(), array_replace(
                    $this->periodMaxima(10), ['P1' => 20, 'P2' => 20, 'P3' => 20, 'P4' => 20],
                ));
            }
        } elseif ($year === 4) {
            $this->saveMaxima($templateId, $nodes->firstWhere('libelle', 'Anglais')->id, $periodIds->all(), array_replace(
                $this->periodMaxima(30), ['P3' => 40, 'P4' => 40, 'EXAM_S2' => 80, 'TOTAL_S2' => 160, 'TOTAL_GENERAL' => 320],
            ));
        }

        $incompatibleBlocks = DB::table('system_blocs_speciaux')->whereIn('code', ['EXAMEN_ETAT', 'RESULTAT_FINAL'])->pluck('id');
        DB::table('system_template_blocs')->where('template_id', $templateId)->whereIn('bloc_id', $incompatibleBlocks)->update(['actif' => false]);
    }

    /** @param array<string, int> $periodIds
     * @param  array<string, int>  $maxima
     */
    private function saveMaxima(int $templateId, int $nodeId, array $periodIds, array $maxima): void
    {
        foreach ($maxima as $periodCode => $maximum) {
            DB::table('system_template_maxima')->updateOrInsert(
                ['node_id' => $nodeId, 'periode_id' => $periodIds[$periodCode]],
                ['template_id' => $templateId, 'maxima' => $maximum, 'coefficient' => 1, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
