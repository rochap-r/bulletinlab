<?php

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Enums\NoteOrigine;
use App\Enums\StatutEvaluation;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
use App\Models\AuditLog;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\RefCycle;
use App\Models\RefNiveau;
use App\Models\ResultatEvaluation;
use App\Models\SystemLayoutBulletin;
use App\Models\SystemTemplateBulletin;
use App\Models\Tenant;
use App\Models\TypeEvaluation;
use App\Models\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function uniqueCode(string $base): string
{
    return $base.'-'.Str::lower(Str::random(4));
}

function evaluationContext(array $overrides = []): array
{
    $tenant = Tenant::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'nom_espace' => 'École Test',
        'slug' => 'ecole-test-'.Str::lower(Str::random(6)),
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ], $overrides['tenant'] ?? []));

    $school = Etablissement::query()->create([
        'tenant_id' => $tenant->getKey(),
        'nom' => 'École Secondaire',
        'type_etablissement' => 'SECONDAIRE',
        'actif' => true,
    ]);

    $cycle = RefCycle::query()->create(['code' => uniqueCode('SEC'), 'nom' => 'Secondaire']);
    $niveau = RefNiveau::query()->create([
        'cycle_id' => $cycle->getKey(),
        'degre_id' => null,
        'code' => uniqueCode('6E'),
        'nom' => 'Sixième',
        'numero' => 6,
    ]);

    $layout = SystemLayoutBulletin::query()->create([
        'code' => uniqueCode('LAYOUT'),
        'nom' => 'Layout par défaut',
    ]);

    $template = SystemTemplateBulletin::query()->create([
        'code' => uniqueCode('TEMPLATE'),
        'nom' => 'Template par défaut',
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

    $typeEvaluation = TypeEvaluation::query()->create([
        'code' => uniqueCode('INTERROGATION'),
        'nom' => 'Interrogation',
        'actif' => true,
    ]);

    $teacher = Utilisateur::query()->create([
        'tenant_id' => $tenant->getKey(),
        'etablissement_id' => $school->getKey(),
        'nom' => 'Prof',
        'prenom' => 'Maths',
        'email' => 'prof.'.Str::random(6).'@ecole.test',
        'telephone' => '+24390000000'.random_int(1, 9),
        'mot_de_passe' => bcrypt('secret-password'),
        'actif' => true,
    ]);

    $attribution = AttributionCours::query()->create([
        'tenant_id' => $tenant->getKey(),
        'classe_id' => $classe->getKey(),
        'modele_bulletin_id' => $modeleBulletin->getKey(),
        'node_id' => $node->getKey(),
        'enseignant_id' => $teacher->getKey(),
        'actif' => true,
    ]);

    $eleve = Eleve::query()->create([
        'tenant_id' => $tenant->getKey(),
        'etablissement_id' => $school->getKey(),
        'nom' => 'Eleve',
        'prenom' => 'Un',
        'actif' => true,
    ]);

    $inscription = Inscription::query()->create([
        'tenant_id' => $tenant->getKey(),
        'eleve_id' => $eleve->getKey(),
        'classe_id' => $classe->getKey(),
        'annee_scolaire_id' => $annee->getKey(),
        'statut' => 'ACTIF',
    ]);
    $maximum = ModeleMaximum::query()->create([
        'tenant_id' => $tenant->getKey(),
        'modele_bulletin_id' => $modeleBulletin->getKey(),
        'node_id' => $node->getKey(),
        'periode_id' => $periode->getKey(),
        'maxima' => 40.00,
        'coefficient' => 1.00,
    ]);
    $bulletin = Bulletin::query()->create([
        'tenant_id' => $tenant->getKey(),
        'inscription_id' => $inscription->getKey(),
        'classe_id' => $classe->getKey(),
        'modele_bulletin_id' => $modeleBulletin->getKey(),
    ]);

    return [
        'tenant' => $tenant,
        'school' => $school,
        'classe' => $classe,
        'node' => $node,
        'periode' => $periode,
        'typeEvaluation' => $typeEvaluation,
        'teacher' => $teacher,
        'attribution' => $attribution,
        'eleve' => $eleve,
        'inscription' => $inscription,
        'maximum' => $maximum,
        'bulletin' => $bulletin,
    ];
}

function teacherToken(Utilisateur $teacher, Tenant $tenant): string
{
    return $teacher->createToken(
        'evaluation-test',
        ["tenant:{$tenant->getKey()}"],
    )->plainTextToken;
}

test('un enseignant peut creer une evaluation pour une matiere de sa classe', function (): void {
    $ctx = evaluationContext();
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/evaluations', [
            'classe_id' => $ctx['classe']->getKey(),
            'periode_id' => $ctx['periode']->getKey(),
            'node_id' => $ctx['node']->getKey(),
            'type_evaluation_id' => $ctx['typeEvaluation']->getKey(),
            'attribution_cours_id' => $ctx['attribution']->getKey(),
            'libelle' => 'Interrogation n°1',
            'maxima' => 10.00,
            'coefficient' => 1.00,
            'date_evaluation' => '2026-10-15',
            'statut' => StatutEvaluation::Publiee->value,
        ]);

    $response->assertStatus(201);

    $evaluation = $response->json('data');
    expect($evaluation['libelle'])->toBe('Interrogation n°1')
        ->and($evaluation['maxima'])->toBe('10.00')
        ->and($evaluation['coefficient'])->toBe('1.00')
        ->and($evaluation['statut'])->toBe('PUBLIEE')
        ->and($evaluation['type_evaluation']['code'])->toContain('INTERROGATION')
        ->and($evaluation['periode']['code'])->toBe('T1')
        ->and($evaluation['node']['libelle'])->toBe('Mathématiques');

    $audit = AuditLog::query()
        ->where('action', 'EVALUATION_CREEE')
        ->where('ligne_cible_id', $evaluation['id'])
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->utilisateur_id)->toBe($ctx['teacher']->getKey())
        ->and($audit->tenant_id)->toBe($ctx['tenant']->getKey())
        ->and($audit->nouvelles_valeurs['statut'])->toBe('PUBLIEE');
});

