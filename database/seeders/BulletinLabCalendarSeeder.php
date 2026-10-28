<?php

namespace Database\Seeders;

use App\Models\CalendrierCycleParametre;
use App\Models\CalendrierEvenement;
use App\Models\CalendrierPeriode;
use App\Models\CalendrierScolaire;
use Illuminate\Database\Seeder;

class BulletinLabCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $calendar = CalendrierScolaire::query()->updateOrCreate(
            ['code' => 'RDC_2026_2027'],
            [
                'libelle' => 'Calendrier scolaire officiel RDC 2026-2027',
                'pays' => 'RDC',
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-07-02',
                'officiel' => true,
            ]
        );

        $profiles = [
            ['MATERNELLE', 'TRIMESTRIEL', 192, ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI']],
            ['PRIMAIRE', 'TRIMESTRIEL', 222, ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI']],
            ['SECONDAIRE_TECHNIQUE', 'SEMESTRIEL', 222, ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI']],
        ];

        foreach ($profiles as [$cycle, $mode, $days, $schoolDays]) {
            CalendrierCycleParametre::query()->updateOrCreate(
                ['calendrier_scolaire_id' => $calendar->id, 'cycle_scolaire' => $cycle],
                [
                    'mode_decoupage' => $mode,
                    'jours_ouvrables_attendus' => $days,
                    'heures_minimales' => 900,
                    'jours_classe' => $schoolDays,
                ]
            );
        }

        $periods = [
            ['MAT_T1', 'Premier trimestre', 'TRIMESTRE', 'MATERNELLE', '2026-09-01', '2026-12-22'],
            ['MAT_P1', '1ère période', 'PERIODE', 'MATERNELLE', '2026-09-01', '2026-11-04'],
            ['MAT_P2', '2ème période', 'PERIODE', 'MATERNELLE', '2026-11-09', '2026-12-22'],
            ['MAT_T2', 'Deuxième trimestre', 'TRIMESTRE', 'MATERNELLE', '2027-01-11', '2027-03-20'],
            ['MAT_P3', '3ème période', 'PERIODE', 'MATERNELLE', '2027-01-11', '2027-02-17'],
            ['MAT_P4', '4ème période', 'PERIODE', 'MATERNELLE', '2027-02-22', '2027-03-20'],
            ['MAT_T3', 'Troisième trimestre', 'TRIMESTRE', 'MATERNELLE', '2027-04-05', '2027-07-02'],
            ['MAT_P5', '5ème période', 'PERIODE', 'MATERNELLE', '2027-04-05', '2027-05-21'],
            ['MAT_P6', '6ème période', 'PERIODE', 'MATERNELLE', '2027-05-24', '2027-07-02'],
            ['PRI_T1', 'Premier trimestre', 'TRIMESTRE', 'PRIMAIRE', '2026-09-01', '2026-12-22'],
            ['PRI_P1', '1ère période', 'PERIODE', 'PRIMAIRE', '2026-09-01', '2026-11-04'],
            ['PRI_P2', '2ème période', 'PERIODE', 'PRIMAIRE', '2026-11-09', '2026-12-22'],
            ['PRI_EXAM_T1', 'Examen du 1er trimestre', 'EXAMEN', 'PRIMAIRE', '2026-12-10', '2026-12-17'],
            ['PRI_T2', 'Deuxième trimestre', 'TRIMESTRE', 'PRIMAIRE', '2027-01-11', '2027-03-20'],
            ['PRI_P3', '3ème période', 'PERIODE', 'PRIMAIRE', '2027-01-11', '2027-02-17'],
            ['PRI_P4', '4ème période', 'PERIODE', 'PRIMAIRE', '2027-02-22', '2027-03-20'],
            ['PRI_EXAM_T2', 'Examen du 2ème trimestre', 'EXAMEN', 'PRIMAIRE', '2027-03-09', '2027-03-16'],
            ['PRI_T3', 'Troisième trimestre', 'TRIMESTRE', 'PRIMAIRE', '2027-04-05', '2027-07-02'],
            ['PRI_P5', '5ème période', 'PERIODE', 'PRIMAIRE', '2027-04-05', '2027-05-22'],
            ['PRI_P6', '6ème période', 'PERIODE', 'PRIMAIRE', '2027-05-24', '2027-07-02'],
            ['PRI_EXAM_T3', 'Examen du 3ème trimestre', 'EXAMEN', 'PRIMAIRE', '2027-06-11', '2027-06-18'],
            ['SEMESTRE_1', 'Premier semestre', 'SEMESTRE', 'SECONDAIRE_TECHNIQUE', '2026-09-01', '2027-02-17'],
            ['P1', '1ère période', 'PERIODE', 'SECONDAIRE_TECHNIQUE', '2026-09-01', '2026-11-04'],
            ['P2', '2ème période', 'PERIODE', 'SECONDAIRE_TECHNIQUE', '2026-11-09', '2027-02-17'],
            ['EXAM_S1', 'Examens du 1er semestre', 'EXAMEN', 'SECONDAIRE_TECHNIQUE', '2027-02-09', '2027-02-17'],
            ['SEMESTRE_2', 'Second semestre', 'SEMESTRE', 'SECONDAIRE_TECHNIQUE', '2027-02-22', '2027-07-02'],
            ['P3', '3ème période', 'PERIODE', 'SECONDAIRE_TECHNIQUE', '2027-02-22', '2027-04-24'],
            ['P4', '4ème période', 'PERIODE', 'SECONDAIRE_TECHNIQUE', '2027-04-26', '2027-07-02'],
            ['EXAM_S2', 'Examens du 2ème semestre', 'EXAMEN', 'SECONDAIRE_TECHNIQUE', '2027-05-24', '2027-06-01'],
            ['DELIBERATION', 'Délibérations de fin d’année', 'DELIBERATION', 'SECONDAIRE_TECHNIQUE', '2027-06-10', '2027-06-18'],
            ['PROCLAMATION', 'Proclamation officielle', 'PROCLAMATION', 'SECONDAIRE_TECHNIQUE', '2027-07-02', '2027-07-02'],
        ];

        foreach ($periods as $order => [$code, $name, $type, $cycle, $start, $end]) {
            CalendrierPeriode::query()->updateOrCreate(
                ['calendrier_scolaire_id' => $calendar->id, 'code' => $code],
                [
                    'nom' => $name,
                    'type_periode' => $type,
                    'cycle_scolaire' => $cycle,
                    'date_debut' => $start,
                    'date_fin' => $end,
                    'ordre' => $order + 1,
                ]
            );
        }

        foreach ($this->events() as $event) {
            CalendrierEvenement::query()->updateOrCreate(
                ['calendrier_scolaire_id' => $calendar->id, 'code' => $event['code']],
                $event,
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function events(): array
    {
        $everyone = ['TOUS'];
        $schoolStaff = ['DIRECTION', 'ENSEIGNANT'];
        $direction = ['DIRECTION', 'PLATEFORME'];

        return [
            $this->event('RENTREE', 'Rentrée scolaire 2026-2027', 'RENTREE', 'TOUS', '2026-09-01', '2026-09-01', $everyone, 'CRITIQUE', 30),
            $this->event('CONGE_T1', 'Congé de détente de novembre', 'CONGE', 'TOUS', '2026-11-05', '2026-11-07', $schoolStaff, 'INFORMATION', 7),
            $this->event('REPRISE_T1', 'Reprise des activités après le congé de détente', 'REPRISE', 'TOUS', '2026-11-09', '2026-11-09', $schoolStaff, 'VIGILANCE', 3),
            $this->event('VACANCES_T1', 'Vacances de fin d’année', 'VACANCES', 'TOUS', '2026-12-23', '2027-01-09', $everyone, 'INFORMATION', 10),
            $this->event('REPRISE_JANVIER', 'Reprise des cours', 'REPRISE', 'TOUS', '2027-01-11', '2027-01-11', $schoolStaff, 'VIGILANCE', 5),
            $this->event('CONGE_T2', 'Congé de détente de février', 'CONGE', 'TOUS', '2027-02-18', '2027-02-20', $schoolStaff, 'INFORMATION', 7),
            $this->event('VACANCES_T2', 'Vacances de mars et avril', 'VACANCES', 'TOUS', '2027-03-22', '2027-04-03', $everyone, 'INFORMATION', 10),
            $this->event('REPRISE_AVRIL', 'Reprise des cours', 'REPRISE', 'TOUS', '2027-04-05', '2027-04-05', $schoolStaff, 'VIGILANCE', 5),
            $this->event('EVALUATION_EVEIL_T1', 'Évaluation du niveau d’éveil — 1er trimestre', 'EXAMEN', 'MATERNELLE', '2026-12-10', '2026-12-17', $schoolStaff, 'VIGILANCE', 14),
            $this->event('EVALUATION_EVEIL_T2', 'Évaluation du niveau d’éveil — 2ème trimestre', 'EXAMEN', 'MATERNELLE', '2027-03-09', '2027-03-16', $schoolStaff, 'VIGILANCE', 14),
            $this->event('EVALUATION_EVEIL_T3', 'Évaluation du niveau d’éveil — 3ème trimestre', 'EXAMEN', 'MATERNELLE', '2027-06-11', '2027-06-18', $schoolStaff, 'VIGILANCE', 14),
            $this->event('ENAFEP', 'Examen national de fin d’études primaires (ENAFEP)', 'EXAMEN_NATIONAL', 'PRIMAIRE', '2027-06-08', '2027-06-09', $everyone, 'CRITIQUE', 30),
            $this->event('TENASOSP', 'TENASOSP', 'EXAMEN_NATIONAL', 'SECONDAIRE_TECHNIQUE', '2027-06-03', '2027-06-04', $everyone, 'CRITIQUE', 30),
            $this->event('EXETAT_PRELIMINAIRE', 'Épreuve préliminaire de l’Examen d’État', 'EXAMEN_NATIONAL', 'SECONDAIRE_TECHNIQUE', '2027-02-13', '2027-02-13', $everyone, 'CRITIQUE', 21),
            $this->event('EXETAT_HORS_SESSION', 'Épreuves hors session de l’Examen d’État', 'EXAMEN_NATIONAL', 'SECONDAIRE_TECHNIQUE', '2027-05-03', '2027-05-18', $everyone, 'CRITIQUE', 30),
            $this->event('EXETAT_SESSION', 'Session ordinaire de l’Examen d’État et JUNACYC', 'EXAMEN_NATIONAL', 'SECONDAIRE_TECHNIQUE', '2027-06-21', '2027-06-24', $everyone, 'CRITIQUE', 30),
            $this->event('GUIDANCE', 'Semaine nationale de guidance', 'SEMAINE_THEMATIQUE', 'PRIMAIRE', '2027-02-15', '2027-02-19', $schoolStaff, 'INFORMATION', 10),
            $this->event('CITOYENNETE', 'Semaine de la Citoyenneté', 'SEMAINE_THEMATIQUE', 'TOUS', '2027-02-22', '2027-02-27', $schoolStaff, 'INFORMATION', 10),
            $this->event('SCIENCES_TECHNOLOGIES', 'Semaine de la Science et des Technologies', 'SEMAINE_THEMATIQUE', 'PRIMAIRE', '2027-04-06', '2027-04-10', $schoolStaff, 'INFORMATION', 10),
            $this->event('STAGES_TECHNIQUES_S1', 'Stages des filières techniques — 1er semestre', 'STAGE', 'SECONDAIRE_TECHNIQUE', '2026-12-04', '2026-12-21', $schoolStaff, 'VIGILANCE', 21),
            $this->event('STAGES_LONGS', 'Stages des élèves-maîtres, niveau 4 et cycle professionnel', 'STAGE', 'SECONDAIRE_TECHNIQUE', '2027-02-22', '2027-04-12', $schoolStaff, 'VIGILANCE', 30),
            $this->event('RAPPORT_RENTREE_ECOLE', 'Dépôt des rapports de rentrée aux bureaux de proximité', 'ADMINISTRATIF', 'TOUS', '2026-10-09', '2026-10-09', $direction, 'CRITIQUE', 14),
            $this->event('AG_PARENTS_1', 'Assemblée générale des parents', 'REUNION', 'TOUS', '2026-10-10', '2026-10-10', $direction, 'VIGILANCE', 10),
            $this->event('AG_PARENTS_2', 'Assemblée générale des parents', 'REUNION', 'TOUS', '2027-02-06', '2027-02-06', $direction, 'VIGILANCE', 10),
            $this->event('AG_PARENTS_3', 'Assemblée générale des parents', 'REUNION', 'TOUS', '2027-05-15', '2027-05-15', $direction, 'VIGILANCE', 10),
            $this->event('CLOTURE', 'Clôture de l’année scolaire et proclamation officielle', 'PROCLAMATION', 'TOUS', '2027-07-02', '2027-07-02', $everyone, 'CRITIQUE', 21),
        ];
    }

    /** @return array<string, mixed> */
    private function event(
        string $code,
        string $name,
        string $type,
        string $cycle,
        string $start,
        string $end,
        array $audiences,
        string $level,
        int $leadDays,
    ): array {
        return [
            'code' => $code,
            'nom' => $name,
            'type_evenement' => $type,
            'cycle_scolaire' => $cycle,
            'date_debut' => $start,
            'date_fin' => $end,
            'audiences' => $audiences,
            'niveau_alerte' => $level,
            'delai_alerte_jours' => $leadDays,
            'description' => null,
        ];
    }
}
