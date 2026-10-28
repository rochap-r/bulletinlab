<?php

namespace App\Enums;

enum StatutEvaluation: string
{
    case Brouillon = 'BROUILLON';
    case Publiee = 'PUBLIEE';
    case Verrouillee = 'VERROUILLEE';
    case Annulee = 'ANNULEE';

    public static function values(): array
    {
        return array_map(
            static fn (self $statut): string => $statut->value,
            self::cases(),
        );
    }

    public function isModifiable(): bool
    {
        return $this !== self::Verrouillee && $this !== self::Annulee;
    }
}
