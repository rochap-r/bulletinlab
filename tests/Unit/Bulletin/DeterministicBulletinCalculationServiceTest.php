<?php

use App\Domains\Bulletin\DTOs\BulletinCalculationInput;
use App\Domains\Bulletin\DTOs\BulletinCalculationSlot;
use App\Domains\Bulletin\Enums\BulletinCalculationBlockerCode;
use App\Domains\Bulletin\Services\DeterministicBulletinCalculationService;
use App\Domains\Evaluation\Enums\NoteResultStatus;

function calculationSlot(
    int $nodeId,
    int $maximumMinorUnits = 2000,
    int $coefficientHundredths = 100,
    ?NoteResultStatus $status = NoteResultStatus::Saisie,
    ?int $valueMinorUnits = 1200,
    string $nodeType = 'MATIERE',
    ?string $key = null,
): BulletinCalculationSlot {
    return new BulletinCalculationSlot(
        key: $key ?? "node:{$nodeId}:period:3",
        nodeId: $nodeId,
        nodeType: $nodeType,
        maximumMinorUnits: $maximumMinorUnits,
        coefficientHundredths: $coefficientHundredths,
        status: $status,
        valueMinorUnits: $valueMinorUnits,
    );
}

/**
 * @param  list<BulletinCalculationSlot>  $slots
 */
function calculationInput(
    array $slots,
    bool $periodActive = true,
    bool $periodCalculable = true,
    bool $periodLocked = true,
    bool $configurationLocked = true,
): BulletinCalculationInput {
    return new BulletinCalculationInput(
        bulletinId: 7,
        periodId: 3,
        configurationHash: str_repeat('a', 64),
        periodActive: $periodActive,
        periodCalculable: $periodCalculable,
        periodLocked: $periodLocked,
        configurationLocked: $configurationLocked,
        slots: $slots,
    );
}

test('it calculates exact deterministic totals independently of slot order', function () {
    $service = new DeterministicBulletinCalculationService;
    $first = calculationSlot(
        nodeId: 1,
        maximumMinorUnits: 100,
        valueMinorUnits: 50,
    );
    $second = calculationSlot(
        nodeId: 2,
        maximumMinorUnits: 200,
        valueMinorUnits: 150,
    );

    $ordered = $service->calculate(calculationInput([$first, $second]));
    $reversed = $service->calculate(calculationInput([$second, $first]));

    expect($ordered->toArray())
        ->toBe($reversed->toArray())
        ->and($ordered->engineVersion)->toBe('1.0.0')
        ->and($ordered->calculable)->toBeTrue()
        ->and($ordered->publicationReady)->toBeTrue()
        ->and($ordered->totalObtained)->toBe('2.00')
        ->and($ordered->totalMaximum)->toBe('3.00')
        ->and($ordered->percentage)->toBe('66.67')
        ->and($ordered->blockers)->toBe([])
        ->and($ordered->inputHash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($ordered->resultHash)->toMatch('/^[a-f0-9]{64}$/');
});

test('lock readiness does not alter calculation hashes', function () {
    $service = new DeterministicBulletinCalculationService;
    $slot = calculationSlot(nodeId: 1);

    $ready = $service->calculate(calculationInput([$slot]));
    $unlocked = $service->calculate(calculationInput(
        [$slot],
        periodLocked: false,
        configurationLocked: false,
    ));

    expect($unlocked->calculable)->toBeTrue()
        ->and($unlocked->publicationReady)->toBeFalse()
        ->and($unlocked->inputHash)->toBe($ready->inputHash)
        ->and($unlocked->resultHash)->toBe($ready->resultHash);
});

test('it reports period blockers without inventing a result', function () {
    $result = (new DeterministicBulletinCalculationService)->calculate(
        calculationInput(
            [],
            periodActive: false,
            periodCalculable: false,
        ),
    );

    expect($result->calculable)->toBeFalse()
        ->and($result->publicationReady)->toBeFalse()
        ->and($result->totalObtained)->toBeNull()
        ->and($result->totalMaximum)->toBeNull()
        ->and($result->percentage)->toBeNull()
        ->and(array_column($result->blockers, 'code'))->toBe([
            BulletinCalculationBlockerCode::PeriodInactive->value,
            BulletinCalculationBlockerCode::PeriodNotCalculable->value,
            BulletinCalculationBlockerCode::NoSlots->value,
        ]);
});

test('it blocks every unsupported or incomplete slot explicitly', function () {
    $slots = [
        calculationSlot(nodeId: 1, nodeType: 'RUBRIQUE'),
        calculationSlot(nodeId: 2, maximumMinorUnits: 0),
        calculationSlot(nodeId: 3, coefficientHundredths: 150),
        calculationSlot(nodeId: 4, status: null, valueMinorUnits: null),
        calculationSlot(
            nodeId: 5,
            status: NoteResultStatus::Absente,
            valueMinorUnits: null,
        ),
        calculationSlot(nodeId: 6, valueMinorUnits: 2001),
        calculationSlot(nodeId: 7, key: 'duplicate'),
        calculationSlot(nodeId: 8, key: 'duplicate'),
    ];

    $result = (new DeterministicBulletinCalculationService)->calculate(
        calculationInput($slots),
    );
    $codes = array_column($result->blockers, 'code');

    expect($result->calculable)->toBeFalse()
        ->and($codes)->toContain(
            BulletinCalculationBlockerCode::UnsupportedNodeType->value,
            BulletinCalculationBlockerCode::InvalidMaximum->value,
            BulletinCalculationBlockerCode::UnsupportedCoefficient->value,
            BulletinCalculationBlockerCode::MissingResult->value,
            BulletinCalculationBlockerCode::UnsupportedResultStatus->value,
            BulletinCalculationBlockerCode::InvalidValue->value,
            BulletinCalculationBlockerCode::DuplicateSlot->value,
        );
});
