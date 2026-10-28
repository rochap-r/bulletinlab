<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionReferenceSeeder extends Seeder
{
    /** @var list<string> */
    private const CONFIGURED_TEMPLATE_CODES = [
        'PRIMAIRE_ELEMENTAIRE_1_2',
        'PRIMAIRE_MOYEN_3_4',
        'PRIMAIRE_TERMINAL_5',
        'PRIMAIRE_TERMINAL_6',
        'CTEB_7',
        'CTEB_8',
        'COMMERCIALE_1',
        'COMMERCIALE_2',
        'COMMERCIALE_3',
        'COMMERCIALE_4',
        'SCIENTIFIQUE_1',
        'SCIENTIFIQUE_2',
        'SCIENTIFIQUE_3',
        'SCIENTIFIQUE_4',
        'PEDAGOGIE_1',
        'PEDAGOGIE_2',
        'PEDAGOGIE_3',
        'PEDAGOGIE_4',
        'ELECTRICITE_1',
        'ELECTRICITE_2',
        'ELECTRICITE_3',
        'ELECTRICITE_4',
        'CONSTRUCTION_1',
        'CONSTRUCTION_2',
        'CONSTRUCTION_3',
        'CONSTRUCTION_4',
        'MECANIQUE_GENERALE_1',
        'MECANIQUE_GENERALE_2',
        'MECANIQUE_GENERALE_3',
        'MECANIQUE_GENERALE_4',
        'MECANIQUE_AUTO_1',
        'MECANIQUE_AUTO_2',
        'MECANIQUE_AUTO_3',
        'MECANIQUE_AUTO_4',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                BulletinLabCoreSeeder::class,
                BulletinLabAcademicSeeder::class,
                BulletinLabLayoutsSeeder::class,
                BulletinLabCalendarSeeder::class,
                BulletinLabSchoolYearSeeder::class,
                BulletinLabTemplateCatalogSeeder::class,
                BulletinLabPrimaireSeeder::class,
                BulletinLabCtebSeeder::class,
                BulletinLabScientificSeeder::class,
                BulletinLabCommercialeSeeder::class,
                BulletinLabPedagogySeeder::class,
                BulletinLabMechanicsSeeder::class,
                BulletinLabElectricalConstructionSeeder::class,
                BulletinLabBaremesSeeder::class,
                TypeEvaluationSeeder::class,
                BulletinLabPlatformSeeder::class,
            ]);

            $this->assertReferenceDataIsComplete();
        }, 3);
    }

    private function assertReferenceDataIsComplete(): void
    {
        $this->assertCodesExist('plans', ['STARTER', 'STANDARD', 'PREMIUM']);
        $this->assertCodesExist('ref_cycles', ['MATERNELLE', 'PRIMAIRE', 'EB', 'HUMANITES']);
        $this->assertCodesExist('system_layouts_bulletin', [
            'RDC_PRIMAIRE_TRIMESTRIEL',
            'RDC_CTEB_DOMAINES',
            'RDC_SCIENTIFIQUE_DOMAINES',
            'RDC_HUMANITES_STANDARD',
            'RDC_HUMANITES_EXAMEN_ETAT',
        ]);
        $this->assertCodesExist('system_templates_bulletin', self::CONFIGURED_TEMPLATE_CODES);
        $this->assertCodesExist('types_evaluations', [
            'INTERROGATION',
            'EXERCICE',
            'DEVOIR',
            'CAHIER',
            'AUTRE',
        ]);
        $this->assertCodesExist('roles_plateforme', ['ADMIN_PLATEFORME', 'SUPPORT_PLATEFORME']);

        if (! DB::table('calendriers_scolaires')->where('code', 'RDC_2026_2027')->exists()) {
            throw new RuntimeException('Le calendrier scolaire de production est absent après sa synchronisation.');
        }

        if (! DB::table('annees_scolaires')->where('libelle', '2026-2027')->exists()) {
            throw new RuntimeException("L'année scolaire de production est absente après sa synchronisation.");
        }

        if (! DB::table('utilisateurs_plateforme')->where('email', 'admin@bulletinlab.app')->exists()) {
            throw new RuntimeException("L'administrateur plateforme initial est absent après sa synchronisation.");
        }

        foreach (self::CONFIGURED_TEMPLATE_CODES as $templateCode) {
            $templateId = DB::table('system_templates_bulletin')->where('code', $templateCode)->value('id');

            if (
                ! DB::table('system_template_nodes')->where('template_id', $templateId)->exists()
                || ! DB::table('system_template_periodes')->where('template_id', $templateId)->exists()
            ) {
                throw new RuntimeException("Le modèle système {$templateCode} est incomplet après sa synchronisation.");
            }
        }
    }

    /** @param list<string> $expectedCodes */
    private function assertCodesExist(string $table, array $expectedCodes): void
    {
        $existingCodes = DB::table($table)
            ->whereIn('code', $expectedCodes)
            ->pluck('code')
            ->all();
        $missingCodes = array_values(array_diff($expectedCodes, $existingCodes));

        if ($missingCodes !== []) {
            throw new RuntimeException(sprintf(
                'Référentiels absents dans %s après synchronisation : %s.',
                $table,
                implode(', ', $missingCodes),
            ));
        }
    }
}
