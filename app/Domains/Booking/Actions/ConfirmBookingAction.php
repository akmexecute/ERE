<?php

declare(strict_types=1);

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Calendar\Models\AvailabilityCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmBookingAction
{
    /**
     * Confirm a pending or held booking after payment confirmation or host approval.
     */
    public function execute(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $fresh = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($fresh->status === BookingStatus::Confirmed) {
                return $fresh;
            }

            if ($fresh->status === BookingStatus::Expired) {
                throw ValidationException::withMessages([
                    'booking' => ['This booking hold has expired and cannot be confirmed.'],
                ]);
            }

            // Check if hold has expired
            if ($fresh->hold_expires_at && $fresh->hold_expires_at < now()) {
                $fresh->update([
                    'status' => BookingStatus::Expired,
                    'cancellation_reason' => 'Payment confirmation attempted after hold expiration.',
                ]);

                // Release inventory
                AvailabilityCalendar::query()
                    ->where('booking_id', $fresh->id)
                    ->where('status', 'held_pending_payment')
                    ->update([
                        'status' => 'available',
                        'booking_id' => null,
                        'block_reason' => null,
                    ]);

                throw ValidationException::withMessages([
                    'booking' => ['The 15-minute temporary payment hold for this booking has expired.'],
                ]);
            }

            // Transition to Confirmed
            $fresh->update([
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            // Mark inventory as booked
            AvailabilityCalendar::query()
                ->where('booking_id', $fresh->id)
                ->update([
                    'status' => 'booked',
                    'block_reason' => "Confirmed booking: {$fresh->booking_code}",
                ]);

            return $fresh->fresh(['unit', 'stayProfile', 'priceItems']);
        });
    }
}
