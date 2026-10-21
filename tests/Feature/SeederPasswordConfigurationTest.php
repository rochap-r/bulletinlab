<?php

use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use Database\Seeders\BulletinLabAcademicSeeder;
use Database\Seeders\BulletinLabCalendarSeeder;
use Database\Seeders\BulletinLabCoreSeeder;
use Database\Seeders\BulletinLabLayoutsSeeder;
use Database\Seeders\BulletinLabPlatformSeeder;
use Database\Seeders\BulletinLabSchoolYearSeeder;
use Database\Seeders\BulletinLabTemplateCatalogSeeder;
use Database\Seeders\BulletinLabTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('the platform seeder applies the configured password when rerun', function (): void {
    config()->set('seeding.platform_admin_password', 'first-platform-password');

    $this->seed(BulletinLabPlatformSeeder::class);

    $admin = UtilisateurPlateforme::query()->where('email', 'admin@bulletinlab.app')->firstOrFail();

    expect(Hash::check('first-platform-password', $admin->mot_de_passe))->toBeTrue();

    config()->set('seeding.platform_admin_password', 'second-platform-password');
    $this->seed(BulletinLabPlatformSeeder::class);

    expect(Hash::check('second-platform-password', $admin->fresh()->mot_de_passe))->toBeTrue();
});

test('the test data seeder applies the configured demo passwords', function (): void {
    config()->set([
        'seeding.demo_admin_password' => 'configured-demo-admin',
        'seeding.demo_teacher_password' => 'configured-demo-teacher',
    ]);

    $this->seed([
        BulletinLabCoreSeeder::class,
        BulletinLabAcademicSeeder::class,
        BulletinLabLayoutsSeeder::class,
        BulletinLabCalendarSeeder::class,
        BulletinLabSchoolYearSeeder::class,
        BulletinLabTemplateCatalogSeeder::class,
        BulletinLabTestDataSeeder::class,
    ]);

    $admin = Utilisateur::query()->where('email', 'admin@test.com')->firstOrFail();
    $teacher = Utilisateur::query()->where('email', 'prof@test.com')->firstOrFail();

    expect(Hash::check('configured-demo-admin', $admin->mot_de_passe))->toBeTrue()
        ->and(Hash::check('configured-demo-teacher', $teacher->mot_de_passe))->toBeTrue();
});

test('seeders reject empty configured passwords', function (): void {
    config()->set('seeding.platform_admin_password', '');

    expect(fn () => $this->seed(BulletinLabPlatformSeeder::class))
        ->toThrow(LogicException::class, 'PLATFORM_ADMIN_PASSWORD doit être renseigné');
});
