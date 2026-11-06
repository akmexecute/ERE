<?php

declare(strict_types=1);

namespace App\Domains\Stay\Services;

use InvalidArgumentException;

final class SecurityDepositCalculator
{
    public const DEFAULT_MIN_DEPOSIT_ETB = 1000.0;
    public const DEFAULT_MAX_DEPOSIT_ETB = 50000.0;

    /**
     * Compute a transparent, category-aware default security deposit in ETB.
     *
     * Rules:
     * - Entire villas / luxury places: 50% of 1 night + 300 ETB per night, minimum 3,000 ETB.
     * - Standard apartments / serviced suites: 40% of 1 night + 150 ETB per night, minimum 2,000 ETB.
     * - Rooms / shared units: 25% of 1 night + 100 ETB per night, minimum 1,000 ETB.
     * - Clamped between safe minimum and maximum limits, rounded to nearest 100 ETB.
     *
     * @param string $accommodationModel e.g. 'entire_place', 'hotel_rooms', 'private_room'
     * @param float $nightlyRateEtb
     * @param int $nightsCount
     * @param float|null $hostCustomDepositEtb
     */
    public static function calculate(
        string $accommodationModel,
        float $nightlyRateEtb,
        int $nightsCount = 1,
        ?float $hostCustomDepositEtb = null
    ): float {
        // If host provided a custom override, validate it stays within permitted platform guardrails
        if ($hostCustomDepositEtb !== null && $hostCustomDepositEtb > 0) {
            return round(min(self::DEFAULT_MAX_DEPOSIT_ETB, max(self::DEFAULT_MIN_DEPOSIT_ETB, $hostCustomDepositEtb)), 2);
        }

        $rate = max(500.0, $nightlyRateEtb);
        $nights = max(1, $nightsCount);

        [$multiplier, $perNightAddition, $floorMin] = match ($accommodationModel) {
            'entire_place', 'villa' => [0.50, 300.0, 3000.0],
            'hotel_rooms', 'serviced_apt' => [0.40, 150.0, 2000.0],
            default => [0.25, 100.0, 1000.0],
        };

        $computed = ($rate * $multiplier) + ($nights * $perNightAddition);
        $rounded = round(max($floorMin, min(self::DEFAULT_MAX_DEPOSIT_ETB, $computed)) / 100) * 100;

        return (float) $rounded;
    }
}

