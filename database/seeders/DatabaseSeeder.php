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
            BulletinLabTemplateCatalogSeeder::class,
            BulletinLabCommercialeSeeder::class,
            BulletinLabBaremesSeeder::class,
        ]);
    }
}
