<?php

namespace App\Domains\Evaluation\Enums;

enum NoteResultStatus: string
{
    case Saisie = 'SAISIE';
    case Absente = 'ABSENTE';
    case Dispensee = 'DISPENSEE';
    case Annulee = 'ANNULEE';

    public function isTeacherEditable(): bool
    {
        return $this !== self::Annulee;
    }

    public function countsTowardProgress(?string $value): bool
    {
        return match ($this) {
            self::Saisie => $value !== null,
            self::Absente,
            self::Dispensee => true,
            self::Annulee => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function teacherEditableValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            array_filter(
                self::cases(),
                static fn (self $status): bool => $status->isTeacherEditable(),
            ),
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
