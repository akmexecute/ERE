<?php

declare(strict_types=1);

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use Illuminate\Support\Facades\DB;

final readonly class ExpireUnpaidBookingHoldsAction
{
    public function execute(): int
    {
        return DB::transaction(function (): int {
            $expiredBookings = DB::table('bookings')
                ->where('status', BookingStatus::AwaitingPayment->value)
                ->whereNotNull('hold_expires_at')
                ->where('hold_expires_at', '<=', now())
                ->lockForUpdate()
                ->get(['id', 'accommodation_unit_id']);

            if ($expiredBookings->isEmpty()) {
                return 0;
            }

            $ids = $expiredBookings->pluck('id')->all();

            DB::table('bookings')
                ->whereIn('id', $ids)
                ->update([
                    'status' => BookingStatus::Expired->value,
                    'cancellation_reason' => 'Temporary payment hold expired automatically (SRS Section 193).',
                    'updated_at' => now(),
                ]);

            // Release the held inventory dates back to available
            DB::table('availability_calendars')
                ->whereIn('booking_id', $ids)
                ->where('status', 'held_pending_payment')
                ->update([
                    'status' => 'available',
                    'booking_id' => null,
                    'updated_at' => now(),
                ]);

            return count($ids);
        });
    }
}
