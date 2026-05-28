<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BulletinLabTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = DB::table('tenants')->updateOrInsert(
            ['slug' => 'ecole-test'],
            [
                'uuid' => Str::uuid(),
                'nom_espace' => 'École Test',
                'type_tenant' => 'ECOLE',
                'statut' => 'ACTIF',
                'updated_at' => now(),
            ]
        );

        $tenantId = DB::table('tenants')->where('slug', 'ecole-test')->value('id');

        $etablissementId = DB::table('etablissements')->updateOrInsert(
            ['tenant_id' => $tenantId, 'code_ministere' => 'CST'],
            [
                'nom' => 'Complexe Scolaire Test',
                'province' => 'Kinshasa',
                'ville' => 'Kinshasa',
                'adresse' => 'Adresse CST',
                'telephone' => '+243000000001',
                'email' => 'info@cst.cd',
                'actif' => true,
                'updated_at' => now(),
            ]
        );

        $etablissementId = DB::table('etablissements')->where('tenant_id', $tenantId)->where('code_ministere', 'CST')->value('id');

        $roleId = DB::table('roles')->updateOrInsert(
            ['tenant_id' => $tenantId, 'code' => 'ADMIN'],
            [
                'nom' => 'Administrateur',
                'system_role' => false,
                'updated_at' => now(),
            ]
        );

        $roleId = DB::table('roles')->where('tenant_id', $tenantId)->where('code', 'ADMIN')->value('id');

        $userId = DB::table('utilisateurs')->updateOrInsert(
            ['tenant_id' => $tenantId, 'telephone' => '+243000000002'],
            [
                'etablissement_id' => $etablissementId,
                'nom' => 'Admin',
                'postnom' => 'Test',
                'prenom' => 'User',
                'email' => 'admin@test.com',
                'mot_de_passe' => Hash::make('password123'),
                'actif' => true,
                'updated_at' => now(),
            ]
        );

        $userId = DB::table('utilisateurs')->where('tenant_id', $tenantId)->where('telephone', '+243000000002')->value('id');

        DB::table('utilisateur_roles')->updateOrInsert(
            ['utilisateur_id' => $userId, 'role_id' => $roleId],
            []
        );

        $calendrierId = DB::table('calendriers_scolaires')->where('code', 'RDC_2026_2027')->value('id');

        $anneeScolaireId = DB::table('annees_scolaires')->updateOrInsert(
            ['tenant_id' => $tenantId, 'libelle' => '2026-2027'],
            [
                'calendrier_scolaire_id' => $calendrierId,
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-07-31',
                'active' => true,
                'cloturee' => false,
                'updated_at' => now(),
            ]
        );

        $anneeScolaireId = DB::table('annees_scolaires')->where('tenant_id', $tenantId)->where('libelle', '2026-2027')->value('id');

        $niveauId = DB::table('ref_niveaux')->where('code', 'HUM_1')->value('id');
        $optionId = DB::table('ref_options')->where('code', 'COMMERCIALE_GESTION')->value('id');
        $cycleId = DB::table('ref_cycles')->where('code', 'HUMANITES')->value('id');
        $degreId = DB::table('ref_degres')->where('code', 'HUMANITES_TECHNIQUES')->value('id');
        $templateId = DB::table('system_templates_bulletin')->where('code', 'COMMERCIALE_1')->value('id');

        $classeId = DB::table('classes')->updateOrInsert(
            ['tenant_id' => $tenantId, 'etablissement_id' => $etablissementId, 'annee_scolaire_id' => $anneeScolaireId, 'nom' => '1ère Commerciale A'],
            [
                'cycle_id' => $cycleId,
                'degre_id' => $degreId,
                'niveau_id' => $niveauId,
                'option_id' => $optionId,
                'system_template_id' => $templateId,
                'parallele' => 'A',
                'code' => '1CA',
                'active' => true,
                'updated_at' => now(),
            ]
        );

        $classeId = DB::table('classes')->where('tenant_id', $tenantId)->where('nom', '1ère Commerciale A')->value('id');

        $eleves = [
            ['Kabongo', 'Jean', 'Pierre', 'M', '2010-05-15'],
            ['Mutombo', 'Marie', 'Claire', 'F', '2010-08-22'],
            ['Luboya', 'François', 'Michel', 'M', '2010-03-10'],
            ['Makaya', 'Annie', 'Louise', 'F', '2010-11-30'],
            ['Kasongo', 'Paul', 'André', 'M', '2010-07-18'],
        ];

        foreach ($eleves as [$nom, $postnom, $prenom, $sexe, $dateNaissance]) {
            $eleveId = DB::table('eleves')->updateOrInsert(
                ['tenant_id' => $tenantId, 'nom' => $nom, 'postnom' => $postnom, 'prenom' => $prenom],
                [
                    'etablissement_id' => $etablissementId,
                    'sexe' => $sexe,
                    'date_naissance' => $dateNaissance,
                    'actif' => true,
                    'updated_at' => now(),
                ]
            );

            $eleveId = DB::table('eleves')->where('tenant_id', $tenantId)->where('nom', $nom)->where('postnom', $postnom)->where('prenom', $prenom)->value('id');

            DB::table('inscriptions')->updateOrInsert(
                ['tenant_id' => $tenantId, 'eleve_id' => $eleveId, 'classe_id' => $classeId, 'annee_scolaire_id' => $anneeScolaireId],
                [
                    'date_inscription' => now(),
                    'statut' => 'ACTIF',
                    'updated_at' => now(),
                ]
            );
        }

        $enseignantRoleId = DB::table('roles')->updateOrInsert(
            ['tenant_id' => $tenantId, 'code' => 'ENSEIGNANT'],
            [
                'nom' => 'Enseignant',
                'system_role' => false,
                'updated_at' => now(),
            ]
        );

        $enseignantRoleId = DB::table('roles')->where('tenant_id', $tenantId)->where('code', 'ENSEIGNANT')->value('id');

        $enseignantId = DB::table('utilisateurs')->updateOrInsert(
            ['tenant_id' => $tenantId, 'telephone' => '+243000000003'],
            [
                'etablissement_id' => $etablissementId,
                'nom' => 'Prof',
                'postnom' => 'Test',
                'prenom' => 'Enseignant',
                'email' => 'prof@test.com',
                'mot_de_passe' => Hash::make('password123'),
                'actif' => true,
                'updated_at' => now(),
            ]
        );

        $enseignantId = DB::table('utilisateurs')->where('tenant_id', $tenantId)->where('telephone', '+243000000003')->value('id');

        DB::table('utilisateur_roles')->updateOrInsert(
            ['utilisateur_id' => $enseignantId, 'role_id' => $enseignantRoleId],
            []
        );
    }
}
