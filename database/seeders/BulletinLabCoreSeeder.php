<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabCoreSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('plans')->upsert([
            ['code' => 'STARTER', 'nom' => 'Starter', 'prix_mensuel' => 10, 'prix_annuel' => 100, 'limite_eleves' => 300, 'limite_utilisateurs' => 30, 'limite_etablissements' => 1],
            ['code' => 'STANDARD', 'nom' => 'Standard', 'prix_mensuel' => 25, 'prix_annuel' => 250, 'limite_eleves' => 1000, 'limite_utilisateurs' => 80, 'limite_etablissements' => 2],
            ['code' => 'PREMIUM', 'nom' => 'Premium', 'prix_mensuel' => 50, 'prix_annuel' => 500, 'limite_eleves' => null, 'limite_utilisateurs' => null, 'limite_etablissements' => null],
        ], ['code']);

        $permissions = [
            'school.manage' => 'Gérer école',
            'teachers.manage' => 'Gérer enseignants',
            'classes.manage' => 'Gérer classes',
            'students.manage' => 'Gérer élèves',
            'assignments.manage' => 'Gérer attributions',
            'notes.enter' => 'Encoder notes',
            'notes.monitor' => 'Suivre encodage',
            'bulletins.generate' => 'Générer bulletins',
            'proclamations.generate' => 'Générer proclamations',
            'attendance.manage' => 'Gérer présences',
            'system.templates.manage' => 'Gérer templates système',
        ];

        foreach ($permissions as $code => $nom) {
            $permission = Permission::findOrCreate($code, 'web');
            $permission->forceFill([
                'code' => $code,
                'nom' => $nom,
            ])->save();
        }

        $roles = [
            ['code' => 'SUPER_ADMIN', 'nom' => 'Super Admin', 'system_role' => true],
            ['code' => 'ADMIN_ECOLE', 'nom' => 'Administrateur école', 'system_role' => true],
            ['code' => 'ADMIN_TENANT', 'nom' => 'Administrateur tenant', 'system_role' => true],
            ['code' => 'GESTIONNAIRE_ECOLE', 'nom' => 'Gestionnaire école', 'system_role' => true],
            ['code' => 'DIRECTEUR_ETUDES', 'nom' => 'Directeur des études', 'system_role' => true],
            ['code' => 'TITULAIRE', 'nom' => 'Titulaire', 'system_role' => true],
            ['code' => 'ENSEIGNANT', 'nom' => 'Enseignant', 'system_role' => true],
            ['code' => 'SECRETARIAT', 'nom' => 'Secrétariat', 'system_role' => true],
        ];

        foreach ($roles as $role) {
            $model = Role::findOrCreate($role['code'], 'web');
            $model->forceFill([
                'tenant_id' => null,
                'code' => $role['code'],
                'nom' => $role['nom'],
                'system_role' => $role['system_role'],
            ])->save();
        }

        $rolePermissions = [
            'SUPER_ADMIN' => ['school.manage', 'teachers.manage', 'classes.manage', 'students.manage', 'assignments.manage', 'notes.enter', 'notes.monitor', 'bulletins.generate', 'proclamations.generate', 'attendance.manage', 'system.templates.manage'],
            'ADMIN_TENANT' => ['school.manage', 'teachers.manage', 'classes.manage', 'students.manage', 'assignments.manage', 'notes.monitor', 'bulletins.generate', 'proclamations.generate', 'attendance.manage'],
            'ADMIN_ECOLE' => ['school.manage', 'teachers.manage', 'classes.manage', 'students.manage', 'assignments.manage', 'notes.monitor', 'bulletins.generate', 'proclamations.generate', 'attendance.manage'],
            'GESTIONNAIRE_ECOLE' => ['teachers.manage', 'classes.manage', 'students.manage', 'assignments.manage', 'notes.monitor', 'bulletins.generate', 'proclamations.generate', 'attendance.manage'],
            'DIRECTEUR_ETUDES' => ['classes.manage', 'students.manage', 'assignments.manage', 'notes.monitor', 'bulletins.generate', 'proclamations.generate'],
            'TITULAIRE' => ['notes.enter', 'attendance.manage'],
            'ENSEIGNANT' => ['notes.enter'],
            'SECRETARIAT' => ['students.manage', 'attendance.manage'],
        ];

        foreach ($rolePermissions as $code => $codes) {
            $role = Role::query()->whereNull('tenant_id')->where('name', $code)->first();

            if ($role === null) {
                continue;
            }

            $role->syncPermissions($codes);
        }
    }
}
