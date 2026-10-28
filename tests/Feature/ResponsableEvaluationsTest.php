<?php

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Enums\NoteOrigine;
use App\Enums\StatutEvaluation;
use App\Enums\StatutLienResponsable;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\LienResponsableEleve;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\RefCycle;
use App\Models\RefNiveau;
use App\Models\Responsable;
use App\Models\ResultatEvaluation;
use App\Models\SystemLayoutBulletin;
use App\Models\SystemTemplateBulletin;
use App\Models\Tenant;
use App\Models\TypeEvaluation;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function buildClassContext(): array
{
    $tenant = Tenant::query()->create([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'École Parent',
        'slug' => 'ecole-parent-'.Str::lower(Str::random(6)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $school = Etablissement::query()->create([
        'tenant_id' => $tenant->getKey(),
        'nom' => 'École Parent',
        'type_etablissement' => 'SECONDAIRE',
        'actif' => true,
    ]);

    $cycle = RefCycle::query()->create(['code' => 'SEC-'.Str::random(4), 'nom' => 'Secondaire']);
    $niveau = RefNiveau::query()->create([
        'cycle_id' => $cycle->getKey(),
        'code' => '6E-'.Str::random(4),
        'nom' => 'Sixième',
        'numero' => 6,
    ]);

    $layout = SystemLayoutBulletin::query()->create([
        'code' => 'LAY-'.Str::random(4),
        'nom' => 'Layout',
    ]);

    $template = SystemTemplateBulletin::query()->create([
        'code' => 'TPL-'.Str::random(4),
        'nom' => 'Template',
        'cycle_id' => $cycle->getKey(),
        'niveau_min_id' => $niveau->getKey(),
        'niveau_max_id' => $niveau->getKey(),
        'layout_id' => $layout->getKey(),
        'type_decoupage' => 'TRIMESTRIEL',
        'statut' => 'VALIDE',
        'actif' => true,
    ]);

    $annee = AnneeScolaire::query()->create([
        'libelle' => '2026-2027',
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-06-30',
        'active' => true,
        'cloturee' => false,
    ]);

    $classe = Classe::query()->create([
        'tenant_id' => $tenant->getKey(),
        'etablissement_id' => $school->getKey(),
        'annee_scolaire_id' => $annee->getKey(),
        'cycle_id' => $cycle->getKey(),
        'niveau_id' => $niveau->getKey(),
        'system_template_id' => $template->getKey(),
        'nom' => '6ème A',
        'active' => true,
    ]);

    $modeleBulletin = ModeleBulletin::query()->create([
        'tenant_id' => $tenant->getKey(),
        'classe_id' => $classe->getKey(),
        'annee_scolaire_id' => $annee->getKey(),
        'system_template_id' => $template->getKey(),
        'layout_id' => $layout->getKey(),
        'nom' => 'Bulletin 6ème',
        'type_decoupage' => 'TRIMESTRIEL',
        'actif' => true,
    ]);

    $node = ModeleNode::query()->create([
        'tenant_id' => $tenant->getKey(),
        'modele_bulletin_id' => $modeleBulletin->getKey(),
        'type_node' => 'MATIERE',
        'matiere_id' => null,
        'libelle' => 'Mathématiques',
        'ordre' => 1,
    ]);

    $periode = ModelePeriode::query()->create([
        'tenant_id' => $tenant->getKey(),
        'modele_bulletin_id' => $modeleBulletin->getKey(),
        'code' => 'T1',
        'nom' => 'Trimestre 1',
        'type_periode' => 'TRIMESTRE',
        'ordre' => 1,
        'saisissable' => true,
    ]);
    $maximum = ModeleMaximum::query()->create([
        'tenant_id' => $tenant->getKey(),
        'modele_bulletin_id' => $modeleBulletin->getKey(),
        'node_id' => $node->getKey(),
        'periode_id' => $periode->getKey(),
        'maxima' => 40.00,
        'coefficient' => 1.00,
    ]);

    $typeEvaluation = TypeEvaluation::query()->create([
        'code' => 'INTERROGATION-'.Str::random(4),
        'nom' => 'Interrogation',
        'actif' => true,
    ]);

    $enseignant = Utilisateur::query()->create([
        'tenant_id' => $tenant->getKey(),
        'etablissement_id' => $school->getKey(),
        'nom' => 'Prof',
        'email' => 'prof.'.Str::random(6).'@ecole.test',
        'telephone' => '+2438000000'.random_int(1, 9),
        'mot_de_passe' => bcrypt('secret'),
        'actif' => true,
    ]);

    $attribution = AttributionCours::query()->create([
        'tenant_id' => $tenant->getKey(),
        'classe_id' => $classe->getKey(),
        'modele_bulletin_id' => $modeleBulletin->getKey(),
        'node_id' => $node->getKey(),
        'enseignant_id' => $enseignant->getKey(),
        'actif' => true,
    ]);

    return compact(
        'tenant', 'school', 'classe', 'node', 'periode', 'typeEvaluation',
        'enseignant', 'attribution', 'modeleBulletin',
        'maximum',
    );
}

test('un parent consulte le detail des evaluations de son enfant pour une periode', function (): void {
    $ctx = buildClassContext();

    $eleve = Eleve::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'etablissement_id' => $ctx['school']->getKey(),
        'nom' => 'Eleve',
        'prenom' => 'Un',
        'actif' => true,
    ]);

    $inscription = Inscription::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'eleve_id' => $eleve->getKey(),
        'classe_id' => $ctx['classe']->getKey(),
        'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
        'statut' => 'ACTIF',
    ]);
    $bulletin = Bulletin::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'inscription_id' => $inscription->getKey(),
        'classe_id' => $ctx['classe']->getKey(),
        'modele_bulletin_id' => $ctx['modeleBulletin']->getKey(),
    ]);

    $responsable = Responsable::query()->create([
        'nom' => 'Parent',
        'prenom' => 'Test',
        'telephone' => '+2438123457'.random_int(1, 9),
        'mot_de_passe' => bcrypt('123456'),
        'doit_changer_mot_de_passe' => true,
        'actif' => true,
    ]);

    $lien = LienResponsableEleve::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'responsable_id' => $responsable->getKey(),
        'eleve_id' => $eleve->getKey(),
        'type_lien' => 'PERE',
        'statut' => StatutLienResponsable::Verifie->value,
        'verifie_at' => now(),
        'peut_consulter_notes' => true,
    ]);

    $evaluation = Evaluation::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'etablissement_id' => $ctx['school']->getKey(),
        'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
        'classe_id' => $ctx['classe']->getKey(),
        'attribution_cours_id' => $ctx['attribution']->getKey(),
        'node_id' => $ctx['node']->getKey(),
        'periode_id' => $ctx['periode']->getKey(),
        'type_evaluation_id' => $ctx['typeEvaluation']->getKey(),
        'libelle' => 'Interrogation n°1',
        'maxima' => 10.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-15',
        'statut' => StatutEvaluation::Publiee->value,
        'cree_par' => $ctx['enseignant']->getKey(),
        'lock_version' => 1,
    ]);

    ResultatEvaluation::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'evaluation_id' => $evaluation->getKey(),
        'inscription_id' => $inscription->getKey(),
        'eleve_id' => $eleve->getKey(),
        'statut_resultat' => NoteResultStatus::Saisie->value,
        'valeur' => 8.00,
        'commentaire_parent' => 'Bien joué',
        'commentaire_interne' => 'Ne doit jamais être visible par le parent.',
        'saisi_par' => $ctx['enseignant']->getKey(),
        'date_saisie' => now(),
        'lock_version' => 1,
    ]);
    Note::query()->forceCreate([
        'tenant_id' => $ctx['tenant']->getKey(),
        'bulletin_id' => $bulletin->getKey(),
        'node_id' => $ctx['node']->getKey(),
        'periode_id' => $ctx['periode']->getKey(),
        'statut_resultat' => NoteResultStatus::Saisie,
        'valeur' => 32.00,
        'origine' => NoteOrigine::Evaluations,
        'calcul_version' => '1.0.0',
        'calcul_hash' => str_repeat('a', 64),
        'calculee_at' => now(),
    ]);

    // Connexion parent puis changement obligatoire du mot de passe initial.
    $login = $this->postJson('/api/v1/responsables/auth/login', [
        'telephone' => $responsable->telephone,
        'mot_de_passe' => '123456',
    ])->assertOk();

    $token = $login->json('data.access_token');
    $token = $this->withToken($token)
        ->postJson('/api/v1/responsables/me/password-initial', [
            'mot_de_passe' => 'secret-parent',
            'mot_de_passe_confirmation' => 'secret-parent',
        ])
        ->assertOk()
        ->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/v1/responsables/enfants/'.$lien->public_id.'/evaluations')
        ->assertOk()
        ->assertJsonPath('enfant.nom', 'Eleve')
        ->assertJsonPath('classe.nom', '6ème A')
        ->assertJsonCount(1, 'evaluations')
        ->assertJsonPath('enfant.public_id', $eleve->public_id)
        ->assertJsonPath('classe.public_id', $ctx['classe']->public_id)
        ->assertJsonPath('inscription_public_id', $inscription->public_id)
        ->assertJsonPath('evaluations.0.evaluation.public_id', $evaluation->public_id)
        ->assertJsonPath('evaluations.0.evaluation.libelle', 'Interrogation n°1')
        ->assertJsonPath('evaluations.0.resultat.valeur', '8.00')
        ->assertJsonPath('evaluations.0.resultat.statut_resultat', 'SAISIE')
        ->assertJsonPath('evaluations.0.periode_calcul.total_obtenu', 32)
        ->assertJsonPath('evaluations.0.periode_calcul.total_maxima', 40)
        ->assertJsonPath('evaluations.0.periode_calcul.pourcentage', 80)
        ->assertJsonPath('evaluations.0.periode_calcul.total_composantes_obtenu', 8)
        ->assertJsonPath('evaluations.0.periode_calcul.total_composantes_maxima', 10)
        ->assertJsonPath('notes_par_periode.0.origine', 'EVALUATIONS')
        ->assertJsonPath('evaluations.0.periode_calcul.composantes.0.evaluation_public_id', $evaluation->public_id)
        ->assertJsonMissingPath('enfant.id')
        ->assertJsonMissingPath('classe.id')
        ->assertJsonMissingPath('inscription_id')
        ->assertJsonMissingPath('evaluations.0.evaluation.id')
        ->assertJsonMissingPath('evaluations.0.resultat.id')
        ->assertJsonMissingPath('evaluations.0.resultat.eleve_id')
        ->assertJsonMissingPath('evaluations.0.resultat.inscription_id')
        ->assertJsonMissingPath('evaluations.0.resultat.commentaire_interne')
        ->assertJsonMissingPath('evaluations.0.periode_calcul.composantes.0.evaluation_id');
});

