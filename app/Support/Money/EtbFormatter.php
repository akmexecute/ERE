<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;

final class EtbFormatter
{
    /**
     * Format full amount: e.g. "ETB 25,000,000" or "ETB 66,666.67".
     */
    public static function format(float|int|string $amount, bool $withDecimals = false): string
    {
        if (! is_numeric($amount)) {
            return 'ETB 0.00';
        }

        $numeric = (float) $amount;
        $decimals = $withDecimals ? 2 : 0;

        $prefix = $numeric < 0 ? '-ETB ' : 'ETB ';

        return $prefix . number_format(abs($numeric), $decimals, '.', ',');
    }

    /**
     * Format compact map pin badge: e.g. 25000000 -> "ETB 25M", 80000 -> "ETB 80K", 1500000000 -> "ETB 1.5B".
     */
    public static function compact(float|int|string $amount): string
    {
        if (! is_numeric($amount)) {
            return 'ETB 0';
        }

        $numeric = (float) $amount;
        $prefix = $numeric < 0 ? '-ETB ' : 'ETB ';
        $abs = abs($numeric);

        if ($abs >= 1_000_000_000) {
            return $prefix . rtrim(rtrim(number_format($abs / 1_000_000_000, 2), '0'), '.') . 'B';
        }

        if ($abs >= 1_000_000) {
            return $prefix . rtrim(rtrim(number_format($abs / 1_000_000, 2), '0'), '.') . 'M';
        }

        if ($abs >= 1_000) {
            return $prefix . rtrim(rtrim(number_format($abs / 1_000, 1), '0'), '.') . 'K';
        }

        return self::format($numeric);
    }

    /**
     * SRS Section 36: Price per m² calculation (e.g. 20,000,000 / 300 = 66,666.67).
     * Strictly validates positive area before division with decimal-safe precision.
     */
    public static function calculatePricePerSqm(float|int|string|null $priceEtb, float|int|string|null $areaSqm): ?float
    {
        if ($priceEtb === null || $areaSqm === null || ! is_numeric($priceEtb) || ! is_numeric($areaSqm)) {
            return null;
        }

        $price = (float) $priceEtb;
        $area = (float) $areaSqm;

        // Area must be strictly positive to prevent division by zero or negative area distortion
        if ($area <= 0.0 || $price < 0.0) {
            return null;
        }

        if (extension_loaded('bcmath')) {
            $quotient = bcdiv(sprintf('%.4f', $price), sprintf('%.4f', $area), 4);
            return round((float) $quotient, 2);
        }

        return round($price / $area, 2);
    }

    /**
     * Convert ETB currency to integer cents (minor units) to prevent binary floating-point drift.
     */
    public static function toMinorUnits(float|int|string $amountEtb): int
    {
        if (! is_numeric($amountEtb)) {
            throw new InvalidArgumentException("Amount must be numeric.");
        }

        if (extension_loaded('bcmath')) {
            return (int) bcmul(sprintf('%.2f', (float) $amountEtb), '100', 0);
        }

        return (int) round(((float) $amountEtb) * 100);
    }

    /**
     * Convert integer cents back to exact decimal ETB.
     */
    public static function fromMinorUnits(int $cents): float
    {
        if (extension_loaded('bcmath')) {
            return (float) bcdiv((string) $cents, '100', 2);
        }

        return round($cents / 100.0, 2);
    }
}
