<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Actions\InitiatePaymentAction;
use App\Domains\Payment\Actions\ProcessWebhookEventAction;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SimulateConcurrentPurchasePaymentCommand extends Command
{
    protected $signature = 'ere:simulate-concurrent-payment
        {action : Action to perform: initiate, webhook, refund}
        {--payable-type= : Polymorphic payable type}
        {--payable-id= : Polymorphic payable ID}
        {--user-id= : Customer user ID}
        {--amount= : Payment amount in ETB}
        {--method=telebirr : Payment method}
        {--idempotency= : Idempotency key}
        {--payment-id= : Payment ID for webhook or refund}
        {--trade-status=COMPLETED : Webhook trade status}
        {--delay-ms=0 : Milliseconds to wait before executing}
        {--hold-lock-ms=0 : Milliseconds to hold lock inside transaction}';

    protected $description = 'Execute a genuine concurrent payment transaction in a separate process for multi-process concurrency verification.';

    public function handle(
        InitiatePaymentAction $initiateAction,
        ProcessWebhookEventAction $webhookAction,
        DoubleEntryPostingService $postingService
    ): int {
        $delayMs = (int) $this->option('delay-ms');
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        $action = $this->argument('action');

        try {
            if ($action === 'initiate') {
                return $this->handleInitiate($initiateAction);
            }

            if ($action === 'webhook') {
                return $this->handleWebhook($webhookAction);
            }

            if ($action === 'refund') {
                return $this->handleRefund($postingService);
            }

            $this->outputJson([
                'success' => false,
                'error' => "Unknown action [{$action}].",
            ]);

            return 1;
        } catch (Throwable $e) {
            $this->outputJson([
                'success' => false,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            return 1;
        }
    }

    private function handleInitiate(InitiatePaymentAction $initiateAction): int
    {
        $payableType = (string) $this->option('payable-type');
        $payableId = (int) $this->option('payable-id');
        $userId = (int) $this->option('user-id');
        $amount = (float) $this->option('amount');
        $method = (string) $this->option('method');
        $idempotency = $this->option('idempotency') ?: null;
        $holdLockMs = (int) $this->option('hold-lock-ms');

        $user = User::query()->findOrFail($userId);
        $payable = $payableType::query()->findOrFail($payableId);

        $params = [
            'payable' => $payable,
            'payment_method' => PaymentMethod::from($method),
            'idempotency_key' => $idempotency,
            'custom_amount_etb' => $amount,
            'payment_type' => 'property_purchase',
        ];

        if ($holdLockMs > 0) {
            // Simulate slow external network call while holding agreement lock
            $result = DB::transaction(function () use ($initiateAction, $user, $params, $holdLockMs) {
                $res = $initiateAction->execute($user, $params);
                usleep($holdLockMs * 1000);

                return $res;
            });
        } else {
            $result = $initiateAction->execute($user, $params);
        }

        $this->outputJson([
            'success' => true,
            'action' => 'initiate',
            'payment_id' => $result['payment']->id,
            'reference_code' => $result['payment']->reference_code,
            'amount_etb' => (float) $result['payment']->total_amount_etb,
            'status' => $result['payment']->status->value,
            'allocation_status' => $result['payment']->allocation_status?->value ?? 'unallocated',
            'is_duplicate' => $result['is_duplicate_replay'],
        ]);

        return 0;
    }

    private function handleWebhook(ProcessWebhookEventAction $webhookAction): int
    {
        $paymentId = (int) $this->option('payment-id');
        $tradeStatus = (string) $this->option('trade-status');
        $payment = Payment::query()->findOrFail($paymentId);

        $appKey = 'telebirr_mock_signing_key_secret_2026';
        $payload = [
            'msisdn' => 'EVT-PROC-' . uniqid(),
            'tradeStatus' => $tradeStatus,
            'outTradeNo' => $payment->reference_code,
            'tradeNo' => 'TB-TXN-' . uniqid(),
            'totalAmount' => (float) $payment->total_amount_etb,
            'tradeTime' => now()->toIso8601String(),
        ];
        $rawBody = (string) json_encode($payload);
        $sig = hash_hmac('sha256', $rawBody, $appKey);

        $result = $webhookAction->execute(
            provider: 'telebirr',
            headers: ['x-signature' => $sig],
            rawBody: $rawBody,
            rawPayload: $payload,
            signature: $sig
        );

        $payment->refresh();

        $this->outputJson([
            'success' => true,
            'action' => 'webhook',
            'webhook_status' => $result['status'],
            'payment_id' => $payment->id,
            'payment_status' => $payment->status->value,
            'allocation_status' => $payment->allocation_status?->value,
            'allocated_amount_etb' => (string) $payment->allocated_amount_etb,
            'unapplied_amount_etb' => (string) $payment->unapplied_amount_etb,
            'reconciliation_notes' => $payment->reconciliation_notes,
        ]);

        return 0;
    }

    private function handleRefund(DoubleEntryPostingService $postingService): int
    {
        $paymentId = (int) $this->option('payment-id');
        $userId = (int) $this->option('user-id');
        $payment = Payment::query()->findOrFail($paymentId);
        $user = User::query()->find($userId);

        $amount = (float) $this->option('amount') ?: (float) $payment->total_amount_etb;

        $posting = $postingService->postPaymentRefund(
            payment: $payment,
            refundAmount: $amount,
            reason: 'Concurrent refund test',
            actor: $user
        );

        $payment->refresh();

        $this->outputJson([
            'success' => true,
            'action' => 'refund',
            'posting_group_id' => $posting->id,
            'payment_status' => $payment->status->value,
            'allocation_status' => $payment->allocation_status?->value,
            'refunded_amount_etb' => (string) $payment->refunded_amount_etb,
        ]);

        return 0;
    }

    private function outputJson(array $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

