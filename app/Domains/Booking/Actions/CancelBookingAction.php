<?php

declare(strict_types=1);

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Calendar\Models\AvailabilityCalendar;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class CancelBookingAction
{
    public function __construct(
        private CalculateCancellationRefundAction $refundCalculator = new CalculateCancellationRefundAction()
    ) {}

    /**
     * Cancel an active or held booking, calculate refunds, and safely release inventory.
     *
     * @return array{
     *   booking: Booking,
     *   refund: array<string, mixed>
     * }
     */
    public function execute(Booking $booking, User $actingUser, ?string $reason = null): array
    {
        $booking->loadMissing(['host', 'guest']);

        // Authorization: guest, host, or platform admin
        $isHost = $booking->host && $booking->host->user_id === $actingUser->id;
        $isGuest = $booking->guest_user_id === $actingUser->id;
        $isStaff = $actingUser->hasAnyRole(['super_admin', 'platform_admin']);

        if (! $isHost && ! $isGuest && ! $isStaff) {
            throw new AccessDeniedHttpException('You are not authorized to cancel this booking.');
        }

        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Refunded, BookingStatus::Completed, BookingStatus::Expired], true)) {
            throw ValidationException::withMessages([
                'booking' => ["Cannot cancel booking in {$booking->status->value} status."],
            ]);
        }

        // Calculate refund
        $refund = $this->refundCalculator->execute(
            policy: $booking->cancellation_policy ?? 'flexible',
            checkInDate: $booking->check_in_date->toDateString(),
            accommodationNetSubtotalEtb: (float) $booking->accommodation_net_subtotal_etb,
            cleaningFeeEtb: (float) $booking->cleaning_fee_etb,
            cancelledAt: now('Africa/Addis_Ababa')
        );

        $updatedBooking = DB::transaction(function () use ($booking, $reason): Booking {
            $fresh = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $fresh->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason ?? 'Cancelled by guest/host request',
            ]);

            // Release calendar inventory back to available
            AvailabilityCalendar::query()
                ->where('booking_id', $fresh->id)
                ->whereIn('status', ['booked', 'held_pending_payment'])
                ->update([
                    'status' => 'available',
                    'booking_id' => null,
                    'block_reason' => null,
                    'updated_at' => now(),
                ]);

            return $fresh;
        });

        return [
            'booking' => $updatedBooking,
            'refund' => $refund,
        ];
    }
}
