<?php

declare(strict_types=1);

namespace App\Domains\Payment\Services\Adapters;

use App\Domains\Payment\Contracts\PaymentProviderAdapterInterface;
use App\Domains\Payment\Models\Payment;

final class CbeBirrAdapter implements PaymentProviderAdapterInterface
{
    private array $config;

    public function __construct()
    {
        $this->config = config('services.cbe_birr', []);
    }

    public function initiatePayment(Payment $payment, array $options = []): array
    {
        if (empty($this->config['enabled']) || empty($this->config['merchant_id'])) {
            return [
                'status' => 'scaffolded',
                'action_type' => 'ussd_or_push',
                'payment_url' => null,
                'provider_reference' => 'CBE-SCAF-' . $payment->reference_code,
                'instructions' => 'CBE Birr merchant credentials are not yet configured in production environment. Adapter scaffold ready.',
            ];
        }

        return [
            'status' => 'pending_provider_handoff',
            'action_type' => 'ussd_push',
            'merchant_id' => $this->config['merchant_id'],
            'provider_reference' => 'CBE-' . bin2hex(random_bytes(12)),
            'instructions' => 'Authorize payment push request on your CBE Birr mobile app or dial *847#.',
        ];
    }

    public function verifyTransaction(string $providerReference): array
    {
        return [
            'provider_reference' => $providerReference,
            'status' => 'pending',
            'verified' => false,
            'message' => 'CBE Birr transaction status check pending external merchant gateway connectivity.',
        ];
    }

    public function verifyWebhookSignature(array $headers, string $rawPayload, string $signature): bool
    {
        $secret = config('services.cbe_birr.app_secret') ?: ($this->config['app_secret'] ?? ($this->config['app_key'] ?? ''));
        if (empty($secret) && empty($signature)) {
            return false;
        }

        $computed = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($computed, $signature);
    }

    public function normalizeWebhookPayload(array $rawPayload): array
    {
        return [
            'provider' => 'cbe_birr',
            'provider_event_id' => (string) ($rawPayload['cbe_txn_id'] ?? $rawPayload['eventId'] ?? uniqid('cbe_evt_')),
            'event_type' => (string) ($rawPayload['event_type'] ?? 'cbe.payment.success'),
            'transaction_reference' => (string) ($rawPayload['reference'] ?? $rawPayload['order_id'] ?? ''),
            'provider_transaction_reference' => (string) ($rawPayload['cbe_txn_id'] ?? ''),
            'amount_etb' => (float) ($rawPayload['amount'] ?? 0.0),
            'currency' => 'ETB',
            'status' => in_array(strtolower((string) ($rawPayload['status'] ?? '')), ['success', 'completed', 'paid'], true)
                ? 'successful'
                : 'failed',
            'raw' => $rawPayload,
        ];
    }

    public function refund(Payment $payment, float $amount, string $reason): array
    {
        return [
            'status' => 'unsupported_by_mock',
            'refund_id' => null,
            'message' => 'CBE Birr direct automated refund API requires active merchant clearance.',
        ];
    }
}

