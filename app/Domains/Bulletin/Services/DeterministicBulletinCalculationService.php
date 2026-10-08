<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Contracts\BulletinCalculationServiceInterface;
use App\Domains\Bulletin\DTOs\BulletinCalculationInput;
use App\Domains\Bulletin\DTOs\BulletinCalculationResult;
use App\Domains\Bulletin\DTOs\BulletinCalculationSlot;
use App\Domains\Bulletin\Enums\BulletinCalculationBlockerCode;
use App\Domains\Evaluation\Enums\NoteResultStatus;
use JsonException;
use OverflowException;

final class DeterministicBulletinCalculationService implements BulletinCalculationServiceInterface
{
    private const string ENGINE_VERSION = '1.0.0';

    public function calculate(
        BulletinCalculationInput $input,
    ): BulletinCalculationResult {
        $inputHash = $this->hash(
            $input->canonicalCalculationPayload(),
        );
        $blockers = $this->periodBlockers($input);
        $seenSlotKeys = [];
        $totalObtainedMinorUnits = 0;
        $totalMaximumMinorUnits = 0;

        foreach ($input->sortedSlots() as $slot) {
            if (isset($seenSlotKeys[$slot->key])) {
                $blockers[] = $this->blocker(
                    BulletinCalculationBlockerCode::DuplicateSlot,
                    $slot,
                );

                continue;
            }

            $seenSlotKeys[$slot->key] = true;
            $slotBlockers = $this->slotBlockers($slot);

            if ($slotBlockers !== []) {
                array_push($blockers, ...$slotBlockers);

                continue;
            }

            $this->assertAdditionWillNotOverflow(
                $totalObtainedMinorUnits,
                $slot->valueMinorUnits ?? 0,
            );
            $this->assertAdditionWillNotOverflow(
                $totalMaximumMinorUnits,
                $slot->maximumMinorUnits,
            );

            $totalObtainedMinorUnits += $slot->valueMinorUnits ?? 0;
            $totalMaximumMinorUnits += $slot->maximumMinorUnits;
        }

        $calculable = $blockers === []
            && $totalMaximumMinorUnits > 0;
        $totalObtained = $calculable
            ? $this->formatMinorUnits($totalObtainedMinorUnits)
            : null;
        $totalMaximum = $calculable
            ? $this->formatMinorUnits($totalMaximumMinorUnits)
            : null;
        $percentage = $calculable
            ? $this->formatMinorUnits(
                $this->percentageHundredths(
                    $totalObtainedMinorUnits,
                    $totalMaximumMinorUnits,
                ),
            )
            : null;
        $resultHash = $this->hash([
            'engine_version' => self::ENGINE_VERSION,
            'configuration_hash' => $input->configurationHash,
            'input_hash' => $inputHash,
            'calculable' => $calculable,
            'total_obtenu' => $totalObtained,
            'total_maxima' => $totalMaximum,
            'pourcentage' => $percentage,
            'blockers' => $blockers,
        ]);

        return new BulletinCalculationResult(
            engineVersion: self::ENGINE_VERSION,
            configurationHash: $input->configurationHash,
            inputHash: $inputHash,
            resultHash: $resultHash,
            calculable: $calculable,
            publicationReady: $calculable
                && $input->periodLocked
                && $input->configurationLocked,
            totalObtained: $totalObtained,
            totalMaximum: $totalMaximum,
            percentage: $percentage,
            blockers: $blockers,
        );
    }

    public function version(): string
    {
        return self::ENGINE_VERSION;
    }

    /**
     * @return list<array{code: string, slot_key: ?string}>
     */
    private function periodBlockers(
        BulletinCalculationInput $input,
    ): array {
        $blockers = [];

        if (! $input->periodActive) {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::PeriodInactive,
            );
        }

        if (! $input->periodCalculable) {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::PeriodNotCalculable,
            );
        }

        if ($input->slots === []) {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::NoSlots,
            );
        }

        return $blockers;
    }

    /**
     * @return list<array{code: string, slot_key: ?string}>
     */
    private function slotBlockers(
        BulletinCalculationSlot $slot,
    ): array {
        $blockers = [];

        if ($slot->nodeType !== 'MATIERE') {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::UnsupportedNodeType,
                $slot,
            );
        }

        if ($slot->maximumMinorUnits <= 0) {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::InvalidMaximum,
                $slot,
            );
        }

        if ($slot->coefficientHundredths !== 100) {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::UnsupportedCoefficient,
                $slot,
            );
        }

        if ($slot->status === null) {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::MissingResult,
                $slot,
            );
        } elseif ($slot->status !== NoteResultStatus::Saisie) {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::UnsupportedResultStatus,
                $slot,
            );
        } elseif ($slot->valueMinorUnits === null
            || $slot->valueMinorUnits < 0
            || $slot->valueMinorUnits > $slot->maximumMinorUnits) {
            $blockers[] = $this->blocker(
                BulletinCalculationBlockerCode::InvalidValue,
                $slot,
            );
        }

        return $blockers;
    }

    /**
     * @return array{code: string, slot_key: ?string}
     */
    private function blocker(
        BulletinCalculationBlockerCode $code,
        ?BulletinCalculationSlot $slot = null,
    ): array {
        return [
            'code' => $code->value,
            'slot_key' => $slot?->key,
        ];
    }

    private function percentageHundredths(
        int $obtainedMinorUnits,
        int $maximumMinorUnits,
    ): int {
        if ($maximumMinorUnits > intdiv(PHP_INT_MAX, 10001)) {
            throw new OverflowException(
                'The calculation total is too large.',
            );
        }

        return intdiv(
            ($obtainedMinorUnits * 10000)
                + intdiv($maximumMinorUnits, 2),
            $maximumMinorUnits,
        );
    }

    private function formatMinorUnits(int $minorUnits): string
    {
        return sprintf(
            '%d.%02d',
            intdiv($minorUnits, 100),
            $minorUnits % 100,
        );
    }

    private function assertAdditionWillNotOverflow(
        int $current,
        int $increment,
    ): void {
        if ($increment > PHP_INT_MAX - $current) {
            throw new OverflowException(
                'The calculation total is too large.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        try {
            return hash(
                'sha256',
                json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
            );
        } catch (JsonException $exception) {
            throw new OverflowException(
                'The calculation payload cannot be canonicalized.',
                previous: $exception,
            );
        }
    }
}
