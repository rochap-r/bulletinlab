<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabCommercialeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['COMMERCIALE_1', 'COMMERCIALE_2', 'COMMERCIALE_3', 'COMMERCIALE_4'] as $templateCode) {
            $this->seedSemestrielPeriods($templateCode);
        }

        $this->seedCommerciale1();
        $this->seedCommerciale2();
        $this->seedCommerciale3();
        $this->seedCommerciale4();
    }

    protected function templateId(string $code): ?int
    {
        $id = DB::table('system_templates_bulletin')->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function matiereId(string $nom): ?int
    {
        $code = strtoupper(str_replace([' ', 'à', 'é', 'è', 'ê', 'ç', '&', '.', '-', '/'], ['_', 'A', 'E', 'E', 'E', 'C', 'ET', '', '_', '_'], $nom));
        DB::table('ref_matieres')->updateOrInsert(
            ['code' => $code],
            ['nom' => $nom, 'nom_court' => mb_substr($nom, 0, 80), 'created_at' => now(), 'updated_at' => now()]
        );

        return DB::table('ref_matieres')->where('code', $code)->value('id');
    }

    protected function seedSemestrielPeriods(string $templateCode): void
    {
        $templateId = $this->templateId($templateCode);
        if (! $templateId) {
            return;
        }

        $periods = [
            ['P1', '1ère période', 'PERIODE', 1, true, false],
            ['P2', '2ème période', 'PERIODE', 2, true, false],
            ['EXAM_S1', 'Examen 1er semestre', 'EXAMEN', 3, true, false],
            ['TOTAL_S1', 'Total 1er semestre', 'TOTAL', 4, false, true],
            ['P3', '3ème période', 'PERIODE', 5, true, false],
            ['P4', '4ème période', 'PERIODE', 6, true, false],
            ['EXAM_S2', 'Examen 2ème semestre', 'EXAMEN', 7, true, false],
            ['TOTAL_S2', 'Total 2ème semestre', 'TOTAL', 8, false, true],
            ['TOTAL_GENERAL', 'Total général', 'TOTAL', 9, false, true],
            ['REPECHAGE', 'Examen de repêchage', 'REPECHAGE', 10, true, false],
            ['REPECHAGE_SIGN', 'Sign. Prof.', 'REPECHAGE', 11, true, false],
        ];

        foreach ($periods as [$code, $nom, $type, $ordre, $saisissable, $calculable]) {
            DB::table('system_template_periodes')->updateOrInsert(
                ['template_id' => $templateId, 'code' => $code],
                [
                    'nom' => $nom,
                    'type_periode' => $type,
                    'ordre' => $ordre,
                    'saisissable' => $saisissable,
                    'calculable' => $calculable,
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function maximaForGroup(int $m): array
    {
        if ($m === 10) {
            return ['P1' => 10, 'P2' => 10, 'EXAM_S1' => 20, 'TOTAL_S1' => 40, 'P3' => 10, 'P4' => 10, 'EXAM_S2' => 20, 'TOTAL_S2' => 40, 'TOTAL_GENERAL' => 80];
        }
        if ($m === 20) {
            return ['P1' => 20, 'P2' => 20, 'EXAM_S1' => 40, 'TOTAL_S1' => 80, 'P3' => 20, 'P4' => 20, 'EXAM_S2' => 40, 'TOTAL_S2' => 80, 'TOTAL_GENERAL' => 160];
        }
        if ($m === 30) {
            return ['P1' => 30, 'P2' => 30, 'EXAM_S1' => 60, 'TOTAL_S1' => 120, 'P3' => 30, 'P4' => 30, 'EXAM_S2' => 60, 'TOTAL_S2' => 120, 'TOTAL_GENERAL' => 240];
        }
        if ($m === 40) {
            return ['P1' => 40, 'P2' => 40, 'EXAM_S1' => 80, 'TOTAL_S1' => 160, 'P3' => 40, 'P4' => 40, 'EXAM_S2' => 80, 'TOTAL_S2' => 160, 'TOTAL_GENERAL' => 320];
        }
        if ($m === 50) {
            return ['P1' => 50, 'P2' => 50, 'EXAM_S1' => 100, 'TOTAL_S1' => 200, 'P3' => 50, 'P4' => 50, 'EXAM_S2' => 100, 'TOTAL_S2' => 200, 'TOTAL_GENERAL' => 400];
        }
        if ($m === 60) {
            return ['P1' => 60, 'P2' => 60, 'EXAM_S1' => 120, 'TOTAL_S1' => 240, 'P3' => 60, 'P4' => 60, 'EXAM_S2' => 120, 'TOTAL_S2' => 240, 'TOTAL_GENERAL' => 480];
        }
        if ($m === 100) {
            return ['P1' => 100, 'P2' => 100, 'EXAM_S1' => 200, 'TOTAL_S1' => null, 'P3' => 100, 'P4' => 100, 'EXAM_S2' => 200, 'TOTAL_S2' => null, 'TOTAL_GENERAL' => 400];
        }

        return [];
    }

    protected function seedNodes(string $templateCode, array $groups): void
    {
        $templateId = $this->templateId($templateCode);
        if (! $templateId) {
            return;
        }

        $periodIds = DB::table('system_template_periodes')->where('template_id', $templateId)->pluck('id', 'code');

        $ordreGroup = 1;
        foreach ($groups as $group) {
            $max = $group['max'];
            $subjects = $group['subjects'];

            $this->upsertNode($templateId, null, 'GROUPE_MAXIMA', "MAXIMA {$max}", $ordreGroup++, null);
            $groupId = $this->nodeId($templateId, null, $ordreGroup - 1);

            $ordre = 1;
            foreach ($subjects as $subject) {
                $this->upsertNode($templateId, $groupId, 'MATIERE', $subject, $ordre, $this->matiereId($subject));
                $nodeId = $this->nodeId($templateId, $groupId, $ordre);

                foreach ($this->maximaForGroup((int) $max) as $pCode => $maxima) {
                    if ($maxima === null || ! isset($periodIds[$pCode])) {
                        continue;
                    }
                    DB::table('system_template_maxima')->updateOrInsert(
                        ['node_id' => $nodeId, 'periode_id' => $periodIds[$pCode]],
                        [
                            'template_id' => $templateId,
                            'maxima' => $maxima,
                            'coefficient' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                $ordre++;
            }
        }
    }

    private function upsertNode(
        int $templateId,
        ?int $parentId,
        string $typeNode,
        string $libelle,
        int $ordre,
        ?int $matiereId,
    ): void {
        DB::table('system_template_nodes')->updateOrInsert(
            ['template_id' => $templateId, 'parent_id' => $parentId, 'ordre' => $ordre],
            [
                'type_node' => $typeNode,
                'matiere_id' => $matiereId,
                'libelle' => $libelle,
                'afficher_ligne' => true,
                'calculer_sous_total' => false,
                'obligatoire' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function nodeId(int $templateId, ?int $parentId, int $ordre): int
    {
        $query = DB::table('system_template_nodes')
            ->where('template_id', $templateId)
            ->where('ordre', $ordre)
            ->orderBy('id');

        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);

        return (int) $query->value('id');
    }

    private function seedCommerciale1(): void
    {
        $this->seedNodes('COMMERCIALE_1', [
            ['max' => 10, 'subjects' => ['Religion', 'Droit', 'Education à la Vie', 'Ed. civ. & morale', 'Education physique & sportive', 'Géographie économique']],
            ['max' => 20, 'subjects' => ['Correspondance commerciale anglaise', 'Correspondance commerciale française', 'Fiscalité', 'Mathématiques financières', 'Mathématiques générales']],
            ['max' => 20, 'subjects' => ['Activités complémentaires']],
            ['max' => 30, 'subjects' => ['Anglais', 'Documentation commerciale']],
            ['max' => 40, 'subjects' => ['Français', 'Informatique']],
            ['max' => 60, 'subjects' => ['Comptabilité générale']],
            ['max' => 100, 'subjects' => ['Pratique professionnelle']],
        ]);
    }

    private function seedCommerciale2(): void
    {
        $this->seedNodes('COMMERCIALE_2', [
            ['max' => 10, 'subjects' => ['Religion', 'Droit', 'Education à la Vie', 'Ed. civ. & morale', 'Education physique & sportive', 'Géographie économique']],
            ['max' => 20, 'subjects' => ['Correspondance commerciale anglaise', 'Correspondance commerciale française', 'Fiscalité', 'Mathématiques générales', 'Mathématiques financières']],
            ['max' => 20, 'subjects' => ['Activités complémentaires']],
            ['max' => 30, 'subjects' => ['Anglais', 'Documentation commerciale']],
            ['max' => 40, 'subjects' => ['Français', 'Informatique']],
            ['max' => 60, 'subjects' => ['Comptabilité générale']],
            ['max' => 100, 'subjects' => ['Pratique professionnelle']],
        ]);
    }

    private function seedCommerciale3(): void
    {
        $this->seedNodes('COMMERCIALE_3', [
            ['max' => 10, 'subjects' => ['Religion', 'Education à la Vie', 'Ed. civ. & morale', 'Education physique & sportive', 'Géographie économique', 'Fiscalité', 'Mathématiques financières', 'Opérations des banques et crédits']],
            ['max' => 10, 'subjects' => ['Activités complémentaires']],
            ['max' => 20, 'subjects' => ['Correspondance commerciale anglaise', 'Correspondance commerciale française', 'Droit', 'Economie politique', 'Entrepreneuriat', 'Mathématiques générales']],
            ['max' => 30, 'subjects' => ['Anglais']],
            ['max' => 40, 'subjects' => ['Français', 'Informatique']],
            ['max' => 60, 'subjects' => ['Comptabilité générale']],
            ['max' => 100, 'subjects' => ['Pratique professionnelle']],
        ]);
    }

    private function seedCommerciale4(): void
    {
        $this->seedNodes('COMMERCIALE_4', [
            ['max' => 10, 'subjects' => ['Religion', 'Correspondance commerciale anglaise', 'Correspondance commerciale française', 'Déontologie professionnelle', 'Education à la Vie', 'Ed. civ. & morale', 'Education physique & sportive', 'Finances publiques', 'Fiscalité']],
            ['max' => 10, 'subjects' => ['Activités complémentaires']],
            ['max' => 20, 'subjects' => ['Droit', 'Economie de développement', 'Mathématiques générales', 'Organisation des entreprises']],
            ['max' => 30, 'subjects' => ['Anglais']],
            ['max' => 40, 'subjects' => ['Comptabilité analytique', 'Français', 'Informatique']],
            ['max' => 50, 'subjects' => ['Comptabilité générale']],
            ['max' => 100, 'subjects' => ['Pratique professionnelle']],
        ]);
    }
}