test('un enseignant liste les evaluations de sa classe', function (): void {
    $ctx = evaluationContext();
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    Evaluation::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'etablissement_id' => $ctx['school']->getKey(),
        'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
        'classe_id' => $ctx['classe']->getKey(),
        'node_id' => $ctx['node']->getKey(),
        'periode_id' => $ctx['periode']->getKey(),
        'type_evaluation_id' => $ctx['typeEvaluation']->getKey(),
        'libelle' => 'Interrogation n°1',
        'maxima' => 10.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-15',
        'statut' => 'PUBLIEE',
        'attribution_cours_id' => $ctx['attribution']->getKey(),
        'cree_par' => $ctx['teacher']->getKey(),
        'lock_version' => 1,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/evaluations', [
            'classe_id' => $ctx['classe']->getKey(),
            'periode_id' => $ctx['periode']->getKey(),
        ]);

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['data'])->toHaveCount(1)
        ->and($data['data'][0]['libelle'])->toBe('Interrogation n°1');
});

test('le filtre par classe exclut les evaluations des autres classes', function (): void {
    $ctx = evaluationContext();
    $other = evaluationContext(['tenant' => ['slug' => 'ecole-autre-classe']]);
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    $makeEvaluation = function (array $context, string $libelle): void {
        Evaluation::query()->create([
            'tenant_id' => $context['tenant']->getKey(),
            'etablissement_id' => $context['school']->getKey(),
            'annee_scolaire_id' => $context['classe']->annee_scolaire_id,
            'classe_id' => $context['classe']->getKey(),
            'node_id' => $context['node']->getKey(),
            'periode_id' => $context['periode']->getKey(),
            'type_evaluation_id' => $context['typeEvaluation']->getKey(),
            'libelle' => $libelle,
            'maxima' => 10.00,
            'coefficient' => 1.00,
            'date_evaluation' => '2026-10-15',
            'statut' => 'PUBLIEE',
            'attribution_cours_id' => $context['attribution']->getKey(),
            'cree_par' => $context['teacher']->getKey(),
        ]);
    };

    $makeEvaluation($ctx, 'Evaluation de ma classe');
    $makeEvaluation($other, 'Evaluation autre classe');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/evaluations?classe_id='.$ctx['classe']->getKey().'&node_id='.$ctx['node']->getKey());

    $response->assertSuccessful();
    $libelles = collect($response->json('data'))->pluck('libelle');
    expect($libelles)->toContain('Evaluation de ma classe')
        ->and($libelles)->not->toContain('Evaluation autre classe');
});

