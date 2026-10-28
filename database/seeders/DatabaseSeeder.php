<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductionReferenceSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
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
                TenantAdminDemoSeeder::class,
                AuthDemoSeeder::class,
            ]);
        }
    }
}
