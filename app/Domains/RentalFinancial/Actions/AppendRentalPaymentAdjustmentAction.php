<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Actions;

use App\Domains\RentalFinancial\Enums\RentalPaymentStatus;
use App\Domains\RentalFinancial\Models\RentalPayment;
use App\Domains\RentalFinancial\Models\RentalPaymentAdjustment;
use App\Domains\RentalFinancial\Models\RentSchedule;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AppendRentalPaymentAdjustmentAction
{
    /**
     * Appends an immutable, signed financial adjustment to a rental payment (SRS Section 189).
     * Strictly preserves the original payment amount and reconciles the affected rent schedule.
     */
    public function execute(
        User $actor,
        RentalPayment $payment,
        float $signedAdjustmentAmount,
        string $mandatoryReason
    ): RentalPaymentAdjustment {
        // 1. Authorization: Finance Officer, Property Admin, Super Admin, or authorized Landlord
        $lease = $payment->lease;
        $isLandlord = ($actor->id === $lease->landlord_id);
        $isCompany = ($lease->company_id && $actor->company_id === $lease->company_id);
        $isStaff = $actor->hasAnyRole(['finance_officer', 'property_admin', 'platform_admin', 'super_admin']);

        if (! $isLandlord && ! $isCompany && ! $isStaff) {
            throw new AuthorizationException('You are not authorized to record financial adjustments for this payment.');
        }

        // 2. Reason validation (mandatory)
        $trimmedReason = trim($mandatoryReason);
        if (mb_strlen($trimmedReason) < 5) {
            throw ValidationException::withMessages([
                'reason' => ['A detailed justification of at least 5 characters is mandatory for all financial adjustments.'],
            ]);
        }

        $signedAdjustment = round($signedAdjustmentAmount, 2);
        if ($signedAdjustment === 0.00) {
            throw ValidationException::withMessages([
                'adjustment_amount_etb' => ['Adjustment amount cannot be zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $payment,
            $lease,
            $signedAdjustment,
            $trimmedReason
        ): RentalPaymentAdjustment {
            /** @var RentalPayment $lockedPayment */
            $lockedPayment = RentalPayment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== RentalPaymentStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'payment' => ['Adjustments can only be appended to confirmed rental payments.'],
                ]);
            }

            // Prevent negative effective payment balance
            $currentEffective = $lockedPayment->effectiveAmountEtb();
            $newEffective = round($currentEffective + $signedAdjustment, 2);
            if ($newEffective < 0.00) {
                throw ValidationException::withMessages([
                    'adjustment_amount_etb' => [
                        sprintf('Adjustment of ETB %s would result in a negative effective payment amount (ETB %s).', number_format($signedAdjustment, 2), number_format($newEffective, 2)),
                    ],
                ]);
            }

            /** @var RentSchedule $lockedSchedule */
            $lockedSchedule = RentSchedule::query()
                ->where('id', $lockedPayment->rent_schedule_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 3. Insert append-only adjustment record
            // Original payment amount in rental_payments is NEVER touched or overwritten!
            $adjustment = RentalPaymentAdjustment::query()->create([
                'uuid' => (string) Str::uuid(),
                'rental_payment_id' => $lockedPayment->id,
                'rent_schedule_id' => $lockedSchedule->id,
                'lease_id' => $lease->id,
                'adjustment_amount_etb' => $signedAdjustment,
                'reason' => $trimmedReason,
                'authorized_by_user_id' => $actor->id,
                'created_at' => now(),
            ]);

            // 4. Reconcile rent schedule financials
            $lockedSchedule->reconcileFinancials();

            return $adjustment;
        });
    }
}
