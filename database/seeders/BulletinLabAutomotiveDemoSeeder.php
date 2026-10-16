<?php

namespace Database\Seeders;

class BulletinLabAutomotiveDemoSeeder extends BulletinLabScientificDemoSeeder
{
    protected const TEMPLATE_PREFIX = 'MECANIQUE_AUTO_';

    protected const CLASS_PREFIX = 'DEMOMA';

    protected const STUDENT_PREFIX = 'DEMOMA00';

    protected const REGISTRATION_PREFIX = 'DEMO-MAUTO-';

    protected const SECTION_NAME = 'Mécanique automobile';

    protected const CONFIGURATION_SEEDER = BulletinLabMechanicsSeeder::class;
}
