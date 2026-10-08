<?php

namespace App\Domains\Bulletin\DTOs;

use InvalidArgumentException;

final readonly class BulletinCalculationInput
{
    /**
     * @param  list<BulletinCalculationSlot>  $slots
     */
    public function __construct(
        public int $bulletinId,
        public int $periodId,
        public string $configurationHash,
        public bool $periodActive,
        public bool $periodCalculable,
        public bool $periodLocked,
        public bool $configurationLocked,
        public array $slots,
    ) {
        if ($this->bulletinId < 1 || $this->periodId < 1) {
            throw new InvalidArgumentException(
                'Calculation identifiers must be positive.',
            );
        }

        if (preg_match('/^[a-f0-9]{64}$/', $this->configurationHash) !== 1) {
            throw new InvalidArgumentException(
                'The configuration hash must be a lowercase SHA-256 hash.',
            );
        }

        foreach ($this->slots as $slot) {
            if (! $slot instanceof BulletinCalculationSlot) {
                throw new InvalidArgumentException(
                    'Every calculation slot must use the calculation slot DTO.',
                );
            }
        }
    }

    /**
     * @return list<BulletinCalculationSlot>
     */
    public function sortedSlots(): array
    {
        $slots = $this->slots;
        usort(
            $slots,
            static fn (
                BulletinCalculationSlot $left,
                BulletinCalculationSlot $right,
            ): int => [$left->key, $left->nodeId]
                <=> [$right->key, $right->nodeId],
        );

        return $slots;
    }

    /**
     * @return array{
     *     bulletin_id: int,
     *     periode_id: int,
     *     configuration_hash: string,
     *     periode_active: bool,
     *     periode_calculable: bool,
     *     slots: list<array<string, int|string|null>>
     * }
     */
    public function canonicalCalculationPayload(): array
    {
        return [
            'bulletin_id' => $this->bulletinId,
            'periode_id' => $this->periodId,
            'configuration_hash' => $this->configurationHash,
            'periode_active' => $this->periodActive,
            'periode_calculable' => $this->periodCalculable,
            'slots' => array_map(
                static fn (BulletinCalculationSlot $slot): array => $slot
                    ->canonicalPayload(),
                $this->sortedSlots(),
            ),
        ];
    }
}
