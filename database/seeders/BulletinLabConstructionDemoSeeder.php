<?php

namespace Database\Seeders;

class BulletinLabConstructionDemoSeeder extends BulletinLabScientificDemoSeeder
{
    protected const TEMPLATE_PREFIX = 'CONSTRUCTION_';

    protected const CLASS_PREFIX = 'DEMOCN';

    protected const STUDENT_PREFIX = 'DEMOCN00';

    protected const REGISTRATION_PREFIX = 'DEMO-CONST-';

    protected const SECTION_NAME = 'Construction';

    protected const CONFIGURATION_SEEDER = BulletinLabElectricalConstructionSeeder::class;
}
