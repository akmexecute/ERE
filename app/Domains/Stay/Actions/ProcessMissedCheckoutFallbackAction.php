<?php

declare(strict_types=1);

namespace App\Domains\Stay\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Stay\Models\CheckOut;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final readonly class ProcessMissedCheckoutFallbackAction
{
    public function __construct(
        private ProcessStayCheckOutAction $checkOutAction
    ) {}

    /**
     * Scan and handle overdue checkouts where the guest departed without submitting condition report.
     *
     * @return array<int, array{booking_id: int, booking_code: string, check_out_id: int, host_notified: bool}>
     */
    public function execute(?string $referenceDate = null): array
    {
        $today = $referenceDate ? CarbonImmutable::parse($referenceDate) : CarbonImmutable::now();

        // Overdue bookings: status is checked_in or active, and checkout date is past today or past departure deadline
        $overdueBookings = Booking::query()
            ->whereIn('status', [BookingStatus::CheckedIn, BookingStatus::Active])
            ->whereDate('check_out_date', '<=', $today->toDateString())
            ->with(['guest', 'host.user', 'property'])
            ->get();

        $processed = [];

        foreach ($overdueBookings as $booking) {
            // Check if checkout record already exists
            if ($booking->checkOut()->exists()) {
                continue;
            }

            // Fallback user: assign to system/host user
            $systemUser = $booking->host?->user ?? User::query()->where('status', 'active')->firstOrFail();

            // Process checkout with missed condition flag
            // RULE: Failing to complete condition form is NOT proof of damage and does NOT forfeit deposit.
            $checkOut = $this->checkOutAction->execute(
                booking: $booking,
                user: $systemUser,
                propertyCondition: 'uninspected_by_guest',
                conditionNotes: 'Guest departed without completing checkout condition report. Missing form flagged; host notified for physical inspection.',
                conditionPhotos: [],
                isMissedCheckoutFallback: true
            );

            // Log event and notify host that guest did not complete checkout condition form
            Log::info("Missed checkout handled for booking {$booking->booking_code}. Host notified for 48-hour inspection window.");

            $processed[] = [
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'check_out_id' => $checkOut->id,
                'host_notified' => true,
            ];
        }

        return $processed;
    }
}

