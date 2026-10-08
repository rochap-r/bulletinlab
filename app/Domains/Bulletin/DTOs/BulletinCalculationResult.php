<?php

namespace App\Domains\Bulletin\DTOs;

final readonly class BulletinCalculationResult
{
    /**
     * @param  list<array{code: string, slot_key: ?string}>  $blockers
     */
    public function __construct(
        public string $engineVersion,
        public string $configurationHash,
        public string $inputHash,
        public string $resultHash,
        public bool $calculable,
        public bool $publicationReady,
        public ?string $totalObtained,
        public ?string $totalMaximum,
        public ?string $percentage,
        public array $blockers,
    ) {}

    /**
     * @return array{
     *     engine_version: string,
     *     configuration_hash: string,
     *     input_hash: string,
     *     result_hash: string,
     *     calculable: bool,
     *     publication_ready: bool,
     *     total_obtenu: ?string,
     *     total_maxima: ?string,
     *     pourcentage: ?string,
     *     blockers: list<array{code: string, slot_key: ?string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'engine_version' => $this->engineVersion,
            'configuration_hash' => $this->configurationHash,
            'input_hash' => $this->inputHash,
            'result_hash' => $this->resultHash,
            'calculable' => $this->calculable,
            'publication_ready' => $this->publicationReady,
            'total_obtenu' => $this->totalObtained,
            'total_maxima' => $this->totalMaximum,
            'pourcentage' => $this->percentage,
            'blockers' => $this->blockers,
        ];
    }
}
