<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\DeveloperPaymentPlan;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Developer\Models\UnitReservation;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ReserveProjectUnitAction
{
    /**
     * Concurrency-safe atomic reservation of a development project unit.
     */
    public function execute(
        int $unitId,
        int $buyerUserId,
        string $idempotencyKey,
        ?int $paymentPlanId = null,
        ?int $agentUserId = null,
        ?int $crmLeadId = null,
        int $holdMinutes = 15,
        float $depositPercentage = 10.0
    ): UnitReservation {
        if ($holdMinutes <= 0) {
            throw new InvalidArgumentException("Hold minutes must be greater than zero.");
        }

        if ($depositPercentage <= 0.0 || $depositPercentage > 100.0) {
            throw new InvalidArgumentException("Deposit percentage must be between 0.01% and 100.00%.");
        }

        // 1. Idempotency Check: Scoped verification
        $existing = UnitReservation::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ((int) $existing->buyer_user_id !== (int) $buyerUserId || (int) $existing->project_unit_id !== (int) $unitId) {
                throw new DomainException(
                    "Idempotency conflict: key '{$idempotencyKey}' was already consumed for a different buyer or unit."
                );
            }

            return $existing;
        }

        return DB::transaction(function () use (
            $unitId,
            $buyerUserId,
            $idempotencyKey,
            $paymentPlanId,
            $agentUserId,
            $crmLeadId,
            $holdMinutes,
            $depositPercentage
        ): UnitReservation {
            // 2. Authoritative row lock on the inventory unit
            /** @var ProjectUnit $unit */
            $unit = ProjectUnit::where('id', $unitId)
                ->lockForUpdate()
                ->firstOrFail();

            // 3. Inventory availability validation
            if ($unit->status !== UnitInventoryStatus::Available) {
                // Check if the unit is only in 'reserved' state due to an expired temporary hold
                $existingHold = UnitReservation::where('project_unit_id', $unit->id)
                    ->whereIn('status', [
                        ReservationStatus::Requested->value,
                        ReservationStatus::PendingPayment->value,
                        ReservationStatus::Reserved->value,
                    ])
                    ->lockForUpdate()
                    ->first();

                if ($unit->status === UnitInventoryStatus::Reserved && $existingHold && $existingHold->hold_expires_at && $existingHold->hold_expires_at->isPast()) {
                    $existingHold->update([
                        'status' => ReservationStatus::Expired,
                        'expired_at' => now(),
                        'cancellation_reason' => 'Server-side expiration: 15-minute hold elapsed before conversion.',
                    ]);
                    $unit->update(['status' => UnitInventoryStatus::Available]);
                } else {
                    throw new DomainException(sprintf(
                        "Unit %s is not available for reservation (current status: %s).",
                        $unit->unit_number,
                        $unit->status->value
                    ));
                }
            }

            // 4. Verify no active hold exists for this unit
            $activeHold = UnitReservation::where('project_unit_id', $unit->id)
                ->whereIn('status', [
                    ReservationStatus::Requested->value,
                    ReservationStatus::PendingPayment->value,
                    ReservationStatus::Reserved->value,
                ])
                ->lockForUpdate()
                ->first();

            if ($activeHold) {
                if ($activeHold->hold_expires_at && $activeHold->hold_expires_at->isPast()) {
                    $activeHold->update([
                        'status' => ReservationStatus::Expired,
                        'expired_at' => now(),
                        'cancellation_reason' => 'Server-side expiration: 15-minute hold elapsed before conversion.',
                    ]);
                } else {
                    throw new DomainException(sprintf(
                        "Unit %s already has an active reservation hold (Code: %s).",
                        $unit->unit_number,
                        $activeHold->reservation_code
                    ));
                }
            }

            // 5. Authoritative price calculation from database
            $unitPrice = (float) $unit->price_etb;
            if ($unitPrice <= 0.0) {
                throw new DomainException("Unit does not have an authoritative price configured.");
            }

            $depositAmount = round(($unitPrice * $depositPercentage) / 100.0, 2);

            // 6. Payment Plan Snapshot
            $plan = null;
            if ($paymentPlanId) {
                $plan = DeveloperPaymentPlan::with('installments')->find($paymentPlanId);
            }
            if (! $plan) {
                $plan = $unit->project->defaultPaymentPlan();
            }

            $planSnapshot = $plan ? [
                'plan_id' => $plan->id,
                'name_en' => $plan->name_en,
                'version' => $plan->version,
                'installments' => $plan->installments->map(fn ($i) => [
                    'sequence_number' => $i->sequence_number,
                    'milestone_label_en' => $i->milestone_label_en,
                    'milestone_label_am' => $i->milestone_label_am,
                    'percentage' => (float) $i->percentage,
                    'trigger_milestone_code' => $i->trigger_milestone_code,
                    'due_days_after_trigger' => $i->due_days_after_trigger,
                ])->toArray(),
            ] : null;

            $now = now();
            $expiresAt = (clone $now)->addMinutes($holdMinutes);

            // 7. Create Reservation
            $reservation = UnitReservation::create([
                'idempotency_key' => $idempotencyKey,
                'project_unit_id' => $unit->id,
                'project_id' => $unit->project_id,
                'buyer_user_id' => $buyerUserId,
                'agent_user_id' => $agentUserId,
                'crm_lead_id' => $crmLeadId,
                'payment_plan_id' => $plan?->id,
                'payment_plan_snapshot_json' => $planSnapshot,
                'agreed_price_etb' => $unitPrice,
                'deposit_amount_etb' => $depositAmount,
                'deposit_percentage' => $depositPercentage,
                'status' => ReservationStatus::Reserved,
                'hold_expires_at' => $expiresAt,
                'requested_at' => $now,
                'reserved_at' => $now,
            ]);

            // 8. Update inventory status to Reserved
            $unit->update([
                'status' => UnitInventoryStatus::Reserved,
            ]);

            return $reservation;
        });
    }
}
