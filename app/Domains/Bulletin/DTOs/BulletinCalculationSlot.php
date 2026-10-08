<?php

namespace App\Domains\Bulletin\DTOs;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use InvalidArgumentException;

final readonly class BulletinCalculationSlot
{
    public function __construct(
        public string $key,
        public int $nodeId,
        public string $nodeType,
        public int $maximumMinorUnits,
        public int $coefficientHundredths,
        public ?NoteResultStatus $status,
        public ?int $valueMinorUnits,
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'A calculation slot key cannot be empty.',
            );
        }

        if ($this->nodeId < 1) {
            throw new InvalidArgumentException(
                'A calculation slot node identifier must be positive.',
            );
        }
    }

    /**
     * @return array{
     *     key: string,
     *     node_id: int,
     *     node_type: string,
     *     maximum_minor_units: int,
     *     coefficient_hundredths: int,
     *     status: ?string,
     *     value_minor_units: ?int
     * }
     */
    public function canonicalPayload(): array
    {
        return [
            'key' => $this->key,
            'node_id' => $this->nodeId,
            'node_type' => $this->nodeType,
            'maximum_minor_units' => $this->maximumMinorUnits,
            'coefficient_hundredths' => $this->coefficientHundredths,
            'status' => $this->status?->value,
            'value_minor_units' => $this->valueMinorUnits,
        ];
    }
}
