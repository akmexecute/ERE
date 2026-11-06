<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use App\Domains\Developer\Models\ContractInstallment;
use App\Domains\Developer\Models\ContractPaymentTransaction;
use App\Domains\Developer\Models\DeveloperPaymentAccount;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\User\Models\User;
use DomainException;
use InvalidArgumentException;

final readonly class SubmitManualInstallmentPaymentAction
{
    /**
     * Records a buyer's submitted manual bank transfer payment claim.
     * The payment is saved in 'pending_verification' status and DOES NOT settle the installment automatically.
     */
    public function execute(
        int $contractId,
        int $installmentId,
        int $paymentAccountId,
        float $amountEtb,
        string $transferReference,
        User $payer,
        ?string $receiptUrl = null,
        ?int $receiptDocumentId = null,
        ?string $notes = null
    ): ContractPaymentTransaction {
        if ($amountEtb <= 0.0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        if (trim($transferReference) === '') {
            throw new InvalidArgumentException("Bank transaction reference or deposit slip number is required.");
        }

        /** @var DeveloperSalesContract $contract */
        $contract = DeveloperSalesContract::findOrFail($contractId);

        // Buyer authorization check
        if ((int) $contract->buyer_user_id !== (int) $payer->id && (! method_exists($payer, 'hasRole') || ! $payer->hasRole('super_admin'))) {
            throw new DomainException("Unauthorized: Only the contracted buyer may submit payment claims for this contract.");
        }

        /** @var ContractInstallment $installment */
        $installment = ContractInstallment::where('id', $installmentId)
            ->where('contract_id', $contract->id)
            ->firstOrFail();

        /** @var DeveloperPaymentAccount $account */
        $account = DeveloperPaymentAccount::where('id', $paymentAccountId)
            ->where('is_active', true)
            ->firstOrFail();

        // Check for duplicate pending claim with identical transfer reference
        $existing = ContractPaymentTransaction::where('transfer_reference', $transferReference)
            ->whereIn('status', [ContractPaymentTransaction::STATUS_PENDING_VERIFICATION, ContractPaymentTransaction::STATUS_VERIFIED])
            ->first();

        if ($existing) {
            throw new DomainException("A payment claim with transfer reference '{$transferReference}' has already been submitted.");
        }

        return ContractPaymentTransaction::create([
            'contract_id' => $contract->id,
            'installment_id' => $installment->id,
            'reservation_id' => $contract->reservation_id,
            'payer_user_id' => $payer->id,
            'payment_method' => 'manual_bank_transfer',
            'payment_account_id' => $account->id,
            'amount_etb' => $amountEtb,
            'transfer_reference' => $transferReference,
            'receipt_url' => $receiptUrl,
            'receipt_document_id' => $receiptDocumentId,
            'status' => ContractPaymentTransaction::STATUS_PENDING_VERIFICATION,
            'metadata_json' => [
                'payer_notes' => $notes,
                'submitted_at' => now()->toIso8601String(),
                'destination_bank' => $account->institution_name,
                'destination_account' => $account->account_number,
            ],
        ]);
    }
}

