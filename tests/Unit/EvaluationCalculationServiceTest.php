<?php

use App\Domains\Evaluation\DTOs\EvaluationAggregate;
use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Evaluation\Services\EvaluationCalculationService;

function composante(
    int $evaluationId,
    string $libelle,
    float $maxima,
    float $coefficient,
    ?string $statut = NoteResultStatus::Saisie->value,
    ?float $valeur = null,
    ?string $typeEvaluation = null,
): array {
    return [
        'evaluation_id' => $evaluationId,
        'libelle' => $libelle,
        'type_evaluation' => $typeEvaluation,
        'maxima' => $maxima,
        'coefficient' => $coefficient,
        'statut_resultat' => $statut,
        'valeur' => $valeur,
    ];
}

test('il calcule la note de periode a partir des composantes (exemple secondaire)', function (): void {
    $service = new EvaluationCalculationService;

    $aggregate = $service->compute([
        composante(1, 'Interrogation 1', 10.00, 1.00, NoteResultStatus::Saisie->value, 7.00, 'INTERROGATION'),
        composante(2, 'Devoir maison', 20.00, 1.00, NoteResultStatus::Saisie->value, 14.00, 'DEVOIR'),
        composante(3, 'Cahier', 15.00, 0.50, NoteResultStatus::Saisie->value, 15.00, 'CAHIER'),
    ]);

    expect($aggregate)->toBeInstanceOf(EvaluationAggregate::class)
        ->and($aggregate->totalObtenu)->toBe(28.5)
        ->and($aggregate->totalMaxima)->toBe(37.5)
        ->and($aggregate->pourcentage)->toBe(76.0)
        ->and($aggregate->nombreComposantes)->toBe(3);
});

test('il exclut les absences dispenses et annules des totaux', function (): void {
    $service = new EvaluationCalculationService;

    $aggregate = $service->compute([
        composante(1, 'Interrogation', 10.00, 1.00, NoteResultStatus::Saisie->value, 8.00),
        composante(2, 'Devoir', 20.00, 1.00, NoteResultStatus::Absente->value),
        composante(3, 'Cahier', 15.00, 0.50, NoteResultStatus::Dispensee->value),
        composante(4, 'Exercice', 10.00, 1.00, NoteResultStatus::Annulee->value, 9.00),
    ]);

    expect($aggregate->totalObtenu)->toBe(8.0)
        ->and($aggregate->totalMaxima)->toBe(10.0)
        ->and($aggregate->pourcentage)->toBe(80.0)
        ->and($aggregate->nombreComposantes)->toBe(1);
});

test('il retourne un total nul sans composante saisie', function (): void {
    $service = new EvaluationCalculationService;

    $aggregate = $service->compute([]);

    expect($aggregate->totalObtenu)->toBe(0.0)
        ->and($aggregate->totalMaxima)->toBe(0.0)
        ->and($aggregate->pourcentage)->toBe(0.0)
        ->and($aggregate->nombreComposantes)->toBe(0);
});

test('il applique le coefficient aux points obtenus et aux maxima', function (): void {
    $service = new EvaluationCalculationService;

    $aggregate = $service->compute([
        composante(1, 'Cahier', 15.00, 0.50, NoteResultStatus::Saisie->value, 15.00),
    ]);

    expect($aggregate->totalObtenu)->toBe(7.5)
        ->and($aggregate->totalMaxima)->toBe(7.5)
        ->and($aggregate->pourcentage)->toBe(100.0);
});

test('il extrapole le total des evaluations vers le maxima officiel du cours', function (): void {
    $service = new EvaluationCalculationService;
    $aggregate = $service->compute([
        composante(1, 'Devoir', 10.00, 1.00, NoteResultStatus::Saisie->value, 6.00),
        composante(2, 'Cahier', 10.00, 1.00, NoteResultStatus::Saisie->value, 7.00),
        composante(3, 'Interrogation', 20.00, 1.00, NoteResultStatus::Saisie->value, 15.00),
    ]);

    expect($service->extrapolate($aggregate, 40.00))->toBe(28.0)
        ->and($service->extrapolate($aggregate, 20.00))->toBe(14.0);
});

test('il retourne la valeur directe en mode saisie directe', function (): void {
    $service = new EvaluationCalculationService;

    $aggregate = $service->compute([], 19.00, 40.00);

    expect($aggregate)->toBeInstanceOf(EvaluationAggregate::class)
        ->and($aggregate->totalObtenu)->toBe(19.0)
        ->and($aggregate->totalMaxima)->toBe(40.0)
        ->and($aggregate->pourcentage)->toBe(47.5)
        ->and($aggregate->nombreComposantes)->toBe(1)
        ->and($aggregate->saisieDirecte)->toBeTrue()
        ->and($aggregate->valeurDirecte)->toBe(19.0)
        ->and($aggregate->maximaReference)->toBe(40.0);
});

test('il retourne zero sans maxima_reference en mode saisie directe', function (): void {
    $service = new EvaluationCalculationService;

    $aggregate = $service->compute([], 19.00, null);

    expect($aggregate->totalObtenu)->toBe(19.0)
        ->and($aggregate->totalMaxima)->toBe(0.0)
        ->and($aggregate->pourcentage)->toBe(0.0)
        ->and($aggregate->saisieDirecte)->toBeTrue();
});

test('toArray expose les champs de saisie directe', function (): void {
    $service = new EvaluationCalculationService;

    $aggregate = $service->compute([], 19.00, 40.00);
    $array = $aggregate->toArray();

    expect($array['saisie_directe'])->toBeTrue()
        ->and($array['valeur_directe'])->toBe(19.0)
        ->and($array['maxima_reference'])->toBe(40.0);
});

test('toArray expose null pour les champs de saisie directe en mode composantes', function (): void {
    $service = new EvaluationCalculationService;

    $aggregate = $service->compute([
        composante(1, 'Interrogation', 10.00, 1.00, NoteResultStatus::Saisie->value, 8.00),
    ]);
    $array = $aggregate->toArray();

    expect($array['saisie_directe'])->toBeFalse()
        ->and($array['valeur_directe'])->toBeNull()
        ->and($array['maxima_reference'])->toBeNull()
        ->and($array['nombre_composantes'])->toBe(1);
});