test('un enseignant recupere le detail dune evaluation avec ses resultats', function (): void {
    $ctx = evaluationContext();
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    $evaluation = Evaluation::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'etablissement_id' => $ctx['school']->getKey(),
        'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
        'classe_id' => $ctx['classe']->getKey(),
        'node_id' => $ctx['node']->getKey(),
        'periode_id' => $ctx['periode']->getKey(),
        'type_evaluation_id' => $ctx['typeEvaluation']->getKey(),
        'libelle' => 'Interrogation n°1',
        'maxima' => 10.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-15',
        'statut' => 'PUBLIEE',
        'attribution_cours_id' => $ctx['attribution']->getKey(),
        'cree_par' => $ctx['teacher']->getKey(),
        'lock_version' => 1,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/evaluations/'.$evaluation->getKey());

    $response->assertStatus(200);
    expect($response->json('data.libelle'))->toBe('Interrogation n°1')
        ->and($response->json('data.resultats'))->toBe([]);
});

test('un enseignant enregistre les resultats eleves dune evaluation', function (): void {
    $ctx = evaluationContext();
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    $evaluation = Evaluation::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'etablissement_id' => $ctx['school']->getKey(),
        'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
        'classe_id' => $ctx['classe']->getKey(),
        'node_id' => $ctx['node']->getKey(),
        'periode_id' => $ctx['periode']->getKey(),
        'type_evaluation_id' => $ctx['typeEvaluation']->getKey(),
        'libelle' => 'Interrogation n°1',
        'maxima' => 10.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-15',
        'statut' => 'PUBLIEE',
        'attribution_cours_id' => $ctx['attribution']->getKey(),
        'cree_par' => $ctx['teacher']->getKey(),
        'lock_version' => 1,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/evaluations/'.$evaluation->getKey().'/resultats', [
            'resultats' => [
                [
                    'inscription_id' => $ctx['inscription']->getKey(),
                    'statut_resultat' => NoteResultStatus::Saisie->value,
                    'valeur' => 8.00,
                    'commentaire_parent' => 'Bien joue',
                ],
            ],
        ]);

    $response->assertStatus(200);
    expect($response->json('resultats'))->toHaveCount(1)
        ->and($response->json('resultats.0.valeur'))->toBe('8.00')
        ->and($response->json('resultats.0.statut_resultat'))->toBe('SAISIE');

    expect(ResultatEvaluation::query()->where('evaluation_id', $evaluation->getKey())->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'EVALUATION_RESULTAT_CREE')->count())->toBe(1);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/evaluations/'.$evaluation->getKey().'/resultats', [
            'resultats' => [
                [
                    'inscription_id' => $ctx['inscription']->getKey(),
                    'statut_resultat' => NoteResultStatus::Saisie->value,
                    'valeur' => 9.00,
                    'commentaire_parent' => 'Excellente progression',
                ],
            ],
        ])->assertOk();

    $modificationAudit = AuditLog::query()
        ->where('action', 'EVALUATION_RESULTAT_MODIFIE')
        ->latest('id')
        ->first();

    expect($modificationAudit)->not->toBeNull()
        ->and($modificationAudit->anciennes_valeurs['valeur'])->toBe('8.00')
        ->and((float) $modificationAudit->nouvelles_valeurs['valeur'])->toBe(9.0)
        ->and($modificationAudit->nouvelles_valeurs['commentaire_parent'])->toBe('Excellente progression');
});

