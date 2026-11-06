<?php

declare(strict_types=1);

namespace App\Domains\Viewing\Actions;

use App\Domains\Viewing\Enums\ViewingAppointmentStatus;
use App\Domains\Viewing\Models\PropertyViewing;
use App\Domains\Viewing\Models\ViewingAppointment;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TransitionViewingAppointmentStatusAction
{
    /**
     * Atomically transition a viewing appointment's status with actor authorization.
     */
    public function execute(
        User $actor,
        ViewingAppointment $appointment,
        ViewingAppointmentStatus|string $targetStatus,
        ?string $notes = null
    ): ViewingAppointment {
        $nextStatus = $targetStatus instanceof ViewingAppointmentStatus
            ? $targetStatus
            : ViewingAppointmentStatus::from($targetStatus);

        $currentStatus = ViewingAppointmentStatus::tryFrom($appointment->status) ?? ViewingAppointmentStatus::Requested;

        // 1. Terminal state check
        if ($currentStatus->isTerminal()) {
            throw new DomainException(
                "Cannot change status: Appointment is already in terminal state '{$currentStatus->value}'."
            );
        }

        // 2. Authorization rules
        $isBuyer = (int) $actor->id === (int) $appointment->buyer_user_id;
        $isAgentOrSeller = (int) $actor->id === (int) $appointment->seller_or_agent_user_id;
        $isAdmin = $actor->hasRole('super_admin') || $actor->hasRole('platform_admin');

        if (! $isBuyer && ! $isAgentOrSeller && ! $isAdmin) {
            throw new AuthorizationException('Unauthorized: You are not a party to this viewing appointment.');
        }

        // Buyer cannot confirm an appointment on behalf of the agent/seller
        if ($nextStatus === ViewingAppointmentStatus::Confirmed && ! $isAgentOrSeller && ! $isAdmin) {
            throw new AuthorizationException(
                'Only the assigned agent, seller, or an administrator can confirm a viewing appointment.'
            );
        }

        // Buyer cannot mark no-show or complete on behalf of the agent
        if (in_array($nextStatus, [ViewingAppointmentStatus::Completed, ViewingAppointmentStatus::NoShow], true) && ! $isAgentOrSeller && ! $isAdmin) {
            throw new AuthorizationException('Only the conducting agent can report viewing completion or no-show.');
        }

        return DB::transaction(function () use ($appointment, $nextStatus, $notes, $isAgentOrSeller): ViewingAppointment {
            if ($nextStatus === ViewingAppointmentStatus::Confirmed) {
                // Lock agent and property resources
                User::query()->where('id', $appointment->seller_or_agent_user_id)->lockForUpdate()->first();
                \App\Domains\Property\Models\Property::query()->where('id', $appointment->property_id)->lockForUpdate()->first();

                [$start, $end] = ViewingAppointment::parseTimeSlot($appointment->scheduled_time_slot);

                $conflictingConfirmed = ViewingAppointment::query()
                    ->where('id', '!=', $appointment->id)
                    ->whereDate('scheduled_date', $appointment->scheduled_date)
                    ->where('status', ViewingAppointmentStatus::Confirmed->value)
                    ->where(function ($q) use ($appointment) {
                        $q->where('seller_or_agent_user_id', $appointment->seller_or_agent_user_id)
                            ->orWhere('property_id', $appointment->property_id);
                    })
                    ->where(function ($q) use ($start, $end, $appointment) {
                        $q->where(function ($sub) use ($start, $end) {
                            $sub->whereNotNull('slot_start_time')
                                ->whereNotNull('slot_end_time')
                                ->where('slot_start_time', '<', $end)
                                ->where('slot_end_time', '>', $start);
                        })->orWhere('scheduled_time_slot', $appointment->scheduled_time_slot);
                    })
                    ->exists();

                if ($conflictingConfirmed) {
                    throw new DomainException(
                        "Scheduling conflict: A confirmed viewing appointment already exists for this slot ({$appointment->scheduled_time_slot})."
                    );
                }
            }

            $updateData = ['status' => $nextStatus->value];

            if ($nextStatus === ViewingAppointmentStatus::Confirmed) {
                $updateData['confirmed_at'] = now();
                if ($notes && $isAgentOrSeller) {
                    $updateData['agent_confirmation_notes'] = $notes;
                }
            } elseif ($nextStatus === ViewingAppointmentStatus::Completed) {
                $updateData['completed_at'] = now();
            }

            $appointment->update($updateData);

            // Also synchronize property_viewings table
            PropertyViewing::query()
                ->where('listing_id', $appointment->listing_id)
                ->where('property_id', $appointment->property_id)
                ->where('client_id', $appointment->buyer_user_id)
                ->latest()
                ->first()
                ?->update(['status' => $nextStatus->value]);

            return $appointment->refresh();
        });
    }
}

