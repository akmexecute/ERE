<?php

declare(strict_types=1);

namespace App\Domains\Stay\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\Stay\Models\CheckOut;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ProcessStayCheckOutAction
{
    /**
     * @param array<int, string> $conditionPhotos
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        Booking $booking,
        User $user,
        string $propertyCondition = 'good',
        ?string $conditionNotes = null,
        array $conditionPhotos = [],
        bool $isMissedCheckoutFallback = false
    ): CheckOut {
        // 1. Authorize: guest, host, or authorized staff
        $isGuest = (int) $booking->guest_user_id === (int) $user->id;
        $isHost = (int) $booking->host?->user_id === (int) $user->id || (int) $booking->property?->owner_id === (int) $user->id;
        $isStaff = $user->isStaff();

        if (! $isGuest && ! $isHost && ! $isStaff && ! $isMissedCheckoutFallback) {
            throw new AuthorizationException('You are not authorized to record checkout for this reservation.');
        }

        // 2. Validate booking state
        $fresh = $booking->fresh() ?: $booking;
        if ($fresh->status !== BookingStatus::CheckedIn && $fresh->status !== BookingStatus::Active) {
            throw ValidationException::withMessages([
                'booking' => ["Cannot check out of reservation with status '{$fresh->status->value}'. Must be 'checked_in'."],
            ]);
        }

        return DB::transaction(function () use (
            $booking,
            $user,
            $propertyCondition,
            $conditionNotes,
            $conditionPhotos,
            $isMissedCheckoutFallback
        ): CheckOut {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            // Prevent duplicate checkout
            if ($lockedBooking->checkOut()->exists()) {
                throw ValidationException::withMessages([
                    'check_out' => ['Checkout has already been recorded for this reservation.'],
                ]);
            }

            $now = CarbonImmutable::now();
            $damageClaimRequired = ($propertyCondition === 'damage_reported');

            // Canonical checkout anchor:
            // Normal checkout: authoritative recorded timestamp ($now).
            // Missed checkout fallback: canonical scheduled departure cutoff (check_out_date 11:00 AM).
            $effectiveCheckoutAt = $isMissedCheckoutFallback
                ? CarbonImmutable::parse($lockedBooking->check_out_date)->setTime(11, 0, 0)
                : $now;

            // 3. Create persistent CheckOut record
            $checkOut = CheckOut::query()->create([
                'booking_id' => $lockedBooking->id,
                'recorded_by_user_id' => $user->id,
                'property_condition' => $propertyCondition,
                'condition_notes' => $conditionNotes,
                'condition_photos_json' => $conditionPhotos,
                'damage_claim_required' => $damageClaimRequired,
                'is_missed_checkout_fallback' => $isMissedCheckoutFallback,
                'checked_out_at' => $effectiveCheckoutAt,
            ]);

            // 4. Transition booking status to CheckedOut
            $lockedBooking->update([
                'status' => BookingStatus::CheckedOut,
                'checked_out_at' => $effectiveCheckoutAt,
            ]);

            // 5. Trigger Housekeeping Task exactly once (SRS Section 63)
            $taskCode = 'CLN-'.$now->year.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $cleaningTask = CleaningTask::query()->firstOrCreate(
                ['booking_id' => $lockedBooking->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'task_code' => $taskCode,
                    'property_id' => $lockedBooking->property_id,
                    'accommodation_unit_id' => $lockedBooking->accommodation_unit_id,
                    'host_id' => $lockedBooking->host_id,
                    'scheduled_date' => $now->toDateString(),
                    'status' => CleaningTaskStatus::Pending,
                    'checklist_json' => [
                        'Strip and wash all bed linens and towels',
                        'Disinfect bathrooms and restock toiletries',
                        'Sanitize kitchen appliances, coffee station and refrigerator',
                        'Inspect windows, locks, Wi-Fi router, and backup generator power',
                        'Vacuum, mop floors and place welcome amenities',
                    ],
                ]
            );

            // 6. Update Accommodation Unit status to 'cleaning'
            if ($lockedBooking->unit) {
                $lockedBooking->unit->update(['status' => 'cleaning']);
            }

            return $checkOut;
        });
    }
}
