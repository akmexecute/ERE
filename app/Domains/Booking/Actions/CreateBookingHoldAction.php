<?php

declare(strict_types=1);

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPriceItem;
use App\Domains\Calendar\Actions\ResolveDynamicStayQuoteAction;
use App\Domains\Calendar\Models\AvailabilityCalendar;
use App\Domains\Host\Models\Host;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class CreateBookingHoldAction
{
    public function __construct(
        private ResolveDynamicStayQuoteAction $quoteAction = new ResolveDynamicStayQuoteAction()
    ) {}

    /**
     * Create a concurrency-safe 15-minute temporary booking hold with itemized price snapshot.
     *
     * @param array{
     *   listing_id: int,
     *   accommodation_unit_id: int,
     *   check_in_date: string,
     *   check_out_date: string,
     *   adults_count: int,
     *   children_count?: int,
     *   infants_count?: int,
     *   pets_count?: int,
     *   idempotency_key: string,
     *   booking_mode?: string
     * } $data
     */
    public function execute(User $guest, array $data): Booking
    {
        $idempotencyKey = trim((string) $data['idempotency_key']);
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages(['idempotency_key' => ['Idempotency key is required.']]);
        }

        // 1. Idempotency Check: Same key returning existing booking
        $existing = Booking::query()
            ->where('idempotency_key', $idempotencyKey)
            ->with(['priceItems', 'unit', 'stayProfile'])
            ->first();

        if ($existing) {
            // Verify that request parameters match the original submission
            if (
                $existing->accommodation_unit_id === (int) $data['accommodation_unit_id'] &&
                $existing->check_in_date->toDateString() === $data['check_in_date'] &&
                $existing->check_out_date->toDateString() === $data['check_out_date'] &&
                $existing->guest_user_id === $guest->id
            ) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'idempotency_key' => ['The provided idempotency key has already been used for a different booking request.'],
            ]);
        }

        // 2. Cross-record relational verification
        $listing = Listing::query()->with(['property.owner', 'stayProfile.host'])->findOrFail($data['listing_id']);

        if ($listing->transaction_mode !== 'STAY') {
            throw ValidationException::withMessages(['listing' => ['Selected listing is not configured for short stays.']]);
        }

        $profile = $listing->stayProfile;
        if (! $profile) {
            throw ValidationException::withMessages(['listing' => ['Short-stay configuration profile not found for this listing.']]);
        }

        $property = $listing->property;
        if (! $property) {
            throw ValidationException::withMessages(['listing' => ['Property linked to this listing is missing.']]);
        }

        $unit = AccommodationUnit::query()->with('unitType')->findOrFail($data['accommodation_unit_id']);

        // Verify that unit belongs to property and stay profile
        if ($unit->property_id !== $property->id) {
            throw ValidationException::withMessages(['accommodation_unit' => ['Unit does not belong to the selected property.']]);
        }

        if ($unit->unitType && $unit->unitType->stay_listing_profile_id !== $profile->id) {
            throw ValidationException::withMessages(['accommodation_unit' => ['Unit type does not belong to this stay configuration.']]);
        }

        if ($unit->status !== 'ready') {
            throw ValidationException::withMessages(['accommodation_unit' => ["This unit is currently {$unit->status} and cannot be booked."]]);
        }

        // Host verification & Self-booking prevention
        $host = $profile->host;
        if (! $host) {
            throw ValidationException::withMessages(['host' => ['Host profile not found for this stay.']]);
        }

        if ($guest->id === $host->user_id || $guest->id === $property->owner_id) {
            throw new AccessDeniedHttpException('Hosts cannot book their own listings.');
        }

        // 3. Guest Capacity Validation
        $adults = (int) $data['adults_count'];
        $children = (int) ($data['children_count'] ?? 0);
        $infants = (int) ($data['infants_count'] ?? 0);
        $pets = (int) ($data['pets_count'] ?? 0);

        if ($adults + $children > $profile->max_guests) {
            throw ValidationException::withMessages([
                'guests' => ["Total guests ({$adults} adults + {$children} children) exceeds maximum capacity of {$profile->max_guests}."],
            ]);
        }

        if ($pets > 0 && ! $profile->allows_pets) {
            throw ValidationException::withMessages([
                'pets' => ['Pets are not allowed at this property.'],
            ]);
        }

        // 4. Server-Side Authoritative Quote Recalculation
        $checkIn = CarbonImmutable::parse($data['check_in_date']);
        $checkOut = CarbonImmutable::parse($data['check_out_date']);

        if ($checkOut <= $checkIn) {
            throw ValidationException::withMessages(['dates' => ['Check-out date must be after check-in date.']]);
        }

        if ($checkIn < CarbonImmutable::today()) {
            throw ValidationException::withMessages(['dates' => ['Check-in date cannot be in the past.']]);
        }

        // Load calendar overrides and seasonal rules
        $calendarOverrides = AvailabilityCalendar::query()
            ->where('accommodation_unit_id', $unit->id)
            ->whereBetween('calendar_date', [$checkIn->toDateString(), $checkOut->toDateString()])
            ->get()
            ->keyBy(fn ($c) => $c->calendar_date->toDateString())
            ->map(fn ($c) => [
                'status' => $c->status,
                'custom_nightly_rate_etb' => $c->custom_nightly_rate_etb ? (float) $c->custom_nightly_rate_etb : null,
                'min_stay_override_nights' => $c->min_stay_override_nights,
            ])
            ->all();

        $seasonalRules = $profile->seasonalPrices()
            ->where('is_active', true)
            ->get(['start_date', 'end_date', 'nightly_rate_etb', 'rate_multiplier', 'min_stay_nights'])
            ->map(fn ($s) => [
                'start_date' => $s->start_date->toDateString(),
                'end_date' => $s->end_date->toDateString(),
                'nightly_rate_etb' => $s->nightly_rate_etb ? (float) $s->nightly_rate_etb : null,
                'rate_multiplier' => (float) $s->rate_multiplier,
                'min_stay_nights' => $s->min_stay_nights,
            ])
            ->all();

        $quote = $this->quoteAction->execute(
            checkInDate: $checkIn->toDateString(),
            checkOutDate: $checkOut->toDateString(),
            profile: [
                'base_nightly_rate_etb' => (float) $profile->base_nightly_rate_etb,
                'weekend_nightly_rate_etb' => $profile->weekend_nightly_rate_etb ? (float) $profile->weekend_nightly_rate_etb : null,
                'weekly_discount_pct' => (float) $profile->weekly_discount_pct,
                'monthly_discount_pct' => (float) $profile->monthly_discount_pct,
                'cleaning_fee_etb' => (float) $profile->cleaning_fee_etb,
                'security_deposit_etb' => (float) $profile->security_deposit_etb,
                'guest_service_fee_pct' => (float) $profile->guest_service_fee_pct,
                'tourism_tax_pct' => (float) $profile->tourism_tax_pct,
                'min_stay_nights' => (int) $profile->min_stay_nights,
                'max_stay_nights' => (int) $profile->max_stay_nights,
            ],
            calendarOverridesByDate: $calendarOverrides,
            seasonalRules: $seasonalRules
        );

        // 5. Concurrency-Safe Booking Hold Creation inside Database Transaction
        try {
            return DB::transaction(function () use (
                $guest,
                $listing,
                $property,
                $profile,
                $unit,
                $host,
                $data,
                $checkIn,
                $checkOut,
                $quote,
                $adults,
                $children,
                $infants,
                $pets,
                $idempotencyKey
            ): Booking {
                // Lock inventory rows in availability_calendars for check-in to check-out (half-open)
                $nightsList = [];
                $cursor = $checkIn;
                while ($cursor < $checkOut) {
                    $nightsList[] = $cursor->toDateString();
                    $cursor = $cursor->addDay();
                }

                $lockedCalendar = AvailabilityCalendar::query()
                    ->where('accommodation_unit_id', $unit->id)
                    ->whereIn('calendar_date', $nightsList)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn ($c) => $c->calendar_date->toDateString());

                // Check for unavailable nights
                foreach ($nightsList as $dateStr) {
                    $existingCal = $lockedCalendar->get($dateStr);
                    if ($existingCal && in_array($existingCal->status, ['booked', 'held_pending_payment', 'blocked_by_host', 'external_ical_blocked'], true)) {
                        throw ValidationException::withMessages([
                            'dates' => ["The date {$dateStr} is no longer available for this accommodation ({$existingCal->status})."],
                        ]);
                    }
                }

                // Check for concurrent active bookings overlapping this range (defense in depth)
                $hasOverlappingBooking = Booking::query()
                    ->where('accommodation_unit_id', $unit->id)
                    ->whereIn('status', [
                        BookingStatus::AwaitingPayment->value,
                        BookingStatus::Confirmed->value,
                        BookingStatus::CheckedIn->value,
                        BookingStatus::Active->value,
                    ])
                    ->where('check_in_date', '<', $checkOut->toDateString())
                    ->where('check_out_date', '>', $checkIn->toDateString())
                    ->lockForUpdate()
                    ->exists();

                if ($hasOverlappingBooking) {
                    throw ValidationException::withMessages([
                        'dates' => ['The selected dates have just been reserved by another guest. Please choose different dates.'],
                    ]);
                }

                // Generate authoritative booking code: BK-YYYY-XXXXXXX
                $year = date('Y');
                $code = 'BK-'.$year.'-'.str_pad((string) random_int(100000, 9999999), 7, '0', STR_PAD_LEFT);

                $holdMinutes = (int) ($profile->hold_duration_minutes ?? 15);
                $holdExpiresAt = CarbonImmutable::now('Africa/Addis_Ababa')->addMinutes($holdMinutes);

                // Create Booking record
                $booking = Booking::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'booking_code' => $code,
                    'idempotency_key' => $idempotencyKey,
                    'listing_id' => $listing->id,
                    'property_id' => $property->id,
                    'stay_listing_profile_id' => $profile->id,
                    'accommodation_unit_id' => $unit->id,
                    'host_id' => $host->id,
                    'guest_user_id' => $guest->id,
                    'booking_mode' => $data['booking_mode'] ?? ($profile->instant_book_enabled ? 'instant_book' : 'request_to_book'),
                    'check_in_date' => $checkIn->toDateString(),
                    'check_out_date' => $checkOut->toDateString(),
                    'nights_count' => $quote['nights_count'],
                    'adults_count' => $adults,
                    'children_count' => $children,
                    'infants_count' => $infants,
                    'pets_count' => $pets,
                    'accommodation_raw_subtotal_etb' => $quote['accommodation_raw_subtotal_etb'],
                    'discount_amount_etb' => $quote['discount_amount_etb'],
                    'accommodation_net_subtotal_etb' => $quote['accommodation_net_subtotal_etb'],
                    'cleaning_fee_etb' => $quote['cleaning_fee_etb'],
                    'platform_fee_etb' => $quote['platform_fee_etb'],
                    'tax_amount_etb' => $quote['tax_amount_etb'],
                    'security_deposit_etb' => $quote['security_deposit_etb'],
                    'total_amount_etb' => $quote['total_amount_etb'],
                    'currency' => 'ETB',
                    'cancellation_policy' => $profile->cancellation_policy,
                    'status' => BookingStatus::AwaitingPayment,
                    'hold_expires_at' => $holdExpiresAt,
                ]);

                // Record itemized price items snapshot (SRS Section 50)
                BookingPriceItem::query()->create([
                    'booking_id' => $booking->id,
                    'item_type' => 'nightly_rate',
                    'label_en' => "{$quote['nights_count']} nights accommodation",
                    'quantity' => $quote['nights_count'],
                    'unit_amount_etb' => round($quote['accommodation_raw_subtotal_etb'] / $quote['nights_count'], 2),
                    'total_amount_etb' => $quote['accommodation_raw_subtotal_etb'],
                    'metadata_json' => ['breakdown' => $quote['nightly_breakdown']],
                ]);

                if ($quote['discount_amount_etb'] > 0) {
                    BookingPriceItem::query()->create([
                        'booking_id' => $booking->id,
                        'item_type' => $quote['discount_type'] ?? 'long_stay_discount',
                        'label_en' => 'Long stay discount',
                        'quantity' => 1,
                        'unit_amount_etb' => -$quote['discount_amount_etb'],
                        'total_amount_etb' => -$quote['discount_amount_etb'],
                    ]);
                }

                if ($quote['cleaning_fee_etb'] > 0) {
                    BookingPriceItem::query()->create([
                        'booking_id' => $booking->id,
                        'item_type' => 'cleaning_fee',
                        'label_en' => 'Cleaning fee',
                        'quantity' => 1,
                        'unit_amount_etb' => $quote['cleaning_fee_etb'],
                        'total_amount_etb' => $quote['cleaning_fee_etb'],
                    ]);
                }

                if ($quote['platform_fee_etb'] > 0) {
                    BookingPriceItem::query()->create([
                        'booking_id' => $booking->id,
                        'item_type' => 'platform_fee',
                        'label_en' => 'Platform guest service fee',
                        'quantity' => 1,
                        'unit_amount_etb' => $quote['platform_fee_etb'],
                        'total_amount_etb' => $quote['platform_fee_etb'],
                    ]);
                }

                if ($quote['tax_amount_etb'] > 0) {
                    BookingPriceItem::query()->create([
                        'booking_id' => $booking->id,
                        'item_type' => 'tax',
                        'label_en' => 'Tourism tax',
                        'quantity' => 1,
                        'unit_amount_etb' => $quote['tax_amount_etb'],
                        'total_amount_etb' => $quote['tax_amount_etb'],
                    ]);
                }

                if ($quote['security_deposit_etb'] > 0) {
                    BookingPriceItem::query()->create([
                        'booking_id' => $booking->id,
                        'item_type' => 'security_deposit',
                        'label_en' => 'Security deposit (refundable)',
                        'quantity' => 1,
                        'unit_amount_etb' => $quote['security_deposit_etb'],
                        'total_amount_etb' => $quote['security_deposit_etb'],
                    ]);
                }

                // Update availability_calendars inventory to held_pending_payment
                foreach ($nightsList as $dateStr) {
                    AvailabilityCalendar::query()->updateOrCreate(
                        [
                            'accommodation_unit_id' => $unit->id,
                            'calendar_date' => $dateStr,
                        ],
                        [
                            'status' => 'held_pending_payment',
                            'booking_id' => $booking->id,
                            'block_reason' => "Temporary hold: {$booking->booking_code}",
                        ]
                    );
                }

                return $booking->load(['priceItems', 'unit', 'stayProfile']);
            });
        } catch (QueryException $e) {
            // PostgreSQL exclusion constraint violation (code 23P01) or unique violation
            if (str_contains($e->getMessage(), 'ex_bookings_no_overlapping_dates') || str_contains($e->getMessage(), '23P01')) {
                throw ValidationException::withMessages([
                    'dates' => ['The selected dates have just been reserved by another guest. Please select alternative dates.'],
                ]);
            }

            throw $e;
        }
    }
}
