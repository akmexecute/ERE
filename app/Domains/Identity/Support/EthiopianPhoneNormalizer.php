<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use InvalidArgumentException;

final class EthiopianPhoneNormalizer
{
    /**
     * Normalizes Ethiopian mobile numbers to strict E.164 format (+2519XXXXXXXX or +2517XXXXXXXX).
     */
    public static function normalize(string $rawPhone): string
    {
        $cleaned = preg_replace('/[\s\-\(\)]+/', '', trim($rawPhone)) ?? '';

        if (str_starts_with($cleaned, '00251')) {
            $cleaned = '+251'.substr($cleaned, 5);
        } elseif (str_starts_with($cleaned, '251') && strlen($cleaned) === 12) {
            $cleaned = '+'.$cleaned;
        } elseif (preg_match('/^0([79]\d{8})$/', $cleaned, $matches) === 1) {
            $cleaned = '+251'.$matches[1];
        } elseif (preg_match('/^([79]\d{8})$/', $cleaned, $matches) === 1) {
            $cleaned = '+251'.$matches[1];
        }

        if (preg_match('/^\+251[79]\d{8}$/', $cleaned) !== 1) {
            throw new InvalidArgumentException(
                'Please enter a valid Ethiopian mobile number starting with 09, 07, +2519, or +2517.'
            );
        }

        return $cleaned;
    }
}
