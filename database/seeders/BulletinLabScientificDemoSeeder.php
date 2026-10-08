<?php

namespace Database\Seeders;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Models\AnneeScolaire;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\SystemTemplateBulletin;
use App\Models\Tenant;
use App\Models\Utilisateur;
use App\Services\Templates\CloneSystemTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulletinLabScientificDemoSeeder extends Seeder
{
    protected const TEMPLATE_PREFIX = 'SCIENTIFIQUE_';

    protected const CLASS_PREFIX = 'DEMOSCI';

    protected const STUDENT_PREFIX = 'DEMOSC00';

    protected const REGISTRATION_PREFIX = 'DEMO-SCI-';

    protected const SECTION_NAME = 'Scientifique';

    protected const CONFIGURATION_SEEDER = BulletinLabScientificSeeder::class;

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $tenant = Tenant::query()->where('slug', 'ecole-test')->firstOrFail();
        $school = Etablissement::query()->where('tenant_id', $tenant->id)->where('code_ministere', 'CST')->firstOrFail();
        $teacher = Utilisateur::query()->where('tenant_id', $tenant->id)->where('telephone', '+243000000003')->firstOrFail();
        $calendarId = DB::table('calendriers_scolaires')->where('code', 'RDC_2026_2027')->value('id');
        $schoolYear = AnneeScolaire::query()->where('calendrier_scolaire_id', $calendarId)->firstOrFail();

        DB::transaction(function () use ($tenant, $school, $teacher, $schoolYear): void {
            $this->call(static::CONFIGURATION_SEEDER);

            foreach (range(1, 4) as $year) {
                $template = SystemTemplateBulletin::query()->where('code', static::TEMPLATE_PREFIX.$year)->firstOrFail();
                $class = Classe::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'etablissement_id' => $school->id, 'annee_scolaire_id' => $schoolYear->id, 'code' => static::CLASS_PREFIX.$year],
                    [
                        'nom' => ($year === 1 ? '1ère' : $year.'ème').' '.static::SECTION_NAME.' — Démonstration',
                        'cycle_id' => $template->cycle_id, 'degre_id' => $template->degre_id,
                        'niveau_id' => $template->niveau_min_id, 'option_id' => $template->option_id,
                        'system_template_id' => $template->id, 'parallele' => 'Démo', 'active' => true,
                    ],
                );
                if ((int) $class->system_template_id !== (int) $template->id) {
                    throw new \RuntimeException('La classe de démonstration utilise une autre configuration.');
                }
                $student = Eleve::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'num_permanent' => static::STUDENT_PREFIX.$year],
                    [
                        'etablissement_id' => $school->id, 'nom' => ['Ilunga', 'Kabeya', 'Ngoy', 'Mbuyi'][$year - 1],
                        'postnom' => 'Exemple', 'prenom' => ['Grâce', 'David', 'Marie', 'Paul'][$year - 1],
                        'sexe' => $year % 2 === 1 ? 'F' : 'M', 'date_naissance' => (2012 - $year).'-04-18',
                        'lieu_naissance' => 'Kolwezi', 'actif' => true,
                    ],
                );
                $registration = Inscription::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'eleve_id' => $student->id, 'classe_id' => $class->id, 'annee_scolaire_id' => $schoolYear->id],
                    ['numero_inscription' => static::REGISTRATION_PREFIX.$year, 'statut' => 'ACTIF', 'date_inscription' => $schoolYear->date_debut],
                );
                $model = ModeleBulletin::query()->where('classe_id', $class->id)
                    ->where('annee_scolaire_id', $schoolYear->id)->where('system_template_id', $template->id)->first();
                $model ??= app(CloneSystemTemplateService::class)->cloneForClasse($class);
                $bulletin = Bulletin::query()->firstOrCreate(
                    ['inscription_id' => $registration->id],
                    ['tenant_id' => $tenant->id, 'classe_id' => $class->id, 'modele_bulletin_id' => $model->id,
                        'decision_statut' => 'EN_COURS', 'nombre_eleves_classe' => 1, 'verrouille' => false],
                );
                $this->seedNotes($bulletin, $teacher, $year);
            }
        });
    }

    private function seedNotes(Bulletin $bulletin, Utilisateur $teacher, int $year): void
    {
        $periods = ModelePeriode::query()->where('modele_bulletin_id', $bulletin->modele_bulletin_id)
            ->whereIn('code', ['P1', 'P2', 'EXAM_S1', 'P3', 'P4', 'EXAM_S2'])->get()->keyBy('id');
        $subjectIds = ModeleNode::query()->where('modele_bulletin_id', $bulletin->modele_bulletin_id)
            ->where('type_node', 'MATIERE')->pluck('id');
        $maxima = ModeleMaximum::query()->where('modele_bulletin_id', $bulletin->modele_bulletin_id)
            ->whereIn('periode_id', $periods->modelKeys())->whereIn('node_id', $subjectIds)->get();

        foreach ($maxima as $maximum) {
            $period = $periods->get($maximum->periode_id);
            $ratio = 0.65 + ((crc32('DEMO:'.$year.':'.$maximum->node_id.':'.$period->code) % 26) / 100);
            Note::query()->firstOrCreate(
                ['bulletin_id' => $bulletin->id, 'node_id' => $maximum->node_id, 'periode_id' => $maximum->periode_id],
                ['tenant_id' => $bulletin->tenant_id, 'statut_resultat' => NoteResultStatus::Saisie,
                    'valeur' => round((float) $maximum->maxima * $ratio), 'saisie_par' => $teacher->id, 'date_saisie' => now()],
            );
        }
    }
}
