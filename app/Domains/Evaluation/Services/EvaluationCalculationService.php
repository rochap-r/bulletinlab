<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\DTOs\EvaluationAggregate;
use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Enums\StatutEvaluation;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\ModeleNode;
use App\Models\ModelePeriode;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EvaluationCalculationService
{
    public function extrapolate(
        EvaluationAggregate $aggregate,
        float $officialMaximum,
    ): ?float {
        if ($aggregate->totalMaxima <= 0.0) {
            return null;
        }

        return round(
            $aggregate->totalObtenu
                / $aggregate->totalMaxima
                * $officialMaximum,
            2,
        );
    }

    /**
     * Calcule la note de periode a partir des composantes d'un eleve.
     *
     *   total_obtenu  = Σ (valeur × coefficient)
     *   total_maxima  = Σ (maxima × coefficient)
     *   pourcentage   = total_obtenu / total_maxima × 100
     *
     * Seules les composantes en statut SAISIE avec une valeur renseignée
     * contribuent au total. Les absences, dispenses et annulations sont exclues
     * des deux totaux.
     *
     * Mode saisie directe : si $valeurDirecte est fournie, elle est retournée
     * telle quelle comme note de periode sur la base $maximaReference, sans
     * sommer les composantes.
     *
     * @param  list<array{
     *     evaluation_id: int,
     *     libelle: string,
     *     type_evaluation: string|null,
     *     maxima: float,
     *     coefficient: float,
     *     statut_resultat: string|null,
     *     valeur: float|null,
     * }>  $composantes
     * @param  float|null  $valeurDirecte  Note saisie directement pour la periode (optionnel)
     * @param  float|null  $maximaReference  Total de reference de la periode (optionnel)
     */
    public function compute(
        array $composantes,
        ?float $valeurDirecte = null,
        ?float $maximaReference = null,
    ): EvaluationAggregate {
        // Mode saisie directe : on retourne la valeur directe telle quelle.
        if ($valeurDirecte !== null) {
            $maximaReference = $maximaReference ?? 0.0;
            $pourcentage = $maximaReference > 0.0
                ? round($valeurDirecte / $maximaReference * 100.0, 2)
                : 0.0;

            return new EvaluationAggregate(
                totalObtenu: round($valeurDirecte, 2),
                totalMaxima: round($maximaReference, 2),
                pourcentage: $pourcentage,
                nombreComposantes: 1,
                composantes: [],
                valeurDirecte: $valeurDirecte,
                maximaReference: $maximaReference,
                saisieDirecte: true,
            );
        }

        $obtenu = 0.0;
        $maxima = 0.0;
        $nombreComposantes = 0;
        /** @var list<array<string, float|int|string|null|bool>> $lignes */
        $lignes = [];

        foreach ($composantes as $composante) {
            $maximaBase = (float) $composante['maxima'];
            $coefficient = (float) $composante['coefficient'];
            $statut = $composante['statut_resultat'];
            $valeur = $composante['valeur'];

            $prisEnCompte = $statut === NoteResultStatus::Saisie->value
                && $valeur !== null;

            $ligne = [
                'evaluation_id' => $composante['evaluation_id'],
                'libelle' => $composante['libelle'],
                'type_evaluation' => $composante['type_evaluation'],
                'maxima' => $maximaBase,
                'coefficient' => $coefficient,
                'statut_resultat' => $statut,
                'valeur' => $valeur,
                'pris_en_compte' => $prisEnCompte,
            ];

            if (! $prisEnCompte) {
                $ligne['points'] = 0.0;
                $ligne['maxima_pondere'] = 0.0;
                $lignes[] = $ligne;

                continue;
            }

            $valeur = (float) $valeur;
            $points = round($valeur * $coefficient, 2);
            $maximaPondere = round($maximaBase * $coefficient, 2);

            $obtenu += $points;
            $maxima += $maximaPondere;
            $nombreComposantes++;

            $ligne['points'] = $points;
            $ligne['maxima_pondere'] = $maximaPondere;
            $lignes[] = $ligne;
        }

        $pourcentage = $maxima > 0.0
            ? round($obtenu / $maxima * 100.0, 2)
            : 0.0;

        return new EvaluationAggregate(
            totalObtenu: round($obtenu, 2),
            totalMaxima: round($maxima, 2),
            pourcentage: $pourcentage,
            nombreComposantes: $nombreComposantes,
            composantes: $lignes,
            valeurDirecte: null,
            maximaReference: $maximaReference,
            saisieDirecte: false,
        );
    }

    /**
     * Calcule la note de periode pour un eleve sur une mati�re et une periode.
     *
     * Les composantes sont les evaluations de la classe de l'eleve pour le
     * node et la periode donn�s, jointes au resultat de cet eleve.
     */
    public function forInscription(
        Inscription $inscription,
        ModeleNode $node,
        ModelePeriode $periode,
    ): EvaluationAggregate {
        $evaluations = Evaluation::query()
            ->where('tenant_id', $inscription->tenant_id)
            ->where('classe_id', $inscription->classe_id)
            ->where('node_id', $node->getKey())
            ->where('periode_id', $periode->getKey())
            ->whereIn('statut', [
                StatutEvaluation::Publiee->value,
                StatutEvaluation::Verrouillee->value,
            ])
            ->with([
                'resultats' => fn (HasMany $results): HasMany => $results
                    ->where('inscription_id', $inscription->getKey()),
                'typeEvaluation',
            ])
            ->get();

        /** @var list<array{...}> $composantes */
        $composantes = [];

        foreach ($evaluations as $evaluation) {
            $resultat = $evaluation->resultats->first(
                fn ($resultat): bool => $resultat->inscription_id === $inscription->getKey(),
            );

            $composantes[] = [
                'evaluation_id' => $evaluation->getKey(),
                'libelle' => $evaluation->libelle,
                'type_evaluation' => $evaluation->typeEvaluation?->code,
                'maxima' => (float) $evaluation->maxima,
                'coefficient' => (float) $evaluation->coefficient,
                'statut_resultat' => $resultat?->statut_resultat,
                'valeur' => $resultat?->valeur,
            ];
        }

        return $this->compute($composantes);
    }
}
