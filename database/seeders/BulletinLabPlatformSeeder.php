<?php

namespace Database\Seeders;

use App\Models\RolePlateforme;
use App\Models\UtilisateurPlateforme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class BulletinLabPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'ADMIN_PLATEFORME' => 'Administrateur plateforme',
            'SUPPORT_PLATEFORME' => 'Support plateforme',
        ];

        foreach ($roles as $code => $nom) {
            RolePlateforme::query()->updateOrCreate(['code' => $code], ['nom' => $nom]);
        }

        $admin = UtilisateurPlateforme::query()->firstOrNew([
            'email' => 'admin@bulletinlab.app',
        ]);

        if (! $admin->exists) {
            $admin->fill([
                'nom' => 'Administrateur',
                'postnom' => 'Plateforme',
                'mot_de_passe' => Hash::make($this->configuredPassword()),
                'actif' => true,
            ])->save();
        }

        $admin->roles()->syncWithoutDetaching('ADMIN_PLATEFORME');
    }

    private function configuredPassword(): string
    {
        $password = (string) config('seeding.platform_admin_password');

        if (trim($password) === '') {
            throw new LogicException('PLATFORM_ADMIN_PASSWORD doit être renseigné avant le seeding.');
        }

        if (app()->isProduction() && strlen($password) < 12) {
            throw new LogicException('PLATFORM_ADMIN_PASSWORD doit contenir au moins 12 caractères en production.');
        }

        return $password;
    }
}
