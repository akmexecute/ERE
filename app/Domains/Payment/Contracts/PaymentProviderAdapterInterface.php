<?php

declare(strict_types=1);

namespace App\Domains\Payment\Contracts;

use App\Domains\Payment\Models\Payment;

interface PaymentProviderAdapterInterface
{
    /**
     * Initiate payment with the external provider or prepare local instructions.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function initiatePayment(Payment $payment, array $options = []): array;

    /**
     * Query or verify the transaction status directly with the provider.
     *
     * @return array<string, mixed>
     */
    public function verifyTransaction(string $providerReference): array;

    /**
     * Authenticate an incoming webhook request using provider signature specifications.
     *
     * @param  array<string, mixed>  $headers
     */
    public function verifyWebhookSignature(array $headers, string $rawPayload, string $signature): bool;

    /**
     * Normalize the provider-specific callback payload into standard ERE attributes:
     * - provider_event_id
     * - event_type
     * - transaction_reference
     * - provider_transaction_reference
     * - amount_etb
     * - currency
     * - status
     *
     * @param  array<string, mixed>  $rawPayload
     * @return array<string, mixed>
     */
    public function normalizeWebhookPayload(array $rawPayload): array;

    /**
     * Initiate an external refund or reversal where supported by provider.
     *
     * @return array<string, mixed>
     */
    public function refund(Payment $payment, float $amount, string $reason): array;
}

