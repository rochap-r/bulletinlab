<?php

namespace Database\Seeders;

class BulletinLabCommercialDemoSeeder extends BulletinLabScientificDemoSeeder
{
    protected const TEMPLATE_PREFIX = 'COMMERCIALE_';

    protected const CLASS_PREFIX = 'DEMOCG';

    protected const STUDENT_PREFIX = 'DEMOCG00';

    protected const REGISTRATION_PREFIX = 'DEMO-COM-';

    protected const SECTION_NAME = 'Commerciale & Gestion';

    protected const CONFIGURATION_SEEDER = BulletinLabCommercialeSeeder::class;
}
