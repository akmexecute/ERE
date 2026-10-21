<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Services;

final class MarketAnalyticsCalculator
{
    public const string DATA_BASIS_ASKING_PRICE = 'platform_asking_price_indicator';

    /**
     * @param  array<int, array{price_etb: float|int, price_per_sqm_etb?: float|int|null}>  $listings
     * @return array{
     *   count: int,
     *   avg_asking_price_etb: ?float,
     *   median_asking_price_etb: ?float,
     *   avg_price_per_sqm_etb: ?float,
     *   data_basis: string,
     *   disclosure_notice: string
     * }
     */
    public static function summarizeListings(array $listings): array
    {
        $count = count($listings);
        if ($count === 0) {
            return [
                'count' => 0,
                'avg_asking_price_etb' => null,
                'median_asking_price_etb' => null,
                'avg_price_per_sqm_etb' => null,
                'data_basis' => self::DATA_BASIS_ASKING_PRICE,
                'disclosure_notice' => self::disclosureNotice(),
            ];
        }

        $prices = array_map(static fn (array $r): float => (float) $r['price_etb'], $listings);
        sort($prices);

        $avgPrice = round(array_sum($prices) / $count, 2);

        $mid = intdiv($count, 2);
        $medianPrice = ($count % 2 === 0)
            ? round(($prices[$mid - 1] + $prices[$mid]) / 2, 2)
            : round($prices[$mid], 2);

        $sqmValues = [];
        foreach ($listings as $r) {
            if (! empty($r['price_per_sqm_etb']) && (float) $r['price_per_sqm_etb'] > 0) {
                $sqmValues[] = (float) $r['price_per_sqm_etb'];
            }
        }

        $avgSqm = $sqmValues !== []
            ? round(array_sum($sqmValues) / count($sqmValues), 2)
            : null;

        return [
            'count' => $count,
            'avg_asking_price_etb' => $avgPrice,
            'median_asking_price_etb' => $medianPrice,
            'avg_price_per_sqm_etb' => $avgSqm,
            'data_basis' => self::DATA_BASIS_ASKING_PRICE,
            'disclosure_notice' => self::disclosureNotice(),
        ];
    }

    public static function disclosureNotice(): string
    {
        return 'Platform-Derived Market Indicator: Metrics reflect active asking prices published on the platform and do not represent official government transaction registry valuations (SRS Sections 37 & 127).';
    }
}
