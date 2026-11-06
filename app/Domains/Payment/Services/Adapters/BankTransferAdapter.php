<?php

declare(strict_types=1);

namespace App\Domains\Payment\Services\Adapters;

use App\Domains\Payment\Contracts\PaymentProviderAdapterInterface;
use App\Domains\Payment\Models\Payment;

final class BankTransferAdapter implements PaymentProviderAdapterInterface
{
    public function initiatePayment(Payment $payment, array $options = []): array
    {
        return [
            'status' => 'pending_slip_submission',
            'action_type' => 'manual_transfer',
            'bank_accounts' => [
                [
                    'bank_name' => 'Commercial Bank of Ethiopia (CBE)',
                    'account_name' => 'ERE Marketplace Escrow Trust',
                    'account_number' => '1000293847561',
                    'branch' => 'Bole Medhanealem Branch',
                    'swift' => 'CBETETAA',
                ],
                [
                    'bank_name' => 'Awash International Bank',
                    'account_name' => 'ERE Real Estate Escrow',
                    'account_number' => '01425896321400',
                    'branch' => 'Kazanchis Branch',
                    'swift' => 'AWINETAA',
                ],
            ],
            'instructions' => "Deposit or transfer the exact amount of ETB {$payment->total_amount_etb} using Reference Code [{$payment->reference_code}]. Keep your bank deposit receipt/slip and upload it via the checkout screen.",
            'provider_reference' => 'BNK-REF-' . $payment->reference_code,
        ];
    }

    public function verifyTransaction(string $providerReference): array
    {
        return [
            'provider_reference' => $providerReference,
            'status' => 'manual_verification_required',
            'verified' => false,
            'message' => 'Bank transfers require an authorized officer to inspect the uploaded deposit slip and confirm bank statement credit.',
        ];
    }

    public function verifyWebhookSignature(array $headers, string $rawPayload, string $signature): bool
    {
        // Manual transfers don't use webhooks; verified via internal officer action
        return false;
    }

    public function normalizeWebhookPayload(array $rawPayload): array
    {
        return [
            'provider' => 'bank_transfer',
            'provider_event_id' => uniqid('bnk_evt_'),
            'event_type' => 'manual_review',
            'transaction_reference' => '',
            'provider_transaction_reference' => '',
            'amount_etb' => 0.0,
            'currency' => 'ETB',
            'status' => 'pending',
            'raw' => $rawPayload,
        ];
    }

    public function refund(Payment $payment, float $amount, string $reason): array
    {
        return [
            'status' => 'manual_payout_required',
            'refund_id' => null,
            'message' => 'Manual bank transfer refunds must be executed via direct banking settlement and recorded in financial adjustments.',
        ];
    }
}

