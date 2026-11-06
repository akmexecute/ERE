<?php

declare(strict_types=1);

namespace App\Domains\Payment\Actions;

use App\Domains\Payment\Enums\BankTransferVerificationStatus;
use App\Domains\Payment\Models\BankTransferProof;
use App\Domains\Payment\Models\Payment;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class SubmitBankTransferSlipAction
{
    /**
     * Submit a bank deposit slip for an existing bank_transfer payment intent.
     * Stores file privately and initializes verification status to 'submitted'.
     */
    public function execute(
        Payment $payment,
        User $customer,
        UploadedFile $slipFile,
        array $details
    ): BankTransferProof {
        if ($payment->customer_user_id !== $customer->id && ! $customer->hasRole('super_admin')) {
            throw new DomainException('Unauthorized: You can only submit deposit slips for your own payments.');
        }

        // Validate file mime type and size (max 10MB, images or PDF)
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (! in_array($slipFile->getMimeType(), $allowedMimes, true)) {
            throw new DomainException('Invalid document type. Bank deposit slip must be a JPG, PNG, WEBP, or PDF file.');
        }

        // Store privately on the 'local' or 'private' disk
        $path = $slipFile->store('bank_slips/' . date('Y/m'), 'local');

        return DB::transaction(function () use ($payment, $customer, $path, $details) {
            $proof = BankTransferProof::query()->updateOrCreate(
                ['payment_id' => $payment->id],
                [
                    'transfer_reference' => $details['transfer_reference'] ?? $payment->reference_code,
                    'bank_name' => $details['bank_name'] ?? 'Commercial Bank of Ethiopia',
                    'account_number' => $details['account_number'] ?? null,
                    'account_holder_name' => $details['account_holder_name'] ?? $customer->name,
                    'deposited_amount_etb' => $details['deposited_amount_etb'] ?? $payment->total_amount_etb,
                    'deposited_at' => $details['deposited_at'] ?? now()->toDateString(),
                    'proof_file_path' => $path,
                    'notes' => $details['notes'] ?? null,
                    'verification_status' => BankTransferVerificationStatus::Submitted,
                ]
            );

            $proof->recordAuditEntry($customer->id, 'submitted', 'Bank deposit slip uploaded by customer.');
            $proof->save();

            return $proof;
        });
    }
}