test('un parent consulte une note de periode saisie en direct par lenseignant', function (): void {
    $ctx = buildClassContext();

    $eleve = Eleve::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'etablissement_id' => $ctx['school']->getKey(),
        'nom' => 'Eleve',
        'prenom' => 'Direct',
        'actif' => true,
    ]);

    $inscription = Inscription::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'eleve_id' => $eleve->getKey(),
        'classe_id' => $ctx['classe']->getKey(),
        'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
        'statut' => 'ACTIF',
    ]);
    $bulletin = Bulletin::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'inscription_id' => $inscription->getKey(),
        'classe_id' => $ctx['classe']->getKey(),
        'modele_bulletin_id' => $ctx['modeleBulletin']->getKey(),
    ]);

    $responsable = Responsable::query()->create([
        'nom' => 'Parent',
        'prenom' => 'Direct',
        'telephone' => '+2438123457'.random_int(1, 9),
        'mot_de_passe' => bcrypt('123456'),
        'doit_changer_mot_de_passe' => true,
        'actif' => true,
    ]);

    $lien = LienResponsableEleve::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'responsable_id' => $responsable->getKey(),
        'eleve_id' => $eleve->getKey(),
        'type_lien' => 'PERE',
        'statut' => StatutLienResponsable::Verifie->value,
        'verifie_at' => now(),
        'peut_consulter_notes' => true,
    ]);

    Note::query()->forceCreate([
        'tenant_id' => $ctx['tenant']->getKey(),
        'bulletin_id' => $bulletin->getKey(),
        'node_id' => $ctx['node']->getKey(),
        'periode_id' => $ctx['periode']->getKey(),
        'statut_resultat' => NoteResultStatus::Saisie->value,
        'valeur' => 19.00,
        'origine' => NoteOrigine::Directe,
        'commentaire' => 'Note directe de période',
    ]);

    $login = $this->postJson('/api/v1/responsables/auth/login', [
        'telephone' => $responsable->telephone,
        'mot_de_passe' => '123456',
    ])->assertOk();

    $token = $login->json('data.access_token');
    $token = $this->withToken($token)
        ->postJson('/api/v1/responsables/me/password-initial', [
            'mot_de_passe' => 'secret-parent',
            'mot_de_passe_confirmation' => 'secret-parent',
        ])
        ->assertOk()
        ->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/v1/responsables/enfants/'.$lien->public_id.'/evaluations')
        ->assertOk()
        ->assertJsonPath('enfant.nom', 'Eleve')
        ->assertJsonPath('classe.nom', '6ème A')
        ->assertJsonCount(0, 'evaluations')
        ->assertJsonCount(1, 'notes_par_periode')
        ->assertJsonPath('notes_par_periode.0.origine', 'DIRECTE')
        ->assertJsonPath('notes_par_periode.0.valeur', '19.00')
        ->assertJsonPath('notes_par_periode.0.maxima', 40)
        ->assertJsonPath('notes_par_periode.0.pourcentage', 47.5)
        ->assertJsonCount(0, 'notes_par_periode.0.evaluations')
        ->assertJsonMissingPath('enfant.id')
        ->assertJsonMissingPath('classe.id');
});

