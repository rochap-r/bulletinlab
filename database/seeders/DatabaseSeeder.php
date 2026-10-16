<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BulletinLabCoreSeeder::class,
            BulletinLabAcademicSeeder::class,
            BulletinLabLayoutsSeeder::class,
            BulletinLabCalendarSeeder::class,
            BulletinLabSchoolYearSeeder::class,
            BulletinLabTemplateCatalogSeeder::class,
            BulletinLabCtebSeeder::class,
            BulletinLabScientificSeeder::class,
            BulletinLabCommercialeSeeder::class,
            BulletinLabPedagogySeeder::class,
            BulletinLabMechanicsSeeder::class,
            BulletinLabElectricalConstructionSeeder::class,
            BulletinLabBaremesSeeder::class,
            BulletinLabPlatformSeeder::class,
            BulletinLabTestDataSeeder::class,
            BulletinLabModelBulletinSeeder::class,
            BulletinLabScenarioEleveSeeder::class,
            BulletinLabScientificDemoSeeder::class,
            BulletinLabPedagogyDemoSeeder::class,
            BulletinLabAutomotiveDemoSeeder::class,
            BulletinLabGeneralMechanicsDemoSeeder::class,
            BulletinLabElectricityDemoSeeder::class,
            BulletinLabConstructionDemoSeeder::class,
            BulletinLabCommercialDemoSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(TenantAdminDemoSeeder::class);
            $this->call(AuthDemoSeeder::class);
        }
    }
}
