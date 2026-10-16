<?php

namespace Database\Seeders;

class BulletinLabElectricityDemoSeeder extends BulletinLabScientificDemoSeeder
{
    protected const TEMPLATE_PREFIX = 'ELECTRICITE_';

    protected const CLASS_PREFIX = 'DEMOEL';

    protected const STUDENT_PREFIX = 'DEMOEL00';

    protected const REGISTRATION_PREFIX = 'DEMO-ELEC-';

    protected const SECTION_NAME = 'Electricité générale';

    protected const CONFIGURATION_SEEDER = BulletinLabElectricalConstructionSeeder::class;
}
