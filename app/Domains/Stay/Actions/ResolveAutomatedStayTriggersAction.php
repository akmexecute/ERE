<?php

declare(strict_types=1);

namespace App\Domains\Stay\Actions;

use Carbon\CarbonImmutable;

final readonly class ResolveAutomatedStayTriggersAction
{
    /**
     * @param  array<int, string>  $alreadySentTriggers
     * @return array<int, string>
     */
    public function execute(
        string $bookingStatus,
        string $checkInDate,
        string $checkOutDate,
        string $referenceDate,
        array $alreadySentTriggers = []
    ): array {
        $due = [];
        $today = CarbonImmutable::parse($referenceDate)->startOfDay();
        $checkIn = CarbonImmutable::parse($checkInDate)->startOfDay();
        $checkOut = CarbonImmutable::parse($checkOutDate)->startOfDay();

        $daysUntilCheckIn = (int) $today->diffInDays($checkIn, false);
        $daysSinceCheckOut = (int) $checkOut->diffInDays($today, false);

        if ($bookingStatus === 'confirmed') {
            if (! in_array('booking_confirmation', $alreadySentTriggers, true)) {
                $due[] = 'booking_confirmation';
            }
            if ($daysUntilCheckIn === 7 && ! in_array('days_before_7', $alreadySentTriggers, true)) {
                $due[] = 'days_before_7';
            }
            if ($daysUntilCheckIn === 3 && ! in_array('days_before_3', $alreadySentTriggers, true)) {
                $due[] = 'days_before_3';
            }
            if ($daysUntilCheckIn === 1 && ! in_array('days_before_1', $alreadySentTriggers, true)) {
                $due[] = 'days_before_1';
            }
            if ($daysUntilCheckIn === 0 && ! in_array('check_in_day', $alreadySentTriggers, true)) {
                $due[] = 'check_in_day';
            }
        }

        if (in_array($bookingStatus, ['checked_in', 'active'], true) && $today->equalTo($checkOut)) {
            if (! in_array('checkout_day', $alreadySentTriggers, true)) {
                $due[] = 'checkout_day';
            }
        }

        if (in_array($bookingStatus, ['checked_out', 'completed'], true) && $daysSinceCheckOut >= 0 && $daysSinceCheckOut <= 3) {
            if (! in_array('review_request', $alreadySentTriggers, true)) {
                $due[] = 'review_request';
            }
        }

        return $due;
    }
}
