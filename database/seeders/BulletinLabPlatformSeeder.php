<?php

namespace Database\Seeders;

use App\Models\RolePlateforme;
use App\Models\UtilisateurPlateforme;
use Illuminate\Database\Seeder;

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

        $admin = UtilisateurPlateforme::query()->firstOrCreate(
            ['email' => 'admin@bulletinlab.app'],
            [
                'nom' => 'Administrateur',
                'postnom' => 'Plateforme',
                'mot_de_passe' => bcrypt('password'),
                'actif' => true,
            ],
        );

        $admin->roles()->syncWithoutDetaching('ADMIN_PLATEFORME');
    }
}
