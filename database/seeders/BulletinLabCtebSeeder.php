<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulletinLabCtebSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['CTEB_7', 'CTEB_8'] as $templateCode) {
            $this->seedPeriods($templateCode);
        }

        $this->seedTemplate('CTEB_7', $this->seventhYearDomains());
        $this->seedTemplate('CTEB_8', $this->eighthYearDomains());
        $this->seedSpecialBlocks('CTEB_7', ['REPECHAGE', 'SIGNATURES']);
        $this->seedSpecialBlocks('CTEB_8', ['REPECHAGE', 'TENASOSP', 'RESULTAT_FINAL', 'JURY', 'SIGNATURES']);
    }

    protected function templateId(string $code): ?int
    {
        $id = DB::table('system_templates_bulletin')->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    protected function seedPeriods(string $templateCode): void
    {
        $templateId = $this->templateId($templateCode);

        if ($templateId === null) {
            return;
        }

        $firstSemesterId = $this->upsertPeriod($templateId, null, 'SEMESTRE_1', 'Premier semestre', 'SEMESTRE', 1, false, true);
        $this->upsertPeriod($templateId, $firstSemesterId, 'P1', 'Première période', 'PERIODE', 2, true, false);
        $this->upsertPeriod($templateId, $firstSemesterId, 'P2', 'Deuxième période', 'PERIODE', 3, true, false);
        $this->upsertPeriod($templateId, $firstSemesterId, 'EXAM_S1', 'Examen du premier semestre', 'EXAMEN', 4, true, false);
        $this->upsertPeriod($templateId, $firstSemesterId, 'TOTAL_S1', 'Total du premier semestre', 'TOTAL', 5, false, true);

        $secondSemesterId = $this->upsertPeriod($templateId, null, 'SEMESTRE_2', 'Second semestre', 'SEMESTRE', 6, false, true);
        $this->upsertPeriod($templateId, $secondSemesterId, 'P3', 'Troisième période', 'PERIODE', 7, true, false);
        $this->upsertPeriod($templateId, $secondSemesterId, 'P4', 'Quatrième période', 'PERIODE', 8, true, false);
        $this->upsertPeriod($templateId, $secondSemesterId, 'EXAM_S2', 'Examen du second semestre', 'EXAMEN', 9, true, false);
        $this->upsertPeriod($templateId, $secondSemesterId, 'TOTAL_S2', 'Total du second semestre', 'TOTAL', 10, false, true);

        $this->upsertPeriod($templateId, null, 'TOTAL_GENERAL', 'Total général', 'TOTAL', 11, false, true);
        $this->upsertPeriod($templateId, null, 'REPECHAGE', 'Examen de repêchage', 'REPECHAGE', 12, true, false);
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

        return (int) DB::table('system_template_periodes')->where('template_id', $templateId)->where('code', $code)->value('id');
    }

    /**
     * @param  array<int, array{name: string, groups: array<int, array{name: string|null, subjects: array<int, array{0: string, 1: int}>}>}>  $domains
     */
    protected function seedTemplate(string $templateCode, array $domains): void
    {
        $templateId = $this->templateId($templateCode);

        if ($templateId === null) {
            return;
        }

        $periodIds = DB::table('system_template_periodes')->where('template_id', $templateId)->pluck('id', 'code');

        foreach ($domains as $domainOrder => $domain) {
            $domainId = $this->upsertNode($templateId, null, 'DOMAINE', $domain['name'], $domainOrder + 1);

            foreach ($domain['groups'] as $groupOrder => $group) {
                $parentId = $group['name'] === null
                    ? $domainId
                    : $this->upsertNode($templateId, $domainId, 'SOUS_DOMAINE', $group['name'], $groupOrder + 1);

                foreach ($group['subjects'] as $subjectOrder => [$subject, $maximum]) {
                    $subjectNodeId = $this->upsertNode(
                        $templateId,
                        $parentId,
                        'MATIERE',
                        $subject,
                        $subjectOrder + 1,
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

                $this->upsertNode($templateId, $parentId, 'SOUS_TOTAL', 'Sous-total', count($group['subjects']) + 1, null, true);
            }
        }
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
            ['nom' => $name, 'nom_court' => mb_substr($name, 0, 80), 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) DB::table('ref_matieres')->where('code', $code)->value('id');
    }

    /** @return array<string, int> */
    protected function periodMaxima(int $maximum): array
    {
        return [
            'P1' => $maximum,
            'P2' => $maximum,
            'EXAM_S1' => $maximum * 2,
            'TOTAL_S1' => $maximum * 4,
            'P3' => $maximum,
            'P4' => $maximum,
            'EXAM_S2' => $maximum * 2,
            'TOTAL_S2' => $maximum * 4,
            'TOTAL_GENERAL' => $maximum * 8,
        ];
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

        $query = DB::table('system_template_nodes')->where('template_id', $templateId)->where('ordre', $order);
        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);

        return (int) $query->value('id');
    }

    /** @param list<string> $blockCodes */
    protected function seedSpecialBlocks(string $templateCode, array $blockCodes): void
    {
        $templateId = $this->templateId($templateCode);

        if ($templateId === null) {
            return;
        }

        $blockIds = DB::table('system_blocs_speciaux')->pluck('id', 'code');

        foreach ($blockCodes as $order => $blockCode) {
            if (! isset($blockIds[$blockCode])) {
                continue;
            }

            DB::table('system_template_blocs')->updateOrInsert(
                ['template_id' => $templateId, 'bloc_id' => $blockIds[$blockCode]],
                ['ordre' => $order + 1, 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    /** @return array<int, array{name: string, groups: array<int, array{name: string|null, subjects: array<int, array{0: string, 1: int}>}>}> */
    private function seventhYearDomains(): array
    {
        return [
            ['name' => 'Domaine des sciences', 'groups' => [
                ['name' => 'Sous-domaine des mathématiques', 'subjects' => [['Arithmétique', 10], ['Statistique', 10], ['Géométrie', 20], ['Algèbre', 40]]],
                ['name' => 'Sous-domaine des sciences de la vie et de la Terre', 'subjects' => [['Anatomie', 10], ['Botanique', 10], ['Zoologie', 10]]],
                ['name' => "Sous-domaine des sciences physiques, technologie et technologies de l'information et de la communication (TIC)", 'subjects' => [['Sciences physiques', 10], ['Technologie', 10], ["Technologies de l'information et de la communication (TIC)", 10]]],
            ]],
            ['name' => 'Domaine des langues', 'groups' => [['name' => null, 'subjects' => [['Anglais', 30], ['Français', 70]]]]],
            ['name' => "Domaine de l'univers social et environnement", 'groups' => [['name' => null, 'subjects' => [['Religion', 20], ['Éducation à la vie', 20], ['Éducation civique et morale', 20], ['Géographie', 20], ['Histoire', 20]]]]],
            ['name' => 'Domaine des arts', 'groups' => [['name' => null, 'subjects' => [['Dessin', 20], ['Musique', 20]]]]],
            ['name' => 'Domaine du développement personnel', 'groups' => [['name' => null, 'subjects' => [['Éducation physique et sportive', 20]]]]],
        ];
    }

    /** @return array<int, array{name: string, groups: array<int, array{name: string|null, subjects: array<int, array{0: string, 1: int}>}>}> */
    private function eighthYearDomains(): array
    {
        return [
            ['name' => 'Domaine des sciences', 'groups' => [
                ['name' => 'Sous-domaine des mathématiques', 'subjects' => [['Arithmétique', 10], ['Statistique', 10], ['Géométrie', 20], ['Algèbre', 40]]],
                ['name' => 'Sous-domaine des sciences de la vie et de la Terre', 'subjects' => [['Anatomie', 10], ['Botanique', 10], ['Nutrition', 10], ['Zoologie', 20]]],
                ['name' => "Sous-domaine des sciences physiques, technologie et technologies de l'information et de la communication (TIC)", 'subjects' => [['Chimie', 10], ['Sciences physiques', 10], ['Technologie', 10], ["Technologies de l'information et de la communication (TIC)", 10]]],
            ]],
            ['name' => 'Domaine des langues', 'groups' => [['name' => null, 'subjects' => [['Anglais', 30], ['Français', 50]]]]],
            ['name' => "Domaine de l'univers social et environnement", 'groups' => [['name' => null, 'subjects' => [['Religion', 20], ['Éducation à la vie', 20], ['Éducation civique et morale', 20], ['Histoire', 20], ['Géographie', 30]]]]],
            ['name' => 'Domaine des arts', 'groups' => [['name' => null, 'subjects' => [['Dessin', 20], ['Musique', 20]]]]],
            ['name' => 'Domaine du développement personnel', 'groups' => [['name' => null, 'subjects' => [['Éducation physique et sportive', 20]]]]],
        ];
    }
}
