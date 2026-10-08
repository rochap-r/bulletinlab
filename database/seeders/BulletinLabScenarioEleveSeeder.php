<?php

namespace Database\Seeders;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use App\Models\Note;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;

class BulletinLabScenarioEleveSeeder extends Seeder
{
    /**
     * Périodes évaluées par une note saisie. Les périodes de total restent
     * calculées à la lecture et le repêchage n'est pas encore traité.
     *
     * @var list<string>
     */
    private const CODES_EVALUATION = ['P1', 'P2', 'EXAM_S1', 'P3', 'P4', 'EXAM_S2'];

    /**
     * Rendement visé par rang d'inscription, pour un jeu de notes reproductible.
     * Les deux derniers élèves restent sous la barre des 50 % afin d'exercer un
     * scénario d'échec dans chaque classe.
     *
     * @var list<float>
     */
    private const RENDEMENTS = [0.86, 0.79, 0.72, 0.45, 0.38];

    public function run(): void
    {
        $tenant = Tenant::where('slug', 'ecole-test')->firstOrFail();
        $enseignant = Utilisateur::where('tenant_id', $tenant->id)
            ->where('telephone', '+243000000003')
            ->firstOrFail();

        $classes = Classe::where('tenant_id', $tenant->id)
            ->whereHas('modeleBulletins')
            ->orderBy('id')
            ->get();

        foreach ($classes as $classe) {
            $this->seedClasse($tenant, $classe, $enseignant);
        }
    }

    private function seedClasse(Tenant $tenant, Classe $classe, Utilisateur $enseignant): void
    {
        $modele = ModeleBulletin::where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $classe->annee_scolaire_id)
            ->first();

        if ($modele === null) {
            return;
        }

        $periodes = ModelePeriode::where('modele_bulletin_id', $modele->id)
            ->whereIn('code', self::CODES_EVALUATION)
            ->get()
            ->keyBy('code');

        $maxima = ModeleMaximum::where('modele_bulletin_id', $modele->id)
            ->whereIn('periode_id', $periodes->modelKeys())
            ->get()
            ->groupBy('node_id');

        $matieres = ModeleNode::where('modele_bulletin_id', $modele->id)
            ->where('type_node', 'MATIERE')
            ->orderBy('ordre')
            ->orderBy('id')
            ->get();

        $inscriptions = Inscription::where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $classe->annee_scolaire_id)
            ->with('eleve')
            ->orderBy('id')
            ->get();

        foreach ($inscriptions as $index => $inscription) {
            $rendement = self::RENDEMENTS[$index] ?? 0.70;

            $bulletin = Bulletin::updateOrCreate(
                ['inscription_id' => $inscription->id],
                [
                    'tenant_id' => $tenant->id,
                    'classe_id' => $classe->id,
                    'modele_bulletin_id' => $modele->id,
                    'decision_statut' => 'EN_COURS',
                    'nombre_eleves_classe' => $inscriptions->count(),
                    'verrouille' => false,
                ],
            );

            foreach ($matieres as $matiere) {
                foreach (self::CODES_EVALUATION as $code) {
                    $periode = $periodes->get($code);
                    $maximum = $maxima->get($matiere->id)
                        ?->firstWhere('periode_id', $periode?->id);

                    if (! $periode instanceof ModelePeriode
                        || ! $maximum instanceof ModeleMaximum) {
                        continue;
                    }

                    Note::updateOrCreate(
                        [
                            'bulletin_id' => $bulletin->id,
                            'node_id' => $matiere->id,
                            'periode_id' => $periode->id,
                        ],
                        [
                            'tenant_id' => $tenant->id,
                            'statut_resultat' => NoteResultStatus::Saisie,
                            'valeur' => $this->valeur(
                                $rendement,
                                $inscription->eleve->nom,
                                $matiere->id,
                                $code,
                                (int) $maximum->maxima,
                            ),
                            'saisie_par' => $enseignant->id,
                            'date_saisie' => now(),
                        ],
                    );
                }
            }
        }
    }

    /**
     * Produit une note entière, reproductible et bornée par le maximum, à partir
     * du rendement de l'élève et d'une variation déterministe par matière/période.
     */
    private function valeur(
        float $rendement,
        string $eleve,
        int $nodeId,
        string $code,
        int $maximum,
    ): int {
        $variation = ((crc32("{$eleve}:{$nodeId}:{$code}") % 21) - 10) / 100;
        $ratio = min(0.98, max(0.15, $rendement + $variation));

        return (int) max(0, min($maximum, (int) round($maximum * $ratio)));
    }
}
