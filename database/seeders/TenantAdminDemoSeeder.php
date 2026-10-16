<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class TenantAdminDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Le compte administrateur de démonstration est réservé aux environnements local et testing.');
        }

        DB::transaction(function (): void {
            $tenant = Tenant::query()->where('slug', 'ecole-test')->firstOrFail();
            $role = Role::query()->firstOrCreate(
                ['tenant_id' => $tenant->getKey(), 'code' => 'ADMIN_ECOLE'],
                ['nom' => 'Administrateur école', 'system_role' => false],
            );
            $user = Utilisateur::query()->firstOrCreate(
                ['tenant_id' => $tenant->getKey(), 'telephone' => '+243000000002'],
                [
                    'nom' => 'Administrateur',
                    'email' => 'admin@test.com',
                    'mot_de_passe' => Hash::make('password123'),
                    'doit_changer_mot_de_passe' => true,
                    'actif' => true,
                ],
            );
            $user->roles()->syncWithoutDetaching([$role->getKey()]);
        });
    }
}
