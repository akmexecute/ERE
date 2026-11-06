<?php

declare(strict_types=1);

namespace App\Domains\Payment\Services\Adapters;

use App\Domains\Payment\Contracts\PaymentProviderAdapterInterface;
use App\Domains\Payment\Models\Payment;

final class ChapaAdapter implements PaymentProviderAdapterInterface
{
    private array $config;

    public function __construct()
    {
        $this->config = config('services.chapa', []);
    }

    public function initiatePayment(Payment $payment, array $options = []): array
    {
        if (empty($this->config['enabled']) || empty($this->config['secret_key'])) {
            return [
                'status' => 'scaffolded',
                'action_type' => 'hosted_checkout',
                'checkout_url' => null,
                'provider_reference' => 'CHP-SCAF-' . $payment->reference_code,
                'instructions' => 'Chapa API credentials are not yet configured in production environment. Adapter scaffold ready.',
            ];
        }

        return [
            'status' => 'pending_provider_handoff',
            'action_type' => 'redirect',
            'checkout_url' => ($this->config['endpoint'] ?? 'https://api.chapa.co/v1') . '/hosted/pay',
            'provider_reference' => 'CHP-' . bin2hex(random_bytes(10)),
            'public_key' => $this->config['public_key'] ?? '',
        ];
    }

    public function verifyTransaction(string $providerReference): array
    {
        return [
            'provider_reference' => $providerReference,
            'status' => 'pending',
            'verified' => false,
            'message' => 'Chapa direct verification query pending live network connection.',
        ];
    }

    public function verifyWebhookSignature(array $headers, string $rawPayload, string $signature): bool
    {
        $secret = $this->config['webhook_secret'] ?? $this->config['secret_key'] ?? '';
        if (empty($secret) && empty($signature)) {
            return false;
        }

        // Chapa sends HMAC-SHA256 signature in 'x-chapa-signature' or 'chapa-signature'
        $computed = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($computed, $signature);
    }

    public function normalizeWebhookPayload(array $rawPayload): array
    {
        return [
            'provider' => 'chapa',
            'provider_event_id' => (string) ($rawPayload['event_id'] ?? $rawPayload['reference'] ?? uniqid('chp_evt_')),
            'event_type' => (string) ($rawPayload['event'] ?? 'charge.complete'),
            'transaction_reference' => (string) ($rawPayload['tx_ref'] ?? $rawPayload['reference'] ?? ''),
            'provider_transaction_reference' => (string) ($rawPayload['reference'] ?? $rawPayload['transaction_id'] ?? ''),
            'amount_etb' => (float) ($rawPayload['amount'] ?? 0.0),
            'currency' => (string) ($rawPayload['currency'] ?? 'ETB'),
            'status' => in_array(strtolower((string) ($rawPayload['status'] ?? '')), ['success', 'completed'], true)
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
            'message' => 'Chapa automated refund requires merchant balance clearance.',
        ];
    }
}

