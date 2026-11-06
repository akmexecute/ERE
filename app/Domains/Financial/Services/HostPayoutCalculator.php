<?php

declare(strict_types=1);

namespace App\Domains\Financial\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Financial\Enums\PayoutSchedule;
use App\Domains\Host\Models\Host;
use Carbon\CarbonImmutable;
use DomainException;

final class HostPayoutCalculator
{
    /**
     * Compute decimal-safe host payout waterfall:
     * Host Payable = Gross Eligible Revenue - Platform Deductions + Adjustments
     *
     * @return array{
     *     gross_revenue_etb: string,
     *     platform_deductions_etb: string,
     *     adjustments_amount_etb: string,
     *     net_payable_etb: string
     * }
     */
    public function calculateWaterfall(
        float|string $grossRevenue,
        float|string $platformDeduction,
        float|string $adjustments = 0.00
    ): array {
        $gross = number_format((float) $grossRevenue, 2, '.', '');
        $deductions = number_format((float) $platformDeduction, 2, '.', '');
        $adj = number_format((float) $adjustments, 2, '.', '');

        if (bccomp($gross, '0.00', 2) < 0 || bccomp($deductions, '0.00', 2) < 0) {
            throw new DomainException('Gross revenue and deductions must be non-negative values.');
        }

        // Subtotal = Gross - Deductions
        $subtotal = bcsub($gross, $deductions, 2);
        
        // Net = Subtotal + Adjustments (adjustments can be negative deductions or positive additions)
        $net = bcadd($subtotal, $adj, 2);

        if (bccomp($net, '0.00', 2) < 0) {
            throw new DomainException("Host payable waterfall cannot produce a negative net payable: [{$net} ETB].");
        }

        return [
            'gross_revenue_etb' => $gross,
            'platform_deductions_etb' => $deductions,
            'adjustments_amount_etb' => $adj,
            'net_payable_etb' => $net,
        ];
    }

    /**
     * Calculate scheduled release date for a host payout based on schedule type and booking dates.
     */
    public function resolveScheduledReleaseDate(
        PayoutSchedule $schedule,
        ?Booking $booking = null,
        ?CarbonImmutable $referenceTime = null
    ): CarbonImmutable {
        $ref = $referenceTime ?? CarbonImmutable::now('Africa/Addis_Ababa');

        return match ($schedule) {
            PayoutSchedule::Immediately => $ref,

            PayoutSchedule::AfterCheckin => $booking && $booking->check_in_date
                ? CarbonImmutable::parse($booking->check_in_date, 'Africa/Addis_Ababa')->addHours(24)
                : $ref->addHours(24),

            PayoutSchedule::AfterCheckout => $booking && $booking->check_out_date
                ? CarbonImmutable::parse($booking->check_out_date, 'Africa/Addis_Ababa')->addHours(24)
                : $ref->addDays(2),

            PayoutSchedule::Weekly => $ref->isFriday()
                ? $ref->setTime(17, 0, 0)
                : $ref->next(CarbonImmutable::FRIDAY)->setTime(17, 0, 0),

            PayoutSchedule::Monthly => $ref->addMonth()->startOfMonth()->setTime(10, 0, 0),
        };
    }
}

