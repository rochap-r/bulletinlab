<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

/**
 * Jeu de données pour tester l'authentification multi-tenant sur l'interface :
 * un même enseignant rattaché à deux tenants avec un seul mot de passe,
 * un admin par tenant et un compte à mot de passe temporaire (1re connexion).
 */
class AuthDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Le jeu démo d’authentification est réservé aux environnements local et testing.');
        }

        DB::transaction(function (): void {
            $adminRole = Role::query()->firstOrCreate(
                ['tenant_id' => null, 'code' => 'ADMIN_TENANT'],
                ['nom' => 'Administrateur tenant', 'system_role' => true],
            );
            $teacherRole = Role::query()->firstOrCreate(
                ['tenant_id' => null, 'code' => 'ENSEIGNANT'],
                ['nom' => 'Enseignant', 'system_role' => true],
            );

            $tenantB = Tenant::query()->firstOrCreate(
                ['slug' => 'ecole-demo-b'],
                [
                    'uuid' => (string) Str::uuid(),
                    'nom_espace' => 'École Démo B',
                    'type_tenant' => 'ECOLE',
                    'statut' => 'ACTIF',
                ]
            );

            $schoolB = Etablissement::query()->firstOrCreate(
                ['tenant_id' => $tenantB->getKey(), 'nom' => 'Institut Démo B'],
                ['type_etablissement' => 'SECONDAIRE', 'ville' => 'Kinshasa', 'actif' => true]
            );

            // Même enseignant que ecole-test (+243000000003) : même hash donc même mot de passe.
            $source = Utilisateur::query()
                ->where('telephone_normalise', '+243000000003')
                ->where('actif', true)
                ->orderBy('id')
                ->first();

            if ($source !== null) {
                $teacherB = Utilisateur::query()->firstOrCreate(
                    ['tenant_id' => $tenantB->getKey(), 'telephone' => '+243000000003'],
                    [
                        'etablissement_id' => $schoolB->getKey(),
                        'nom' => $source->nom,
                        'postnom' => $source->postnom,
                        'prenom' => $source->prenom,
                        'email' => $source->email,
                        'mot_de_passe' => $source->mot_de_passe,
                        'doit_changer_mot_de_passe' => false,
                        'actif' => true,
                    ]
                );
                $teacherB->roles()->syncWithoutDetaching([$teacherRole->getKey()]);
            }

            // Compte à mot de passe temporaire (1re connexion forcée).
            $fresh = Utilisateur::query()->firstOrCreate(
                ['tenant_id' => $tenantB->getKey(), 'telephone' => '+243000000004'],
                [
                    'etablissement_id' => $schoolB->getKey(),
                    'nom' => 'Nouveau',
                    'prenom' => 'Enseignant',
                    'mot_de_passe' => Hash::make('Temporaire-1234'),
                    'doit_changer_mot_de_passe' => true,
                    'actif' => true,
                ]
            );
            $fresh->roles()->syncWithoutDetaching([$teacherRole->getKey()]);

            // Admin de l'espace B.
            $adminB = Utilisateur::query()->firstOrCreate(
                ['tenant_id' => $tenantB->getKey(), 'telephone' => '+243000000005'],
                [
                    'etablissement_id' => null,
                    'nom' => 'Admin',
                    'prenom' => 'Démo B',
                    'mot_de_passe' => Hash::make('password123'),
                    'doit_changer_mot_de_passe' => false,
                    'actif' => true,
                ]
            );
            $adminB->roles()->syncWithoutDetaching([$adminRole->getKey()]);
        });
    }
}
