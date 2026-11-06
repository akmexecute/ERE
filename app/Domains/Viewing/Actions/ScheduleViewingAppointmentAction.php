<?php

declare(strict_types=1);

namespace App\Domains\Viewing\Actions;

use App\Domains\Listing\Models\Listing;
use App\Domains\Viewing\Enums\ViewingAppointmentStatus;
use App\Domains\Viewing\Models\PropertyViewing;
use App\Domains\Viewing\Models\ViewingAppointment;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ScheduleViewingAppointmentAction
{
    /**
     * Atomically schedule a viewing appointment with scheduling conflict detection.
     *
     * @param  array{
     *   listing_id: int,
     *   property_id?: ?int,
     *   scheduled_date: string,
     *   scheduled_time_slot: string,
     *   viewing_type?: string,
     *   visitor_notes?: ?string,
     *   crm_lead_id?: ?int
     * }  $payload
     */
    public function execute(User $buyer, int $sellerOrAgentUserId, array $payload): ViewingAppointment
    {
        $listingId = (int) $payload['listing_id'];
        $listing = Listing::query()->with('property')->findOrFail($listingId);

        $propertyId = $payload['property_id'] ?? $listing->property_id ?? $listing->property?->id;
        if (! $propertyId) {
            throw new InvalidArgumentException('Listing must be associated with a valid property.');
        }

        $scheduledDate = CarbonImmutable::parse($payload['scheduled_date'])->toDateString();
        $today = CarbonImmutable::today()->toDateString();

        if ($scheduledDate < $today) {
            throw new InvalidArgumentException('Cannot schedule a viewing appointment in the past.');
        }

        $timeSlot = trim($payload['scheduled_time_slot']);
        if (empty($timeSlot)) {
            throw new InvalidArgumentException('Scheduled time slot is required.');
        }

        // Concurrency-safe atomic conflict detection and reservation
        return DB::transaction(function () use (
            $buyer,
            $sellerOrAgentUserId,
            $listing,
            $propertyId,
            $scheduledDate,
            $timeSlot,
            $payload
        ): ViewingAppointment {
            // Lock primary scheduling resources to serialize concurrent booking requests
            User::query()->where('id', $sellerOrAgentUserId)->lockForUpdate()->first();
            \App\Domains\Property\Models\Property::query()->where('id', $propertyId)->lockForUpdate()->first();

            [$newStart, $newEnd] = ViewingAppointment::parseTimeSlot($timeSlot);

            // Check for existing confirmed appointments for the same agent/seller OR property on the same date with overlapping interval
            $hasConflict = ViewingAppointment::query()
                ->whereDate('scheduled_date', $scheduledDate)
                ->where('status', ViewingAppointmentStatus::Confirmed->value)
                ->where(function ($q) use ($sellerOrAgentUserId, $propertyId) {
                    $q->where('seller_or_agent_user_id', $sellerOrAgentUserId)
                        ->orWhere('property_id', $propertyId);
                })
                ->where(function ($q) use ($newStart, $newEnd, $timeSlot) {
                    $q->where(function ($sub) use ($newStart, $newEnd) {
                        $sub->whereNotNull('slot_start_time')
                            ->whereNotNull('slot_end_time')
                            ->where('slot_start_time', '<', $newEnd)
                            ->where('slot_end_time', '>', $newStart);
                    })->orWhere('scheduled_time_slot', $timeSlot);
                })
                ->exists();

            if ($hasConflict) {
                throw new DomainException(
                    "Scheduling conflict: A confirmed viewing appointment already exists for this date and time slot ({$timeSlot})."
                );
            }

            $appointment = ViewingAppointment::query()->create([
                'uuid' => (string) Str::uuid(),
                'appointment_code' => 'VWG-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                'listing_id' => $listing->id,
                'property_id' => $propertyId,
                'buyer_user_id' => $buyer->id,
                'client_id' => $buyer->id,
                'seller_or_agent_user_id' => $sellerOrAgentUserId,
                'agent_id' => $sellerOrAgentUserId,
                'crm_lead_id' => $payload['crm_lead_id'] ?? null,
                'scheduled_date' => $scheduledDate,
                'scheduled_time_slot' => $timeSlot,
                'slot_start_time' => $newStart,
                'slot_end_time' => $newEnd,
                'scheduled_at' => CarbonImmutable::parse($scheduledDate . ' ' . explode('-', $timeSlot)[0]),
                'viewing_type' => $payload['viewing_type'] ?? 'in_person',
                'status' => ViewingAppointmentStatus::Requested->value,
                'visitor_notes' => $payload['visitor_notes'] ?? null,
            ]);

            // Sync with property_viewings for API compatibility
            PropertyViewing::query()->create([
                'property_id' => $propertyId,
                'listing_id' => $listing->id,
                'agent_id' => $sellerOrAgentUserId,
                'client_id' => $buyer->id,
                'scheduled_at' => $appointment->scheduled_at ?? now(),
                'status' => ViewingAppointmentStatus::Requested->value,
                'notes' => $payload['visitor_notes'] ?? null,
            ]);

            return $appointment;
        });
    }
}
