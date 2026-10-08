<?php

namespace Database\Seeders;

class BulletinLabPedagogyDemoSeeder extends BulletinLabScientificDemoSeeder
{
    protected const TEMPLATE_PREFIX = 'PEDAGOGIE_';

    protected const CLASS_PREFIX = 'DEMOPED';

    protected const STUDENT_PREFIX = 'DEMOPG00';

    protected const REGISTRATION_PREFIX = 'DEMO-PED-';

    protected const SECTION_NAME = 'Pédagogie générale';

    protected const CONFIGURATION_SEEDER = BulletinLabPedagogySeeder::class;
}
