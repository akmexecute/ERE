<?php

declare(strict_types=1);

namespace App\Domains\Booking\Actions;

use Carbon\CarbonImmutable;

final readonly class CalculateCancellationRefundAction
{
    /**
     * @return array{
     *   policy: string,
     *   hours_until_checkin: int,
     *   accommodation_refund_pct: float,
     *   cleaning_fee_refund_etb: float,
     *   accommodation_refund_etb: float,
     *   total_guest_refund_etb: float,
     *   host_retained_etb: float
     * }
     */
    public function execute(
        string $policy,
        string $checkInDate,
        float $accommodationNetSubtotalEtb,
        float $cleaningFeeEtb,
        CarbonImmutable|string|null $cancelledAt = null
    ): array {
        $now = $cancelledAt instanceof CarbonImmutable
            ? $cancelledAt
            : CarbonImmutable::parse($cancelledAt ?? 'now', 'Africa/Addis_Ababa');

        // Standard check-in at 14:00 East Africa Time
        $checkInMoment = CarbonImmutable::parse($checkInDate.' 14:00:00', 'Africa/Addis_Ababa');
        $hoursUntilCheckIn = (int) round($now->diffInHours($checkInMoment, false));

        // Cleaning fee is always 100% refunded if cancelled before check-in
        $cleaningRefund = $hoursUntilCheckIn > 0 ? $cleaningFeeEtb : 0.0;

        $refundPct = match ($policy) {
            // Flexible: 100% accommodation refund if cancelled >= 24 hours before check-in; 50% if < 24h
            'flexible' => $hoursUntilCheckIn >= 24 ? 100.0 : ($hoursUntilCheckIn > 0 ? 50.0 : 0.0),

            // Moderate: 100% refund if >= 5 days (120h) before check-in; 50% if 24h..119h
            'moderate' => $hoursUntilCheckIn >= 120 ? 100.0 : ($hoursUntilCheckIn >= 24 ? 50.0 : 0.0),

            // Strict: 100% refund if >= 14 days (336h); 50% if >= 7 days (168h); 0% within 7 days
            'strict' => $hoursUntilCheckIn >= 336 ? 100.0 : ($hoursUntilCheckIn >= 168 ? 50.0 : 0.0),

            default => $hoursUntilCheckIn >= 24 ? 100.0 : 0.0,
        };

        $accommodationRefund = round($accommodationNetSubtotalEtb * ($refundPct / 100.0), 2);
        $totalGuestRefund = round($accommodationRefund + $cleaningRefund, 2);
        $hostRetained = round(($accommodationNetSubtotalEtb + $cleaningFeeEtb) - $totalGuestRefund, 2);

        return [
            'policy' => $policy,
            'hours_until_checkin' => $hoursUntilCheckIn,
            'accommodation_refund_pct' => $refundPct,
            'cleaning_fee_refund_etb' => $cleaningRefund,
            'accommodation_refund_etb' => $accommodationRefund,
            'total_guest_refund_etb' => $totalGuestRefund,
            'host_retained_etb' => $hostRetained,
        ];
    }
}
