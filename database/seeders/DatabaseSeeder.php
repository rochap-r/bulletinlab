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
            BulletinLabBaremesSeeder::class,
            BulletinLabPlatformSeeder::class,
            BulletinLabTestDataSeeder::class,
            BulletinLabModelBulletinSeeder::class,
            BulletinLabScenarioEleveSeeder::class,
            BulletinLabScientificDemoSeeder::class,
            BulletinLabPedagogyDemoSeeder::class,
        ]);
    }
}
