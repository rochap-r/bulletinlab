<?php

namespace App\Enums;

enum NoteOrigine: string
{
    case Directe = 'DIRECTE';
    case Evaluations = 'EVALUATIONS';

    public static function values(): array
    {
        return array_map(
            static fn (self $origine): string => $origine->value,
            self::cases(),
        );
    }
}
