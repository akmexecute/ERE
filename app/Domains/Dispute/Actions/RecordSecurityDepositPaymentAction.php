<?php

declare(strict_types=1);

namespace App\Domains\Dispute\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ValidationException;

final readonly class RecordSecurityDepositPaymentAction
{
    /**
     * Record or verify a security deposit payment.
     *
     * @throws InvalidArgumentException
     * @throws AuthorizationException
     */
    public function execute(
        Booking $booking,
        float $depositAmountEtb,
        string $paymentMethod = 'system_managed',
        ?string $paymentReference = null,
        ?User $verifier = null
    ): SecurityDeposit {
        if ($depositAmountEtb <= 0) {
            throw new InvalidArgumentException('Security deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $booking,
            $depositAmountEtb,
            $paymentMethod,
            $paymentReference,
            $verifier
        ): SecurityDeposit {
            /** @var SecurityDeposit $deposit */
            $deposit = SecurityDeposit::query()->firstOrNew(['booking_id' => $booking->id]);

            $now = CarbonImmutable::now();

            if (! $deposit->exists) {
                $deposit->uuid = (string) Str::uuid();
                $deposit->host_id = $booking->host_id;
                $deposit->guest_user_id = $booking->guest_user_id;
                $deposit->held_amount_etb = $depositAmountEtb;
                $deposit->deducted_amount_etb = 0.00;
                $deposit->released_to_guest_etb = 0.00;
            }

            if ($paymentMethod === 'system_managed') {
                if (empty($paymentReference)) {
                    throw new InvalidArgumentException('System-managed payment requires a valid provider transaction reference.');
                }
                $deposit->payment_method = 'system_managed';
                $deposit->payment_status = SecurityDeposit::PAYMENT_STATUS_HELD;
                $deposit->status = SecurityDeposit::STATUS_HELD;
                $deposit->custody_provider = 'licensed_escrow_partner';
                $deposit->custody_transaction_ref = $paymentReference;
                $deposit->payment_reference = $paymentReference;
            } else {
                // Manual payment workflow (Telebirr, CBE Birr, Bank transfer)
                $deposit->payment_method = $paymentMethod;
                $deposit->payment_reference = $paymentReference;
                $deposit->custody_provider = 'manual_host_custody';

                if ($verifier !== null) {
                    // Prevent duplicate verification
                    if ($deposit->verified_at !== null) {
                        throw new InvalidArgumentException('Manual deposit payment has already been verified.');
                    }

                    // Authorized receipt verification
                    $isHost = (int) $booking->host?->user_id === (int) $verifier->id || (int) $booking->property?->owner_id === (int) $verifier->id;
                    $isStaff = $verifier->isStaff();

                    if (! $isHost && ! $isStaff) {
                        throw new AuthorizationException('Only an authorized host or staff member can confirm receipt of a manual deposit.');
                    }

                    $deposit->payment_status = SecurityDeposit::PAYMENT_STATUS_HELD;
                    $deposit->status = SecurityDeposit::STATUS_HELD;
                    $deposit->verified_by_user_id = $verifier->id;
                    $deposit->verified_at = $now;
                } else {
                    // Guest submitted slip/reference, pending verification
                    $deposit->payment_status = SecurityDeposit::PAYMENT_STATUS_PENDING;
                    $deposit->status = SecurityDeposit::STATUS_PENDING;
                }
            }

            $deposit->save();

            return $deposit;
        });
    }
}

