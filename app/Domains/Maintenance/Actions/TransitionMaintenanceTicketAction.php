<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Actions;

use App\Domains\Maintenance\Enums\MaintenanceStatus;
use App\Domains\Maintenance\Models\MaintenanceRequest;
use App\Domains\Maintenance\Models\MaintenanceRequestHistory;
use App\Domains\Portfolio\Services\MultiTenantOrganizationGuard;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionMaintenanceTicketAction
{
    /**
     * Atomically transitions a maintenance ticket to a valid new state.
     *
     * @param array{
     *     assigned_to_user_id?: int|null,
     *     contractor_name?: string|null,
     *     contractor_phone?: string|null,
     *     estimated_cost_etb?: float|int|string|null,
     *     actual_cost_etb?: float|int|string|null,
     *     resolution_notes?: string|null,
     *     notes?: string|null,
     * } $payload
     */
    public function execute(
        User $actor,
        int|MaintenanceRequest $ticket,
        MaintenanceStatus|string $targetStatus,
        array $payload = []
    ): MaintenanceRequest {
        $target = is_string($targetStatus) ? MaintenanceStatus::from($targetStatus) : $targetStatus;
        $ticketId = $ticket instanceof MaintenanceRequest ? $ticket->id : $ticket;

        return DB::transaction(function () use ($actor, $ticketId, $target, $payload): MaintenanceRequest {
            /** @var MaintenanceRequest $lockedTicket */
            $lockedTicket = MaintenanceRequest::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            $currentStatus = $lockedTicket->status;

            // 1. Prevent transitions on terminal closed state
            if ($currentStatus->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => ["Ticket {$lockedTicket->ticket_code} is closed and cannot undergo further status transitions."],
                ]);
            }

            // 2. Authorize actor based on ticket role and transition permissions
            $isGlobalAdmin = $actor->hasRole('super_admin') || $actor->hasRole('platform_admin');
            $isManager = $isGlobalAdmin
                || $actor->hasRole('property_admin')
                || ($lockedTicket->company_id && MultiTenantOrganizationGuard::canAccess($actor, $lockedTicket));
            $isOwner = $lockedTicket->property && (
                $lockedTicket->property->owner_id === $actor->id ||
                $lockedTicket->property->owner_user_id === $actor->id
            );
            $isTechnician = $lockedTicket->assigned_to_user_id === $actor->id;
            $isReporter = $lockedTicket->reported_by_user_id === $actor->id;

            if (! $isManager && ! $isOwner && ! $isTechnician && ! $isReporter) {
                throw new AuthorizationException('You are not authorized to transition this maintenance ticket.');
            }

            // Reporter/tenant constraints: can only cancel open tickets or confirm closure of completed tickets
            if ($isReporter && ! $isManager && ! $isOwner && ! $isTechnician) {
                if ($target !== MaintenanceStatus::Closed) {
                    throw ValidationException::withMessages([
                        'status' => ['Tenants and reporters cannot advance ticket operational workflow.'],
                    ]);
                }
            }

            // Technician constraints: can advance assigned/in-progress/waiting tickets, but cannot close or reassign
            if ($isTechnician && ! $isManager && ! $isOwner) {
                if ($target === MaintenanceStatus::Closed || $target === MaintenanceStatus::Assigned) {
                    throw ValidationException::withMessages([
                        'status' => ['Technicians cannot close or reassign maintenance tickets.'],
                    ]);
                }
            }

            // 3. Validate transition against state machine
            if (! $currentStatus->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Invalid transition: Cannot advance ticket from {$currentStatus->value} to {$target->value}.",
                    ],
                ]);
            }

            $updateData = [
                'status' => $target,
            ];

            // 3. State-Specific Validations & Milestone Timestamps
            if ($target === MaintenanceStatus::Assigned) {
                $assignedUserId = $payload['assigned_to_user_id'] ?? $lockedTicket->assigned_to_user_id;
                $contractor = $payload['contractor_name'] ?? $lockedTicket->contractor_name;

                if (! $assignedUserId && empty(trim((string) $contractor))) {
                    throw ValidationException::withMessages([
                        'assigned_to_user_id' => ['An assigned technician or contractor name is required when moving ticket to assigned status.'],
                    ]);
                }

                $updateData['assigned_to_user_id'] = $assignedUserId;
                $updateData['contractor_name'] = $contractor;
                $updateData['contractor_phone'] = $payload['contractor_phone'] ?? $lockedTicket->contractor_phone;
                $updateData['assigned_at'] = $lockedTicket->assigned_at ?? now();
            }

            if ($target === MaintenanceStatus::InProgress) {
                $updateData['started_at'] = $lockedTicket->started_at ?? now();
            }

            if ($target === MaintenanceStatus::Completed) {
                $resolution = $payload['resolution_notes'] ?? $lockedTicket->resolution_notes;
                if (empty(trim((string) $resolution))) {
                    throw ValidationException::withMessages([
                        'resolution_notes' => ['Resolution notes describing the work completed are mandatory.'],
                    ]);
                }

                $actualCost = isset($payload['actual_cost_etb'])
                    ? (float) $payload['actual_cost_etb']
                    : (float) $lockedTicket->actual_cost_etb;

                if ($actualCost < 0) {
                    throw ValidationException::withMessages([
                        'actual_cost_etb' => ['Actual maintenance cost cannot be negative.'],
                    ]);
                }

                $updateData['actual_cost_etb'] = $actualCost;
                $updateData['resolution_notes'] = trim((string) $resolution);
                $updateData['completed_at'] = now();
            }

            if ($target === MaintenanceStatus::Closed) {
                $closeNotes = $payload['notes'] ?? $payload['resolution_notes'] ?? null;
                if ($currentStatus === MaintenanceStatus::Open && empty(trim((string) $closeNotes))) {
                    throw ValidationException::withMessages([
                        'notes' => ['A cancellation or rejection reason is mandatory when closing an open ticket.'],
                    ]);
                }

                $updateData['closed_at'] = now();
            }

            if (isset($payload['estimated_cost_etb'])) {
                $est = (float) $payload['estimated_cost_etb'];
                if ($est < 0) {
                    throw ValidationException::withMessages([
                        'estimated_cost_etb' => ['Estimated cost cannot be negative.'],
                    ]);
                }
                $updateData['estimated_cost_etb'] = $est;
            }

            if (isset($payload['actual_cost_etb']) && $target !== MaintenanceStatus::Completed) {
                $act = (float) $payload['actual_cost_etb'];
                if ($act < 0) {
                    throw ValidationException::withMessages([
                        'actual_cost_etb' => ['Actual cost cannot be negative.'],
                    ]);
                }
                $updateData['actual_cost_etb'] = $act;
            }

            $lockedTicket->update($updateData);

            // 4. Record Immutable Operational Status History Event
            $historyNote = $payload['notes'] ?? $payload['resolution_notes'] ?? "Status transitioned to {$target->label()}";

            MaintenanceRequestHistory::query()->create([
                'maintenance_request_id' => $lockedTicket->id,
                'actor_user_id' => $actor->id,
                'from_status' => $currentStatus->value,
                'to_status' => $target->value,
                'notes' => trim((string) $historyNote),
                'cost_snapshot_etb' => $lockedTicket->actual_cost_etb > 0
                    ? $lockedTicket->actual_cost_etb
                    : $lockedTicket->estimated_cost_etb,
                'created_at' => now(),
            ]);

            return $lockedTicket->fresh(['property', 'projectUnit', 'accommodationUnit', 'assignedTechnician', 'histories']);
        });
    }
}
