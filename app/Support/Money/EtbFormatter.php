<?php

declare(strict_types=1);

namespace App\Support\Money;

final class EtbFormatter
{
    /**
     * Format full amount: e.g. "ETB 25,000,000" or "ETB 66,666.67".
     */
    public static function format(float|int|string $amount, bool $withDecimals = false): string
    {
        $numeric = (float) $amount;
        $decimals = $withDecimals ? 2 : 0;

        return 'ETB '.number_format($numeric, $decimals, '.', ',');
    }

    /**
     * Format compact map pin badge: e.g. 25000000 -> "ETB 25M", 80000 -> "ETB 80K".
     */
    public static function compact(float|int|string $amount): string
    {
        $numeric = (float) $amount;

        if ($numeric >= 1_000_000_000) {
            return 'ETB '.rtrim(rtrim(number_format($numeric / 1_000_000_000, 2), '0'), '.').'B';
        }

        if ($numeric >= 1_000_000) {
            return 'ETB '.rtrim(rtrim(number_format($numeric / 1_000_000, 2), '0'), '.').'M';
        }

        if ($numeric >= 1_000) {
            return 'ETB '.rtrim(rtrim(number_format($numeric / 1_000, 1), '0'), '.').'K';
        }

        return self::format($numeric);
    }

    /**
     * SRS Section 36: Price per m² calculation (e.g. 20,000,000 / 300 = 66,666.67).
     */
    public static function calculatePricePerSqm(float|int|string $priceEtb, float|int|string|null $areaSqm): ?float
    {
        $area = (float) ($areaSqm ?? 0);
        if ($area <= 0.0) {
            return null;
        }

        return round(((float) $priceEtb) / $area, 2);
    }
}
