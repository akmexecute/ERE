<?php

declare(strict_types=1);

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Developer\Models\ContractInstallment;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\Financial\Actions\ScheduleHostPayoutAction;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Invoice\Models\Invoice;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Enums\BankTransferVerificationStatus;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\BankTransferProof;
use App\Domains\Payment\Models\Payment;
use App\Domains\RentalFinancial\Models\RentSchedule;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReviewBankTransferAction
{
    public function __construct(
        private readonly DoubleEntryPostingService $postingService,
        private readonly ScheduleHostPayoutAction $schedulePayoutAction
    ) {}

    /**
     * Review an uploaded bank deposit slip by an authorized financial officer.
     */
    public function execute(
        BankTransferProof $proof,
        User $officer,
        string $decision, // 'verify' or 'reject'
        ?string $reason = null
    ): BankTransferProof {
        return DB::transaction(function () use ($proof, $officer, $decision, $reason) {
            $proof = BankTransferProof::query()->where('id', $proof->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->where('id', $proof->payment_id)->lockForUpdate()->firstOrFail();

            if ($decision === 'verify') {
                $proof->verification_status = BankTransferVerificationStatus::Verified;
                $proof->verified_by_user_id = $officer->id;
                $proof->verified_at = now();
                $proof->rejection_reason = null;
                $proof->recordAuditEntry($officer->id, 'verified', 'Deposit slip confirmed against bank statement.');
                $proof->save();

                // Transition payment to successful and mark allocated
                $payment->transitionTo(PaymentStatus::Successful);
                $payment->allocation_status = PurchaseAllocationStatus::Allocated;
                $payment->allocated_amount_etb = $payment->total_amount_etb;
                $payment->unapplied_amount_etb = '0.00';
                $payment->confirmed_at = now();
                $payment->save();

                // Post double-entry financial ledger
                match ($payment->payment_type) {
                    PaymentType::Booking => $this->postingService->postBookingPaymentCaptured($payment),
                    default => $this->postingService->postPropertyPurchasePayment($payment),
                };

                // Coordinate business record updates
                $this->coordinateBusinessRecordUpdate($payment);
            } elseif ($decision === 'reject') {
                if (empty($reason)) {
                    throw new DomainException('A rejection reason must be provided when rejecting a deposit slip.');
                }

                $proof->verification_status = BankTransferVerificationStatus::Rejected;
                $proof->verified_by_user_id = $officer->id;
                $proof->verified_at = now();
                $proof->rejection_reason = $reason;
                $proof->recordAuditEntry($officer->id, 'rejected', $reason);
                $proof->save();

                $payment->failure_reason = "Deposit slip rejected: {$reason}";
                $payment->save();
            } else {
                throw new DomainException("Invalid review decision [{$decision}]. Must be 'verify' or 'reject'.");
            }

            return $proof;
        });
    }

    private function coordinateBusinessRecordUpdate(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof Booking) {
            $payable->status = \App\Domains\Booking\Enums\BookingStatus::Confirmed;
            $payable->confirmed_at = now();
            $payable->save();

            $this->schedulePayoutAction->execute($payable);
        } elseif ($payable instanceof PropertyOffer) {
            $payable->syncFinancialBalanceSnapshot();
        } elseif ($payable instanceof DeveloperSalesContract) {
            $payable->syncFinancialBalanceSnapshot();
        } elseif ($payable instanceof ContractInstallment) {
            $payable->syncFinancialBalanceSnapshot();
            $payable->contract?->syncFinancialBalanceSnapshot();
        } elseif ($payable instanceof UnitReservation) {
            $payable->contract?->syncFinancialBalanceSnapshot();
        } elseif ($payable instanceof RentSchedule) {
            $payable->confirmed_paid_etb = (float) $payable->confirmed_paid_etb + (float) $payment->total_amount_etb;
            $payable->outstanding_balance_etb = max(0.0, (float) $payable->net_amount_due_etb - (float) $payable->confirmed_paid_etb);
            $payable->status = $payable->outstanding_balance_etb <= 0.0 ? 'paid' : 'partially_paid';
            if ($payable->status === 'paid') {
                $payable->paid_at = now();
            }
            $payable->save();
        } elseif ($payable instanceof Invoice) {
            $payable->recordPayment((float) $payment->total_amount_etb, $payment->reference_code);
        }
    }
}
