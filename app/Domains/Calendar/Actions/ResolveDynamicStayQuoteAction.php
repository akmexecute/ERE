<?php

declare(strict_types=1);

namespace App\Domains\Calendar\Actions;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ResolveDynamicStayQuoteAction
{
    /**
     * @param  array{
     *   base_nightly_rate_etb: float|int,
     *   weekend_nightly_rate_etb?: float|int|null,
     *   weekly_discount_pct?: float|int,
     *   monthly_discount_pct?: float|int,
     *   cleaning_fee_etb?: float|int,
     *   security_deposit_etb?: float|int,
     *   guest_service_fee_pct?: float|int,
     *   tourism_tax_pct?: float|int,
     *   min_stay_nights?: int,
     *   max_stay_nights?: int
     * }  $profile
     * @param  array<string, array{status?: string, custom_nightly_rate_etb?: float|null, min_stay_override_nights?: int|null}>  $calendarOverridesByDate
     * @param  array<int, array{start_date: string, end_date: string, nightly_rate_etb?: float|null, rate_multiplier?: float, min_stay_nights?: int|null}>  $seasonalRules
     * @return array{
     *   nights_count: int,
     *   effective_min_stay_nights: int,
     *   nightly_breakdown: array<string, float>,
     *   accommodation_raw_subtotal_etb: float,
     *   discount_type: ?string,
     *   discount_amount_etb: float,
     *   accommodation_net_subtotal_etb: float,
     *   cleaning_fee_etb: float,
     *   platform_fee_etb: float,
     *   tax_amount_etb: float,
     *   security_deposit_etb: float,
     *   total_amount_etb: float
     * }
     */
    public function execute(
        string $checkInDate,
        string $checkOutDate,
        array $profile,
        array $calendarOverridesByDate = [],
        array $seasonalRules = []
    ): array {
        $start = CarbonImmutable::parse($checkInDate)->startOfDay();
        $end = CarbonImmutable::parse($checkOutDate)->startOfDay();

        $nightsCount = (int) $start->diffInDays($end, false);
        if ($nightsCount <= 0) {
            throw new InvalidArgumentException('Check-out date must be after check-in date.');
        }

        $baseRate = (float) $profile['base_nightly_rate_etb'];
        $weekendRate = isset($profile['weekend_nightly_rate_etb']) && $profile['weekend_nightly_rate_etb'] !== null
            ? (float) $profile['weekend_nightly_rate_etb']
            : $baseRate;

        $effectiveMinStay = (int) ($profile['min_stay_nights'] ?? 1);
        $maxStay = (int) ($profile['max_stay_nights'] ?? 90);

        $nightlyBreakdown = [];
        $cursor = $start;

        for ($i = 0; $i < $nightsCount; $i++) {
            $dateKey = $cursor->toDateString();
            $dayOverride = $calendarOverridesByDate[$dateKey] ?? null;

            if ($dayOverride && isset($dayOverride['status']) && $dayOverride['status'] !== 'available') {
                throw new InvalidArgumentException("Selected date {$dateKey} is unavailable ({$dayOverride['status']}).");
            }

            // Priority 1: Explicit Host Date Override
            if ($dayOverride && ! empty($dayOverride['custom_nightly_rate_etb'])) {
                $rate = (float) $dayOverride['custom_nightly_rate_etb'];
            } else {
                // Priority 2: Seasonal / Ethiopian Holiday Rule
                $matchedRule = null;
                foreach ($seasonalRules as $rule) {
                    if ($dateKey >= $rule['start_date'] && $dateKey <= $rule['end_date']) {
                        $matchedRule = $rule;
                        break;
                    }
                }

                if ($matchedRule) {
                    $rate = ! empty($matchedRule['nightly_rate_etb'])
                        ? (float) $matchedRule['nightly_rate_etb']
                        : round($baseRate * (float) ($matchedRule['rate_multiplier'] ?? 1.0), 2);

                    if (! empty($matchedRule['min_stay_nights'])) {
                        $effectiveMinStay = max($effectiveMinStay, (int) $matchedRule['min_stay_nights']);
                    }
                } else {
                    // Priority 3: Weekend (Friday = 5, Saturday = 6) vs Weekday
                    $isWeekendNight = in_array($cursor->dayOfWeekIso, [5, 6], true);
                    $rate = $isWeekendNight ? $weekendRate : $baseRate;
                }
            }

            if ($dayOverride && ! empty($dayOverride['min_stay_override_nights'])) {
                $effectiveMinStay = max($effectiveMinStay, (int) $dayOverride['min_stay_override_nights']);
            }

            $nightlyBreakdown[$dateKey] = round($rate, 2);
            $cursor = $cursor->addDay();
        }

        // Enforce SRS Section 52 Minimum & Maximum Stay
        if ($nightsCount < $effectiveMinStay) {
            throw new InvalidArgumentException(
                "This stay requires a minimum of {$effectiveMinStay} nights for the selected dates."
            );
        }

        if ($nightsCount > $maxStay) {
            throw new InvalidArgumentException(
                "Maximum allowed stay is {$maxStay} nights."
            );
        }

        $rawSubtotal = round(array_sum($nightlyBreakdown), 2);

        // Long-stay discount (SRS Section 51: Monthly 28+ nights or Weekly 7+ nights)
        $discountType = null;
        $discountAmount = 0.0;
        $monthlyPct = (float) ($profile['monthly_discount_pct'] ?? 0);
        $weeklyPct = (float) ($profile['weekly_discount_pct'] ?? 0);

        if ($nightsCount >= 28 && $monthlyPct > 0) {
            $discountType = 'monthly_discount';
            $discountAmount = round($rawSubtotal * ($monthlyPct / 100), 2);
        } elseif ($nightsCount >= 7 && $weeklyPct > 0) {
            $discountType = 'weekly_discount';
            $discountAmount = round($rawSubtotal * ($weeklyPct / 100), 2);
        }

        $netSubtotal = round($rawSubtotal - $discountAmount, 2);
        $cleaningFee = round((float) ($profile['cleaning_fee_etb'] ?? 0), 2);

        $serviceFeePct = (float) ($profile['guest_service_fee_pct'] ?? 0);
        $platformFee = round(($netSubtotal + $cleaningFee) * ($serviceFeePct / 100), 2);

        $taxPct = (float) ($profile['tourism_tax_pct'] ?? 0);
        $taxAmount = round(($netSubtotal + $cleaningFee + $platformFee) * ($taxPct / 100), 2);

        $securityDeposit = round((float) ($profile['security_deposit_etb'] ?? 0), 2);
        $totalAmount = round($netSubtotal + $cleaningFee + $platformFee + $taxAmount, 2);

        return [
            'nights_count' => $nightsCount,
            'effective_min_stay_nights' => $effectiveMinStay,
            'nightly_breakdown' => $nightlyBreakdown,
            'accommodation_raw_subtotal_etb' => $rawSubtotal,
            'discount_type' => $discountType,
            'discount_amount_etb' => $discountAmount,
            'accommodation_net_subtotal_etb' => $netSubtotal,
            'cleaning_fee_etb' => $cleaningFee,
            'platform_fee_etb' => $platformFee,
            'tax_amount_etb' => $taxAmount,
            'security_deposit_etb' => $securityDeposit,
            'total_amount_etb' => $totalAmount,
        ];
    }
}
