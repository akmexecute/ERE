<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use App\Domains\Developer\Models\ContractInstallment;
use App\Domains\Developer\Models\ContractPaymentTransaction;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class VerifyManualInstallmentPaymentAction
{
    /**
     * Authoritatively verifies (approves or rejects) a submitted manual payment transaction.
     * Only approved payments update the contract and installment financial state.
     */
    public function execute(
        int $transactionId,
        bool $approved,
        User $verifier,
        ?string $decisionNotes = null,
        ?string $rejectionReason = null
    ): ContractPaymentTransaction {
        return DB::transaction(function () use ($transactionId, $approved, $verifier, $decisionNotes, $rejectionReason): ContractPaymentTransaction {
            /** @var ContractPaymentTransaction $transaction */
            $transaction = ContractPaymentTransaction::with(['contract.project.developer.company', 'installment'])
                ->where('id', $transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status !== ContractPaymentTransaction::STATUS_PENDING_VERIFICATION) {
                throw new DomainException(sprintf(
                    "Payment transaction #%s cannot be verified because its current status is '%s'.",
                    $transaction->transaction_reference,
                    $transaction->status
                ));
            }

            // Authorization check
            $isSuperAdmin = method_exists($verifier, 'hasRole') && $verifier->hasRole('super_admin');
            $developerCompany = $transaction->contract->project->developer->company;
            $isCompanyOwner = (int) $developerCompany->owner_user_id === (int) $verifier->id;
            $isDeveloperUser = (int) $transaction->contract->project->developer->user_id === (int) $verifier->id;

            if (! $isSuperAdmin && ! $isCompanyOwner && ! $isDeveloperUser) {
                throw new DomainException("Unauthorized: Only authorized developer officers or administrators may verify payment claims.");
            }

            if (! $approved) {
                if (empty($rejectionReason)) {
                    throw new InvalidArgumentException("A rejection reason must be specified when declining a payment claim.");
                }

                $transaction->update([
                    'status' => ContractPaymentTransaction::STATUS_REJECTED,
                    'verified_by_user_id' => $verifier->id,
                    'verified_at' => now(),
                    'verification_notes' => $decisionNotes,
                    'rejection_reason' => $rejectionReason,
                ]);

                return $transaction;
            }

            // Approved Payment: Settle Installment & Update Contract Balances
            $amount = (float) $transaction->amount_etb;

            /** @var ContractInstallment $installment */
            $installment = ContractInstallment::where('id', $transaction->installment_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var DeveloperSalesContract $contract */
            $contract = DeveloperSalesContract::where('id', $transaction->contract_id)
                ->lockForUpdate()
                ->firstOrFail();

            $prevPaid = (float) $installment->paid_amount_etb;
            $newPaid = round($prevPaid + $amount, 2);
            $newOutstanding = max(0.0, round((float) $installment->amount_etb - $newPaid, 2));
            $newStatus = ($newOutstanding <= 0.0) ? 'paid' : 'partially_paid';

            $installment->update([
                'paid_amount_etb' => $newPaid,
                'outstanding_balance_etb' => $newOutstanding,
                'status' => $newStatus,
                'paid_at' => ($newStatus === 'paid') ? now() : $installment->paid_at,
            ]);

            // Update contract totals
            $allPaidSoFar = (float) ContractInstallment::where('contract_id', $contract->id)->sum('paid_amount_etb');
            $contractPrice = (float) $contract->contract_price_etb;
            $newBalance = max(0.0, round($contractPrice - $allPaidSoFar, 2));

            $contract->update([
                'deposit_paid_etb' => $allPaidSoFar,
                'balance_payable_etb' => $newBalance,
            ]);

            $transaction->update([
                'status' => ContractPaymentTransaction::STATUS_VERIFIED,
                'verified_by_user_id' => $verifier->id,
                'verified_at' => now(),
                'verification_notes' => $decisionNotes,
            ]);

            return $transaction;
        });
    }
}

