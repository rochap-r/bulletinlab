<?php

use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Models\Etablissement;
use App\Models\Role;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('phase 1 validation: complete bulletin workflow', function () {
    $this->seed(\Database\Seeders\BulletinLabAcademicSeeder::class);
    $this->seed(\Database\Seeders\BulletinLabCalendarSeeder::class);
    $this->seed(\Database\Seeders\BulletinLabCoreSeeder::class);

    $tenant = Tenant::create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École Test',
        'slug' => 'ecole-test',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $etablissement = Etablissement::create([
        'tenant_id' => $tenant->id,
        'nom' => 'Complexe Scolaire Test',
        'code_ministere' => 'CST',
        'province' => 'Kinshasa',
        'ville' => 'Kinshasa',
    ]);

    $role = Role::create([
        'tenant_id' => $tenant->id,
        'code' => 'ADMIN',
        'nom' => 'Administrateur',
    ]);

    $user = Utilisateur::create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement->id,
        'nom' => 'Admin',
        'postnom' => 'Test',
        'prenom' => 'User',
        'telephone' => '+243000000002',
        'email' => 'admin@test.com',
        'mot_de_passe' => Hash::make('password123'),
        'actif' => true,
    ]);

    $user->roles()->attach($role->id);

    Sanctum::actingAs($user);
    app()->instance('tenant', $tenant);

    $calendrierId = \DB::table('calendriers_scolaires')->where('code', 'RDC_2026_2027')->value('id');

    $anneeScolaire = AnneeScolaire::create([
        'tenant_id' => $tenant->id,
        'calendrier_scolaire_id' => $calendrierId,
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-07-31',
    ]);

    expect($anneeScolaire)->not->toBeNull();

    $response = $this->postJson('/api/v1/annees-scolaires/' . $anneeScolaire->id . '/open');
    $response->assertStatus(200);

    $cycleId = \DB::table('ref_cycles')->where('code', 'HUMANITES')->value('id');
    $niveauId = \DB::table('ref_niveaux')->where('code', 'HUM_1')->value('id');
    $optionId = \DB::table('ref_options')->where('code', 'COMMERCIALE_GESTION')->value('id');

    $layoutId = \DB::table('system_layouts_bulletin')->insertGetId([
        'code' => 'TEST_LAYOUT',
        'nom' => 'Layout Test',
        'description' => 'Test layout',
        'actif' => true,
    ]);

    $templateId = \DB::table('system_templates_bulletin')->insertGetId([
        'code' => 'TEST_TEMPLATE',
        'nom' => 'Template Test',
        'cycle_id' => $cycleId,
        'layout_id' => $layoutId,
        'type_decoupage' => 'TRIMESTRIEL',
        'actif' => true,
    ]);

    $classe = Classe::create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement->id,
        'annee_scolaire_id' => $anneeScolaire->id,
        'cycle_id' => $cycleId,
        'niveau_id' => $niveauId,
        'option_id' => $optionId,
        'system_template_id' => $templateId,
        'nom' => '1ère Commerciale A',
        'parallele' => 'A',
        'code' => '1CA',
    ]);

    expect($classe)->not->toBeNull();

    $eleve = Eleve::create([
        'tenant_id' => $tenant->id,
        'etablissement_id' => $etablissement->id,
        'nom' => 'Kabongo',
        'postnom' => 'Jean',
        'prenom' => 'Pierre',
        'sexe' => 'M',
        'date_naissance' => '2010-05-15',
    ]);

    expect($eleve)->not->toBeNull();

    $inscription = Inscription::create([
        'tenant_id' => $tenant->id,
        'eleve_id' => $eleve->id,
        'classe_id' => $classe->id,
        'annee_scolaire_id' => $anneeScolaire->id,
        'date_inscription' => now(),
        'statut' => 'ACTIF',
    ]);

    expect($inscription)->not->toBeNull();

    $response = $this->getJson('/api/v1/classes/' . $classe->id);
    $response->assertStatus(200);
    $response->assertJsonFragment(['nom' => '1ère Commerciale A']);

    $response = $this->getJson('/api/v1/eleves');
    $response->assertStatus(200);
    $elevesData = $response->json();
    expect($elevesData)->toHaveCount(1);

    $response = $this->getJson('/api/v1/inscriptions');
    $response->assertStatus(200);
    $inscriptionsData = $response->json();
    expect($inscriptionsData)->toHaveCount(1);
});
