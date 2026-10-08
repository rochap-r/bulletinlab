<?php

namespace App\Domains\Bulletin\DTOs;

final readonly class PublishBulletinResult
{
    public function __construct(
        public string $configurationHash,
        public string $inputHash,
        public string $resultHash,
        public ?string $totalObtained,
        public ?string $totalMaximum,
        public ?string $percentage,
        public ?string $mention,
        public ?string $decision,
        public ?int $rank,
        public int $classSize,
        public int $publicationId,
        public int $snapshotVersion,
        public string $publicationStatus,
    ) {}

    /**
     * @return array{
     *     configuration_hash: string,
     *     input_hash: string,
     *     result_hash: string,
     *     total_obtenu: ?string,
     *     total_maxima: ?string,
     *     pourcentage: ?string,
     *     mention: ?string,
     *     decision: ?string,
     *     rang: ?int,
     *     nombre_eleves_classe: int,
     *     publication_id: int,
     *     version: int,
     *     publication_statut: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'configuration_hash' => $this->configurationHash,
            'input_hash' => $this->inputHash,
            'result_hash' => $this->resultHash,
            'total_obtenu' => $this->totalObtained,
            'total_maxima' => $this->totalMaximum,
            'pourcentage' => $this->percentage,
            'mention' => $this->mention,
            'decision' => $this->decision,
            'rang' => $this->rank,
            'nombre_eleves_classe' => $this->classSize,
            'publication_id' => $this->publicationId,
            'version' => $this->snapshotVersion,
            'publication_statut' => $this->publicationStatus,
        ];
    }
}
