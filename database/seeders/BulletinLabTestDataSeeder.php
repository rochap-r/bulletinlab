<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

class BulletinLabTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = $this->configuredPassword('demo_admin_password', 'DEMO_ADMIN_PASSWORD');
        $teacherPassword = $this->configuredPassword('demo_teacher_password', 'DEMO_TEACHER_PASSWORD');

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
                'province' => 'Lualaba',
                'province_educationnelle' => 'LUALABA 1',
                'ville' => 'Kolwezi',
                'commune_territoire' => 'DILALA',
                'adresse' => 'Adresse CST',
                'telephone' => '+243000000001',
                'email' => 'info@cst.cd',
                'actif' => true,
                'public_id' => (string) Str::ulid(),
                'updated_at' => now(),
            ]
        );

        $etablissementId = DB::table('etablissements')->where('tenant_id', $tenantId)->where('code_ministere', 'CST')->value('id');

        DB::table('etablissements')
            ->where('id', $etablissementId)
            ->whereNull('public_id')
            ->update(['public_id' => (string) Str::ulid()]);

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
                'telephone_normalise' => '+243000000002',
                'nom' => 'Admin',
                'postnom' => 'Test',
                'prenom' => 'User',
                'email' => 'admin@test.com',
                'mot_de_passe' => Hash::make($adminPassword),
                'actif' => true,
                'public_id' => (string) Str::ulid(),
                'updated_at' => now(),
            ]
        );

        $userId = DB::table('utilisateurs')->where('tenant_id', $tenantId)->where('telephone', '+243000000002')->value('id');

        DB::table('utilisateurs')
            ->where('id', $userId)
            ->whereNull('public_id')
            ->update(['public_id' => (string) Str::ulid()]);

        DB::table('utilisateur_roles')->updateOrInsert(
            ['utilisateur_id' => $userId, 'role_id' => $roleId],
            []
        );

        $calendrierId = DB::table('calendriers_scolaires')->where('code', 'RDC_2026_2027')->value('id');

        $anneeScolaireId = DB::table('annees_scolaires')
            ->where('calendrier_scolaire_id', $calendrierId)
            ->value('id');

        if (! $anneeScolaireId) {
            throw new RuntimeException(
                'The shared school year must be seeded before tenant data. '
                .'Run BulletinLabSchoolYearSeeder first.'
            );
        }

        $optionIds = DB::table('ref_options')->pluck('id', 'code');
        $cycleIds = DB::table('ref_cycles')->pluck('id', 'code');
        $degreIds = DB::table('ref_degres')->pluck('id', 'code');

        $classes = [
            ['template' => 'COMMERCIALE_1', 'nom' => '1ère Commerciale A', 'code' => '1CA', 'niveau' => 'HUM_1', 'cycle' => 'HUMANITES', 'degre' => 'HUMANITES_TECHNIQUES', 'option' => 'COMMERCIALE_GESTION'],
            ['template' => 'COMMERCIALE_2', 'nom' => '2ème Commerciale A', 'code' => '2CA', 'niveau' => 'HUM_2', 'cycle' => 'HUMANITES', 'degre' => 'HUMANITES_TECHNIQUES', 'option' => 'COMMERCIALE_GESTION'],
            ['template' => 'COMMERCIALE_3', 'nom' => '3ème Commerciale A', 'code' => '3CA', 'niveau' => 'HUM_3', 'cycle' => 'HUMANITES', 'degre' => 'HUMANITES_TECHNIQUES', 'option' => 'COMMERCIALE_GESTION'],
            ['template' => 'COMMERCIALE_4', 'nom' => '4ème Commerciale A', 'code' => '4CA', 'niveau' => 'HUM_4', 'cycle' => 'HUMANITES', 'degre' => 'HUMANITES_TECHNIQUES', 'option' => 'COMMERCIALE_GESTION'],
            ['template' => 'CTEB_7', 'nom' => '7ème EB A', 'code' => '7EBA', 'niveau' => 'CTEB_7', 'cycle' => 'EB', 'degre' => 'CTEB', 'option' => null],
            ['template' => 'CTEB_8', 'nom' => '8ème EB A', 'code' => '8EBA', 'niveau' => 'CTEB_8', 'cycle' => 'EB', 'degre' => 'CTEB', 'option' => null],
        ];

        $elevesParTemplate = [
            'COMMERCIALE_1' => [
                ['Kabongo', 'Jean', 'Pierre', 'M', '2010-05-15', 'Kolwezi', '24B983142'],
                ['Mutombo', 'Marie', 'Claire', 'F', '2010-08-22', 'Lubumbashi', '24B983143'],
                ['Luboya', 'François', 'Michel', 'M', '2010-03-10', 'Kolwezi', '24B983144'],
                ['Makaya', 'Annie', 'Louise', 'F', '2010-11-30', 'Likasi', '24B983145'],
                ['Kasongo', 'Paul', 'André', 'M', '2010-07-18', 'Kolwezi', '24B983146'],
            ],
            'COMMERCIALE_2' => [
                ['Ilunga', 'Joseph', 'Patrick', 'M', '2009-04-12', 'Kolwezi', '24B983147'],
                ['Mbuyi', 'Thérèse', 'Nicole', 'F', '2009-06-25', 'Lubumbashi', '24B983148'],
                ['Nkulu', 'Éric', 'Bertrand', 'M', '2009-02-08', 'Likasi', '24B983149'],
                ['Tshibangu', 'Chantal', 'Léonie', 'F', '2009-09-19', 'Kolwezi', '24B983150'],
                ['Mukendi', 'Pierre', 'Célestin', 'M', '2009-12-01', 'Kolwezi', '24B983151'],
            ],
            'COMMERCIALE_3' => [
                ['Ngoy', 'Alice', 'Josée', 'F', '2008-03-05', 'Lubumbashi', '24B983152'],
                ['Kanku', 'Michel', 'Robert', 'M', '2008-07-14', 'Kolwezi', '24B983153'],
                ['Banza', 'Dorothée', 'Loraine', 'F', '2008-05-27', 'Likasi', '24B983154'],
                ['Mulumba', 'Jacques', 'Olivier', 'M', '2008-10-09', 'Kolwezi', '24B983155'],
                ['Kabila', 'Estelle', 'Prisca', 'F', '2008-01-16', 'Kolwezi', '24B983156'],
            ],
            'COMMERCIALE_4' => [
                ['Lubwele', 'Serge', 'Antoine', 'M', '2007-06-02', 'Lubumbashi', '24B983157'],
                ['Nzuzi', 'Béatrice', 'Sylvie', 'F', '2007-08-21', 'Kolwezi', '24B983158'],
                ['Kahozi', 'Didier', 'Fernand', 'M', '2007-04-11', 'Likasi', '24B983159'],
                ['Mwamba', 'Georgette', 'Nadine', 'F', '2007-11-24', 'Kolwezi', '24B983160'],
                ['Kalonji', 'Léon', 'Martin', 'M', '2007-02-28', 'Kolwezi', '24B983161'],
            ],
            'CTEB_7' => [
                ['Katumbi', 'Grâce', 'Merveille', 'F', '2012-04-18', 'Kolwezi', '25EBA7001'],
            ],
            'CTEB_8' => [
                ['Mwenda', 'David', 'Junior', 'M', '2011-09-07', 'Likasi', '25EBA8001'],
            ],
        ];

        foreach ($classes as $classeDefinition) {
            $niveauId = DB::table('ref_niveaux')->where('code', $classeDefinition['niveau'])->value('id');
            $templateId = DB::table('system_templates_bulletin')->where('code', $classeDefinition['template'])->value('id');

            DB::table('classes')->updateOrInsert(
                ['tenant_id' => $tenantId, 'etablissement_id' => $etablissementId, 'annee_scolaire_id' => $anneeScolaireId, 'nom' => $classeDefinition['nom']],
                [
                    'cycle_id' => $cycleIds[$classeDefinition['cycle']],
                    'degre_id' => $degreIds[$classeDefinition['degre']],
                    'niveau_id' => $niveauId,
                    'option_id' => $classeDefinition['option'] === null
                        ? null
                        : $optionIds[$classeDefinition['option']],
                    'system_template_id' => $templateId,
                    'parallele' => 'A',
                    'code' => $classeDefinition['code'],
                    'active' => true,
                    'public_id' => (string) Str::ulid(),
                    'updated_at' => now(),
                ]
            );

            $classeId = DB::table('classes')->where('tenant_id', $tenantId)->where('nom', $classeDefinition['nom'])->value('id');

            DB::table('classes')
                ->where('id', $classeId)
                ->whereNull('public_id')
                ->update(['public_id' => (string) Str::ulid()]);

            $eleves = $elevesParTemplate[$classeDefinition['template']] ?? [];

            foreach ($eleves as [$nom, $postnom, $prenom, $sexe, $dateNaissance, $lieuNaissance, $numPermanent]) {
                $eleveId = DB::table('eleves')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'nom' => $nom, 'postnom' => $postnom, 'prenom' => $prenom],
                    [
                        'etablissement_id' => $etablissementId,
                        'sexe' => $sexe,
                        'date_naissance' => $dateNaissance,
                        'lieu_naissance' => $lieuNaissance,
                        'num_permanent' => $numPermanent,
                        'actif' => true,
                        'public_id' => (string) Str::ulid(),
                        'updated_at' => now(),
                    ]
                );

                $eleveId = DB::table('eleves')->where('tenant_id', $tenantId)->where('nom', $nom)->where('postnom', $postnom)->where('prenom', $prenom)->value('id');

                DB::table('eleves')
                    ->where('id', $eleveId)
                    ->whereNull('public_id')
                    ->update(['public_id' => (string) Str::ulid()]);

                DB::table('inscriptions')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'eleve_id' => $eleveId, 'classe_id' => $classeId, 'annee_scolaire_id' => $anneeScolaireId],
                    [
                        'date_inscription' => now(),
                        'statut' => 'ACTIF',
                        'public_id' => (string) Str::ulid(),
                        'updated_at' => now(),
                    ]
                );

                $inscriptionId = DB::table('inscriptions')
                    ->where('tenant_id', $tenantId)
                    ->where('eleve_id', $eleveId)
                    ->where('classe_id', $classeId)
                    ->value('id');

                DB::table('inscriptions')
                    ->where('id', $inscriptionId)
                    ->whereNull('public_id')
                    ->update(['public_id' => (string) Str::ulid()]);
            }
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
                'telephone_normalise' => '+243000000003',
                'nom' => 'Prof',
                'postnom' => 'Test',
                'prenom' => 'Enseignant',
                'email' => 'prof@test.com',
                'mot_de_passe' => Hash::make($teacherPassword),
                'actif' => true,
                'public_id' => (string) Str::ulid(),
                'updated_at' => now(),
            ]
        );

        $enseignantId = DB::table('utilisateurs')->where('tenant_id', $tenantId)->where('telephone', '+243000000003')->value('id');

        DB::table('utilisateurs')
            ->where('id', $enseignantId)
            ->whereNull('public_id')
            ->update(['public_id' => (string) Str::ulid()]);

        DB::table('utilisateur_roles')->updateOrInsert(
            ['utilisateur_id' => $enseignantId, 'role_id' => $enseignantRoleId],
            []
        );
    }

    private function configuredPassword(string $configKey, string $environmentVariable): string
    {
        $password = (string) config("seeding.{$configKey}");

        if (trim($password) === '') {
            throw new LogicException("{$environmentVariable} doit être renseigné avant le seeding.");
        }

        if (app()->isProduction() && strlen($password) < 12) {
            throw new LogicException("{$environmentVariable} doit contenir au moins 12 caractères en production.");
        }

        return $password;
    }
}