test('un autre responsable ne voit pas les evaluations de lenfant dun premier responsable', function (): void {
    $ctxA = buildClassContext();

    $eleveA = Eleve::query()->create([
        'tenant_id' => $ctxA['tenant']->getKey(),
        'etablissement_id' => $ctxA['school']->getKey(),
        'nom' => 'Eleve A',
        'actif' => true,
    ]);
    $inscriptionA = Inscription::query()->create([
        'tenant_id' => $ctxA['tenant']->getKey(),
        'eleve_id' => $eleveA->getKey(),
        'classe_id' => $ctxA['classe']->getKey(),
        'annee_scolaire_id' => $ctxA['classe']->annee_scolaire_id,
        'statut' => 'ACTIF',
    ]);
    $responsableA = Responsable::query()->create([
        'nom' => 'Parent A',
        'telephone' => '+243812345711',
        'mot_de_passe' => bcrypt('123456'),
        'doit_changer_mot_de_passe' => true,
        'actif' => true,
    ]);
    $lienA = LienResponsableEleve::query()->create([
        'tenant_id' => $ctxA['tenant']->getKey(),
        'responsable_id' => $responsableA->getKey(),
        'eleve_id' => $eleveA->getKey(),
        'statut' => StatutLienResponsable::Verifie->value,
        'verifie_at' => now(),
        'peut_consulter_notes' => true,
    ]);
    $evaluationA = Evaluation::query()->create([
        'tenant_id' => $ctxA['tenant']->getKey(),
        'etablissement_id' => $ctxA['school']->getKey(),
        'annee_scolaire_id' => $ctxA['classe']->annee_scolaire_id,
        'classe_id' => $ctxA['classe']->getKey(),
        'attribution_cours_id' => $ctxA['attribution']->getKey(),
        'node_id' => $ctxA['node']->getKey(),
        'periode_id' => $ctxA['periode']->getKey(),
        'type_evaluation_id' => $ctxA['typeEvaluation']->getKey(),
        'libelle' => 'Interrogation secrete',
        'maxima' => 10.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-15',
        'statut' => StatutEvaluation::Publiee->value,
        'cree_par' => $ctxA['enseignant']->getKey(),
        'lock_version' => 1,
    ]);
    ResultatEvaluation::query()->create([
        'tenant_id' => $ctxA['tenant']->getKey(),
        'evaluation_id' => $evaluationA->getKey(),
        'inscription_id' => $inscriptionA->getKey(),
        'eleve_id' => $eleveA->getKey(),
        'statut_resultat' => NoteResultStatus::Saisie->value,
        'valeur' => 8.00,
        'lock_version' => 1,
    ]);

    // Un responsable B (autre telephone) tente d'acceder au lien publique de l'enfant de A.
    $responsableB = Responsable::query()->create([
        'nom' => 'Parent B',
        'telephone' => '+243812345722',
        'mot_de_passe' => bcrypt('123456'),
        'doit_changer_mot_de_passe' => true,
        'actif' => true,
    ]);

    $login = $this->postJson('/api/v1/responsables/auth/login', [
        'telephone' => $responsableB->telephone,
        'mot_de_passe' => '123456',
    ])->assertOk();

    $token = $login->json('data.access_token');
    $token = $this->withToken($token)
        ->postJson('/api/v1/responsables/me/password-initial', [
            'mot_de_passe' => 'secret-parent',
            'mot_de_passe_confirmation' => 'secret-parent',
        ])
        ->assertOk()
        ->json('data.access_token');

    // B n'a aucun lien vers l'enfant de A : le lien public de A est introuvable pour B.
    $this->withToken($token)
        ->getJson('/api/v1/responsables/enfants/'.$lienA->public_id.'/evaluations')
        ->assertNotFound();
});
