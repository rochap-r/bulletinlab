<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulletinLabPrimaireSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'PRIMAIRE_ELEMENTAIRE_1_2' => $this->elementaryDomains(),
            'PRIMAIRE_MOYEN_3_4' => $this->middleDomains(),
            'PRIMAIRE_TERMINAL_5' => $this->terminalDomains(),
            'PRIMAIRE_TERMINAL_6' => $this->terminalDomains(),
        ];

        foreach ($templates as $templateCode => $domains) {
            $this->seedPeriods($templateCode);
            $this->seedTemplate($templateCode, $domains);
            $this->seedSpecialBlocks(
                $templateCode,
                $templateCode === 'PRIMAIRE_TERMINAL_6'
                    ? ['ENAFEP', 'RESULTAT_FINAL', 'SIGNATURES']
                    : ['SIGNATURES'],
            );
        }
    }

    private function seedPeriods(string $templateCode): void
    {
        $templateId = $this->templateId($templateCode);

        if ($templateId === null) {
            return;
        }

        $periodOrder = 1;

        foreach (range(1, 3) as $trimester) {
            $trimesterId = $this->upsertPeriod(
                $templateId,
                null,
                'TRIMESTRE_'.$trimester,
                match ($trimester) {
                    1 => 'Premier trimestre',
                    2 => 'Deuxième trimestre',
                    3 => 'Troisième trimestre',
                },
                'TRIMESTRE',
                $periodOrder++,
                false,
                true,
            );

            $firstPeriod = ($trimester * 2) - 1;
            $secondPeriod = $trimester * 2;

            $this->upsertPeriod($templateId, $trimesterId, 'P'.$firstPeriod, $this->periodName($firstPeriod), 'PERIODE', $periodOrder++, true, false);
            $this->upsertPeriod($templateId, $trimesterId, 'P'.$secondPeriod, $this->periodName($secondPeriod), 'PERIODE', $periodOrder++, true, false);
            $this->upsertPeriod($templateId, $trimesterId, 'EXAM_T'.$trimester, 'Examen du trimestre '.$trimester, 'EXAMEN', $periodOrder++, true, false);
            $this->upsertPeriod($templateId, $trimesterId, 'TOTAL_T'.$trimester, 'Total du trimestre '.$trimester, 'TOTAL', $periodOrder++, false, true);
        }

        $this->upsertPeriod($templateId, null, 'TOTAL_GENERAL', 'Total général', 'TOTAL', $periodOrder, false, true);
    }

    /**
     * @param  list<array{name: string, groups: list<array{name: string|null, subjects: list<array{0: string, 1: int}>, subtotal?: bool}>}>  $domains
     */
    private function seedTemplate(string $templateCode, array $domains): void
    {
        $templateId = $this->templateId($templateCode);

        if ($templateId === null) {
            return;
        }

        $periodIds = DB::table('system_template_periodes')
            ->where('template_id', $templateId)
            ->pluck('id', 'code');

        foreach ($domains as $domainOrder => $domain) {
            $domainId = $this->upsertNode($templateId, null, 'DOMAINE', $domain['name'], $domainOrder + 1);
            $domainChildOrder = 1;

            foreach ($domain['groups'] as $group) {
                if ($group['name'] !== null) {
                    $parentId = $this->upsertNode(
                        $templateId,
                        $domainId,
                        'SOUS_DOMAINE',
                        $group['name'],
                        $domainChildOrder++,
                    );
                    $subjectOrder = 1;

                    foreach ($group['subjects'] as [$subject, $maximum]) {
                        $this->seedSubject($templateId, $parentId, $subjectOrder++, $subject, $maximum, $periodIds);
                    }

                    if ($group['subtotal'] ?? true) {
                        $this->upsertNode($templateId, $parentId, 'SOUS_TOTAL', 'Sous-total', $subjectOrder, null, true);
                    }

                    continue;
                }

                foreach ($group['subjects'] as [$subject, $maximum]) {
                    $this->seedSubject($templateId, $domainId, $domainChildOrder++, $subject, $maximum, $periodIds);
                }

                if ($group['subtotal'] ?? true) {
                    $this->upsertNode($templateId, $domainId, 'SOUS_TOTAL', 'Sous-total', $domainChildOrder++, null, true);
                }
            }
        }

        $totalGeneralId = $periodIds['TOTAL_GENERAL'] ?? null;

        if ($totalGeneralId !== null) {
            DB::table('system_templates_bulletin')->where('id', $templateId)->update([
                'total_general_maxima' => DB::table('system_template_maxima')
                    ->where('template_id', $templateId)
                    ->where('periode_id', $totalGeneralId)
                    ->sum('maxima'),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedSubject(int $templateId, int $parentId, int $order, string $subject, int $maximum, object $periodIds): void
    {
        $subjectNodeId = $this->upsertNode(
            $templateId,
            $parentId,
            'MATIERE',
            $subject,
            $order,
            $this->subjectId($subject),
        );

        foreach ($this->periodMaxima($maximum) as $periodCode => $periodMaximum) {
            DB::table('system_template_maxima')->updateOrInsert(
                ['node_id' => $subjectNodeId, 'periode_id' => $periodIds[$periodCode]],
                [
                    'template_id' => $templateId,
                    'maxima' => $periodMaximum,
                    'coefficient' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function templateId(string $code): ?int
    {
        $id = DB::table('system_templates_bulletin')->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function upsertPeriod(int $templateId, ?int $parentId, string $code, string $name, string $type, int $order, bool $isEditable, bool $isCalculated): int
    {
        DB::table('system_template_periodes')->updateOrInsert(
            ['template_id' => $templateId, 'code' => $code],
            [
                'parent_id' => $parentId,
                'nom' => $name,
                'type_periode' => $type,
                'ordre' => $order,
                'saisissable' => $isEditable,
                'calculable' => $isCalculated,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return (int) DB::table('system_template_periodes')
            ->where('template_id', $templateId)
            ->where('code', $code)
            ->value('id');
    }

    private function periodName(int $period): string
    {
        return match ($period) {
            1 => 'Première période',
            2 => 'Deuxième période',
            3 => 'Troisième période',
            4 => 'Quatrième période',
            5 => 'Cinquième période',
            6 => 'Sixième période',
        };
    }

    /** @return array<string, int> */
    private function periodMaxima(int $maximum): array
    {
        return [
            'P1' => $maximum,
            'P2' => $maximum,
            'EXAM_T1' => $maximum * 2,
            'TOTAL_T1' => $maximum * 4,
            'P3' => $maximum,
            'P4' => $maximum,
            'EXAM_T2' => $maximum * 2,
            'TOTAL_T2' => $maximum * 4,
            'P5' => $maximum,
            'P6' => $maximum,
            'EXAM_T3' => $maximum * 2,
            'TOTAL_T3' => $maximum * 4,
            'TOTAL_GENERAL' => $maximum * 12,
        ];
    }

    private function subjectId(string $name): int
    {
        $code = Str::of($name)
            ->ascii()
            ->upper()
            ->replace('&', ' ET ')
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->value();

        DB::table('ref_matieres')->updateOrInsert(
            ['code' => $code],
            [
                'nom' => $name,
                'nom_court' => Str::limit($name, 80, ''),
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return (int) DB::table('ref_matieres')->where('code', $code)->value('id');
    }

    private function upsertNode(int $templateId, ?int $parentId, string $type, string $label, int $order, ?int $subjectId = null, bool $calculateSubtotal = false): int
    {
        DB::table('system_template_nodes')->updateOrInsert(
            ['template_id' => $templateId, 'parent_id' => $parentId, 'ordre' => $order],
            [
                'type_node' => $type,
                'matiere_id' => $subjectId,
                'libelle' => $label,
                'afficher_ligne' => true,
                'calculer_sous_total' => $calculateSubtotal,
                'obligatoire' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $query = DB::table('system_template_nodes')
            ->where('template_id', $templateId)
            ->where('ordre', $order);

        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);

        return (int) $query->value('id');
    }

    /** @param  list<string>  $blockCodes */
    private function seedSpecialBlocks(string $templateCode, array $blockCodes): void
    {
        $templateId = $this->templateId($templateCode);

        if ($templateId === null) {
            return;
        }

        DB::table('system_template_blocs')->where('template_id', $templateId)->update([
            'actif' => false,
            'updated_at' => now(),
        ]);

        $blockIds = DB::table('system_blocs_speciaux')->pluck('id', 'code');

        foreach ($blockCodes as $order => $blockCode) {
            if (! isset($blockIds[$blockCode])) {
                continue;
            }

            DB::table('system_template_blocs')->updateOrInsert(
                ['template_id' => $templateId, 'bloc_id' => $blockIds[$blockCode]],
                [
                    'ordre' => $order + 1,
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /** @return list<array{name: string, groups: list<array{name: string|null, subjects: list<array{0: string, 1: int}>, subtotal?: bool}>}> */
    private function elementaryDomains(): array
    {
        return [
            ['name' => 'Domaine des langues', 'groups' => [
                ['name' => 'Langues congolaises', 'subjects' => [['Expression orale', 20], ['Expression écrite', 20]]],
                ['name' => 'Français', 'subjects' => [['Vocabulaire', 10], ['Expression orale', 20]]],
                ['name' => null, 'subjects' => [['Lecture-écriture en langues congolaises', 30]], 'subtotal' => false],
            ]],
            ['name' => 'Domaine des mathématiques, sciences et technologie', 'groups' => [
                ['name' => 'Mathématiques', 'subjects' => [['Mesures des grandeurs', 10], ['Formes géométriques', 10], ['Numération', 20], ['Opérations', 20], ['Problèmes', 20]]],
                ['name' => 'Sciences', 'subjects' => [['Sciences d’éveil', 20]]],
                ['name' => 'Technologie', 'subjects' => [['Technologie', 10]]],
            ]],
            ['name' => 'Domaine de l’univers social et environnement', 'groups' => [
                ['name' => null, 'subjects' => [['Éducation civique et morale', 10], ['Éducation santé et environnement', 10]]],
            ]],
            ['name' => 'Domaine des arts', 'groups' => [
                ['name' => 'Éducation artistique', 'subjects' => [['Arts plastiques', 10], ['Arts dramatiques', 10]]],
            ]],
            ['name' => 'Domaine du développement personnel', 'groups' => [
                ['name' => null, 'subjects' => [['Éducation physique et sportive', 10], ['Initiation aux travaux productifs', 10], ['Religion (1)', 10]]],
            ]],
        ];
    }

    /** @return list<array{name: string, groups: list<array{name: string|null, subjects: list<array{0: string, 1: int}>, subtotal?: bool}>}> */
    private function middleDomains(): array
    {
        return [
            ['name' => 'Domaine des langues', 'groups' => [
                ['name' => 'Langues congolaises', 'subjects' => [['Expression orale et vocabulaire', 10], ['Grammaire et conjugaison', 10], ['Orthographe et rédaction', 5]]],
                ['name' => 'Français', 'subjects' => [['Expression orale, récit et vocabulaire', 10], ['Orthographe, phrases, écrit et rédaction', 10], ['Grammaire, conjugaison et analyse', 15]]],
                ['name' => null, 'subjects' => [['Lecture-écriture en langues congolaises', 30], ['Lecture-écriture en langue française', 30]], 'subtotal' => false],
            ]],
            ['name' => 'Domaine des mathématiques, sciences et technologie', 'groups' => [
                ['name' => 'Mathématiques', 'subjects' => [['Numération', 10], ['Opérations', 10], ['Mesures des grandeurs', 10], ['Formes géométriques', 10], ['Problèmes', 20]]],
                ['name' => 'Sciences', 'subjects' => [['Zoologie, botanique et informatique', 10]]],
                ['name' => 'Technologie', 'subjects' => [['Technologie', 20]]],
            ]],
            ['name' => 'Domaine de l’univers social et environnement', 'groups' => [
                ['name' => null, 'subjects' => [['Éducation civique et morale', 10], ['Éducation santé et environnement', 10], ['Géographie', 10], ['Histoire', 10]]],
            ]],
            ['name' => 'Domaine des arts', 'groups' => [
                ['name' => 'Éducation artistique', 'subjects' => [['Arts plastiques', 10], ['Arts dramatiques', 10]]],
            ]],
            ['name' => 'Domaine du développement personnel', 'groups' => [
                ['name' => null, 'subjects' => [['Éducation physique et sportive', 10], ['Initiation aux travaux productifs', 10], ['Religion (1)', 10]]],
            ]],
        ];
    }

    /** @return list<array{name: string, groups: list<array{name: string|null, subjects: list<array{0: string, 1: int}>, subtotal?: bool}>}> */
    private function terminalDomains(): array
    {
        return [
            ['name' => 'Domaine des langues', 'groups' => [
                ['name' => 'Langues congolaises', 'subjects' => [['Grammaire et conjugaison', 10], ['Expression orale et vocabulaire', 10], ['Orthographe et rédaction', 10]]],
                ['name' => 'Français', 'subjects' => [['Expression orale et vocabulaire', 10], ['Orthographe', 10], ['Rédaction', 10], ['Grammaire, conjugaison et analyse', 20]]],
                ['name' => null, 'subjects' => [['Lecture-écriture en langues congolaises', 20], ['Lecture-écriture en langue française', 20]], 'subtotal' => false],
            ]],
            ['name' => 'Domaine des mathématiques, sciences et technologie', 'groups' => [
                ['name' => 'Mathématiques', 'subjects' => [['Numération', 10], ['Opérations', 10], ['Mesures des grandeurs', 10], ['Formes géométriques', 10], ['Problèmes', 20]]],
                ['name' => 'Sciences', 'subjects' => [['Physique et informatique', 10], ['Anatomie, zoologie et botanique', 20]]],
                ['name' => 'Technologie', 'subjects' => [['Technologie', 10]]],
            ]],
            ['name' => 'Domaine de l’univers social et environnement', 'groups' => [
                ['name' => null, 'subjects' => [['Éducation civique et morale', 10], ['Éducation santé et environnement', 10], ['Géographie', 10], ['Histoire', 10]]],
            ]],
            ['name' => 'Domaine des arts', 'groups' => [
                ['name' => null, 'subjects' => [['Arts plastiques', 10], ['Arts dramatiques', 10]]],
            ]],
            ['name' => 'Domaine du développement personnel', 'groups' => [
                ['name' => null, 'subjects' => [['Initiation aux travaux productifs', 10], ['Éducation physique et sportive', 10], ['Religion (1)', 10]]],
            ]],
        ];
    }
}
