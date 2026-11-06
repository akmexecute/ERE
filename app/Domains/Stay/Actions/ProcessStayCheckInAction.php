<?php

declare(strict_types=1);

namespace App\Domains\Stay\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Stay\Models\CheckIn;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ProcessStayCheckInAction
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        Booking $booking,
        User $user,
        string $method = 'guest_self',
        ?string $accessCodeUsed = null,
        ?string $notes = null
    ): CheckIn {
        // 1. Authorize: only the booking guest or the stay host/staff may record check-in
        $isGuest = (int) $booking->guest_user_id === (int) $user->id;
        $isHost = (int) $booking->host?->user_id === (int) $user->id || (int) $booking->property?->owner_id === (int) $user->id;
        $isStaff = $user->isStaff();

        if (! $isGuest && ! $isHost && ! $isStaff) {
            throw new AuthorizationException('You are not authorized to record check-in for this reservation.');
        }

        // 2. Validate booking state machine
        if ($booking->status !== BookingStatus::Confirmed && $booking->status !== BookingStatus::Active) {
            throw ValidationException::withMessages([
                'booking' => ["Cannot check in to reservation with status '{$booking->status->value}'. Must be 'confirmed'."],
            ]);
        }

        // 3. Concurrency-safe check-in using database transaction & row lock
        return DB::transaction(function () use ($booking, $user, $method, $accessCodeUsed, $notes): CheckIn {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            // Prevent duplicate check-in
            if ($lockedBooking->checkIn()->exists()) {
                throw ValidationException::withMessages([
                    'check_in' => ['Check-in has already been recorded for this reservation.'],
                ]);
            }

            $now = CarbonImmutable::now();

            // Create CheckIn record
            $checkIn = CheckIn::query()->create([
                'booking_id' => $lockedBooking->id,
                'recorded_by_user_id' => $user->id,
                'check_in_method' => $method,
                'digital_access_code_used' => $accessCodeUsed,
                'house_manual_unlocked_at' => $now,
                'notes' => $notes,
                'checked_in_at' => $now,
            ]);

            // Transition booking status to CheckedIn
            $lockedBooking->update([
                'status' => BookingStatus::CheckedIn,
                'checked_in_at' => $now,
            ]);

            return $checkIn;
        });
    }
}

