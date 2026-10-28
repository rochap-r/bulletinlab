<?php

namespace App\Domains\Evaluation\DTOs;

final readonly class EvaluationAggregate
{
    /**
     * @param  array{
     *     evaluation_id: int,
     *     libelle: string,
     *     type_evaluation: string|null,
     *     maxima: float,
     *     coefficient: float,
     *     statut_resultat: string|null,
     *     valeur: float|null,
     *     pris_en_compte: bool,
     *     points: float,
     *     maxima_pondere: float,
     * }  $composantes
     */
    public function __construct(
        public float $totalObtenu,
        public float $totalMaxima,
        public float $pourcentage,
        public int $nombreComposantes,
        public array $composantes = [],
        public ?float $valeurDirecte = null,
        public ?float $maximaReference = null,
        public bool $saisieDirecte = false,
    ) {}

    /**
     * @return array<string, float|int|bool|null|list<array<string, float|int|string|null|bool>>>
     */
    public function toArray(): array
    {
        return [
            'total_obtenu' => (float) number_format($this->totalObtenu, 2),
            'total_maxima' => (float) number_format($this->totalMaxima, 2),
            'pourcentage' => (float) number_format($this->pourcentage, 2),
            'nombre_composantes' => $this->nombreComposantes,
            'composantes' => $this->composantes,
            'valeur_directe' => $this->valeurDirecte !== null ? (float) number_format($this->valeurDirecte, 2) : null,
            'maxima_reference' => $this->maximaReference !== null ? (float) number_format($this->maximaReference, 2) : null,
            'saisie_directe' => $this->saisieDirecte,
        ];
    }
}
