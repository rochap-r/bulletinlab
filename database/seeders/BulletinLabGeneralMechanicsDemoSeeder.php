<?php

namespace Database\Seeders;

class BulletinLabGeneralMechanicsDemoSeeder extends BulletinLabScientificDemoSeeder
{
    protected const TEMPLATE_PREFIX = 'MECANIQUE_GENERALE_';

    protected const CLASS_PREFIX = 'DEMOMG';

    protected const STUDENT_PREFIX = 'DEMOMG00';

    protected const REGISTRATION_PREFIX = 'DEMO-MGEN-';

    protected const SECTION_NAME = 'Mécanique générale';

    protected const CONFIGURATION_SEEDER = BulletinLabMechanicsSeeder::class;
}