test('les evaluations sont extrapolees et projetees dans la note officielle de periode', function (): void {
    $ctx = evaluationContext();
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    $activities = collect([
        ['Devoir', 10.00, 6.00],
        ['Cotation du cahier', 10.00, 7.00],
        ['Interrogation', 20.00, 15.00],
    ])->map(fn (array $activity): array => [
        'evaluation' => Evaluation::query()->create([
            'tenant_id' => $ctx['tenant']->getKey(),
            'etablissement_id' => $ctx['school']->getKey(),
            'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
            'classe_id' => $ctx['classe']->getKey(),
            'node_id' => $ctx['node']->getKey(),
            'periode_id' => $ctx['periode']->getKey(),
            'type_evaluation_id' => $ctx['typeEvaluation']->getKey(),
            'libelle' => $activity[0],
            'maxima' => $activity[1],
            'coefficient' => 1.00,
            'date_evaluation' => '2026-10-15',
            'statut' => StatutEvaluation::Publiee,
            'attribution_cours_id' => $ctx['attribution']->getKey(),
            'cree_par' => $ctx['teacher']->getKey(),
        ]),
        'value' => $activity[2],
    ]);

    foreach ($activities as $activity) {
        $this->withToken($token)
            ->postJson('/api/v1/evaluations/'.$activity['evaluation']->getKey().'/resultats', [
                'resultats' => [[
                    'inscription_id' => $ctx['inscription']->getKey(),
                    'statut_resultat' => NoteResultStatus::Saisie->value,
                    'valeur' => $activity['value'],
                ]],
            ])->assertOk();
    }

    $note = Note::query()->sole();

    expect((float) $note->valeur)->toBe(28.0)
        ->and($note->origine)->toBe(NoteOrigine::Evaluations)
        ->and($note->calcul_version)->toBe('1.0.0')
        ->and($note->calcul_hash)->toHaveLength(64)
        ->and($note->calculee_at)->not->toBeNull()
        ->and($note->lock_version)->toBe(3);
});

