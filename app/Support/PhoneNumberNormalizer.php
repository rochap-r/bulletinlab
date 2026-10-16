<?php

namespace App\Support;

use InvalidArgumentException;

final class PhoneNumberNormalizer
{
    public static function normalize(string $phoneNumber): string
    {
        $normalized = preg_replace('/[\s\p{Z}().\/-]+/u', '', trim($phoneNumber));

        if (! is_string($normalized)) {
            throw new InvalidArgumentException('Le numéro de téléphone est invalide.');
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        } elseif (preg_match('/^0\d{9}$/', $normalized) === 1) {
            // Numéro national congolais : 0 suivi de 9 chiffres.
            $normalized = '+243'.substr($normalized, 1);
        } elseif (preg_match('/^243\d{9}$/', $normalized) === 1) {
            // Indicatif sans +.
            $normalized = '+'.$normalized;
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Le numéro de téléphone doit respecter le format E.164.');
        }

        return $normalized;
    }
}
