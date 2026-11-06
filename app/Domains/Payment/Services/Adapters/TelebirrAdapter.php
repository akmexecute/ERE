<?php

declare(strict_types=1);

namespace App\Domains\Payment\Services\Adapters;

use App\Domains\Payment\Contracts\PaymentProviderAdapterInterface;
use App\Domains\Payment\Models\Payment;
use RuntimeException;

final class TelebirrAdapter implements PaymentProviderAdapterInterface
{
    private array $config;

    public function __construct()
    {
        $this->config = config('services.telebirr', []);
    }

    public function initiatePayment(Payment $payment, array $options = []): array
    {
        if (empty($this->config['enabled']) || empty($this->config['app_id'])) {
            return [
                'status' => 'scaffolded',
                'action_type' => 'redirect_or_qr',
                'payment_url' => null,
                'provider_reference' => 'TB-SCAF-' . $payment->reference_code,
                'instructions' => 'Telebirr credentials are not yet configured in production environment. Adapter scaffold ready.',
            ];
        }

        // Real Telebirr API preparation
        $nonce = bin2hex(random_bytes(16));
        $timestamp = (string) (int) (microtime(true) * 1000);

        return [
            'status' => 'pending_provider_handoff',
            'action_type' => 'redirect',
            'payment_url' => $this->config['endpoint'] ?? 'https://app.ethiomobilemoney.et:2121',
            'provider_reference' => 'TB-' . $nonce,
            'timestamp' => $timestamp,
            'app_id' => $this->config['app_id'],
        ];
    }

    public function verifyTransaction(string $providerReference): array
    {
        return [
            'provider_reference' => $providerReference,
            'status' => 'pending',
            'verified' => false,
            'message' => 'Direct query pending external Telebirr gateway availability.',
        ];
    }

    public function verifyWebhookSignature(array $headers, string $rawPayload, string $signature): bool
    {
        $appKey = config('services.telebirr.app_key') ?: ($this->config['app_key'] ?? '');
        if (empty($appKey) && empty($signature)) {
            return false;
        }

        // SHA256 HMAC verification with constant-time comparison
        $computed = hash_hmac('sha256', $rawPayload, $appKey);

        return hash_equals($computed, $signature);
    }

    public function normalizeWebhookPayload(array $rawPayload): array
    {
        return [
            'provider' => 'telebirr',
            'provider_event_id' => (string) ($rawPayload['msisdn'] ?? $rawPayload['outTradeNo'] ?? $rawPayload['tradeNo'] ?? uniqid('tb_evt_')),
            'event_type' => $rawPayload['tradeStatus'] ?? 'PAYMENT_COMPLETED',
            'transaction_reference' => (string) ($rawPayload['outTradeNo'] ?? $rawPayload['reference_code'] ?? ''),
            'provider_transaction_reference' => (string) ($rawPayload['tradeNo'] ?? $rawPayload['transactionId'] ?? ''),
            'amount_etb' => (float) ($rawPayload['totalAmount'] ?? $rawPayload['amount'] ?? 0.0),
            'currency' => 'ETB',
            'status' => in_array(strtoupper((string) ($rawPayload['tradeStatus'] ?? '')), ['COMPLETED', 'SUCCESS', 'SUCCESSFUL', 'PAID'], true)
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
            'message' => 'Telebirr automated direct refunds require dedicated commercial B2B API contracts.',
        ];
    }
}