test('un enseignant liste les types d evaluations actifs', function (): void {
    $ctx = evaluationContext();
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    TypeEvaluation::query()->create([
        'code' => uniqueCode('INACTIF'),
        'nom' => 'Type inactif',
        'actif' => false,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/types-evaluations');

    $response->assertSuccessful();
    $codes = collect($response->json('data'))->pluck('code');
    expect($codes)->toContain($ctx['typeEvaluation']->code)
        ->and($codes->contains(fn (string $code): bool => str_contains($code, 'INACTIF')))->toBeFalse();
});

test('un enseignant publie une evaluation en brouillon', function (): void {
    $ctx = evaluationContext();
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    $evaluation = Evaluation::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'etablissement_id' => $ctx['school']->getKey(),
        'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
        'classe_id' => $ctx['classe']->getKey(),
        'node_id' => $ctx['node']->getKey(),
        'periode_id' => $ctx['periode']->getKey(),
        'type_evaluation_id' => $ctx['typeEvaluation']->getKey(),
        'libelle' => 'Devoir maison',
        'maxima' => 20.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-20',
        'statut' => StatutEvaluation::Brouillon,
        'attribution_cours_id' => $ctx['attribution']->getKey(),
        'cree_par' => $ctx['teacher']->getKey(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/evaluations/'.$evaluation->getKey().'/publication');

    $response->assertSuccessful();
    expect($response->json('data.statut'))->toBe('PUBLIEE')
        ->and($response->json('data.visible_parents_at'))->not->toBeNull();

    expect($evaluation->refresh()->statut)->toBe(StatutEvaluation::Publiee)
        ->and(AuditLog::query()->where('action', 'EVALUATION_PUBLIEE')->count())->toBe(1);
});

test('une evaluation deja publiee ne peut pas etre publiee a nouveau', function (): void {
    $ctx = evaluationContext();
    $token = teacherToken($ctx['teacher'], $ctx['tenant']);

    $evaluation = Evaluation::query()->create([
        'tenant_id' => $ctx['tenant']->getKey(),
        'etablissement_id' => $ctx['school']->getKey(),
        'annee_scolaire_id' => $ctx['classe']->annee_scolaire_id,
        'classe_id' => $ctx['classe']->getKey(),
        'node_id' => $ctx['node']->getKey(),
        'periode_id' => $ctx['periode']->getKey(),
        'type_evaluation_id' => $ctx['typeEvaluation']->getKey(),
        'libelle' => 'Interrogation',
        'maxima' => 10.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-15',
        'statut' => StatutEvaluation::Publiee,
        'attribution_cours_id' => $ctx['attribution']->getKey(),
        'cree_par' => $ctx['teacher']->getKey(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/evaluations/'.$evaluation->getKey().'/publication')
        ->assertStatus(422);
});

test('la publication d une evaluation d un autre tenant est refusee (isolation)', function (): void {
    $ctxA = evaluationContext(['tenant' => ['slug' => 'ecole-publication-a']]);
    $ctxB = evaluationContext(['tenant' => ['slug' => 'ecole-publication-b']]);
    $tokenB = teacherToken($ctxB['teacher'], $ctxB['tenant']);

    $evaluationA = Evaluation::query()->create([
        'tenant_id' => $ctxA['tenant']->getKey(),
        'etablissement_id' => $ctxA['school']->getKey(),
        'annee_scolaire_id' => $ctxA['classe']->annee_scolaire_id,
        'classe_id' => $ctxA['classe']->getKey(),
        'node_id' => $ctxA['node']->getKey(),
        'periode_id' => $ctxA['periode']->getKey(),
        'type_evaluation_id' => $ctxA['typeEvaluation']->getKey(),
        'libelle' => 'Evaluation confidentielle',
        'maxima' => 10.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-15',
        'statut' => StatutEvaluation::Brouillon,
        'attribution_cours_id' => $ctxA['attribution']->getKey(),
        'cree_par' => $ctxA['teacher']->getKey(),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$tokenB)
        ->postJson('/api/v1/evaluations/'.$evaluationA->getKey().'/publication')
        ->assertNotFound();

    expect($evaluationA->refresh()->statut)->toBe(StatutEvaluation::Brouillon);
});

test('une evaluation appartenant a un autre tenant est inaccessible (isolation)', function (): void {
    $ctxA = evaluationContext(['tenant' => ['slug' => 'ecole-isolation-a']]);
    $ctxB = evaluationContext(['tenant' => ['slug' => 'ecole-isolation-b']]);
    $tokenB = teacherToken($ctxB['teacher'], $ctxB['tenant']);

    $evaluationA = Evaluation::query()->create([
        'tenant_id' => $ctxA['tenant']->getKey(),
        'etablissement_id' => $ctxA['school']->getKey(),
        'annee_scolaire_id' => $ctxA['classe']->annee_scolaire_id,
        'classe_id' => $ctxA['classe']->getKey(),
        'node_id' => $ctxA['node']->getKey(),
        'periode_id' => $ctxA['periode']->getKey(),
        'type_evaluation_id' => $ctxA['typeEvaluation']->getKey(),
        'libelle' => 'Interrogation secretes',
        'maxima' => 10.00,
        'coefficient' => 1.00,
        'date_evaluation' => '2026-10-15',
        'statut' => 'PUBLIEE',
        'attribution_cours_id' => $ctxA['attribution']->getKey(),
        'cree_par' => $ctxA['teacher']->getKey(),
        'lock_version' => 1,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$tokenB)
        ->getJson('/api/v1/evaluations/'.$evaluationA->getKey())
        ->assertStatus(404);
});
