<?php

declare(strict_types=1);

namespace App\Domains\Payment\Actions;

use App\Domains\Booking\Actions\ConfirmBookingAction;
use App\Domains\Booking\Models\Booking;
use App\Domains\Developer\Models\ContractInstallment;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\Financial\Actions\ScheduleHostPayoutAction;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Invoice\Models\Invoice;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\WebhookEvent;
use App\Domains\Payment\Services\PaymentManager;
use App\Domains\RentalFinancial\Models\RentSchedule;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProcessWebhookEventAction
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
        private readonly DoubleEntryPostingService $postingService,
        private readonly ScheduleHostPayoutAction $schedulePayoutAction
    ) {}

    /**
     * Process an incoming authenticated payment webhook event idempotently.
     *
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $rawPayload
     * @return array{
     *     status: string,
     *     event_id: string,
     *     payment_id: ?int,
     *     message: string
     * }
     */
    public function execute(string $provider, array $headers, string $rawBody, array $rawPayload, ?string $signature = null): array
    {
        $adapter = $this->paymentManager->adapterForProvider($provider);

        // 1. Verify HMAC / Signature
        $sig = $signature ?? $headers['x-signature'] ?? $headers['x-chapa-signature'] ?? $headers['signature'] ?? '';
        $isValidSignature = $adapter->verifyWebhookSignature($headers, $rawBody, (string) $sig);

        if (! $isValidSignature) {
            Log::warning("Unauthorized webhook received for provider [{$provider}]");
            throw new DomainException("Invalid webhook signature for provider [{$provider}].");
        }

        // 2. Normalize payload
        $normalized = $adapter->normalizeWebhookPayload($rawPayload);
        $providerEventId = $normalized['provider_event_id'];

        // 2b. Authoritative provider status query verification (where supported by provider integration)
        $lookupRef = (string) ($normalized['provider_transaction_reference'] ?: $normalized['transaction_reference']);
        if ($lookupRef !== '') {
            $providerCheck = $adapter->verifyTransaction($lookupRef);
            if (isset($providerCheck['verified']) && $providerCheck['verified'] === false && isset($providerCheck['status']) && $providerCheck['status'] === 'failed') {
                Log::warning("Authoritative provider verification query failed for reference [{$lookupRef}]");
                throw new DomainException("Authoritative provider verification rejected transaction reference [{$lookupRef}].");
            }
        }

        return DB::transaction(function () use ($provider, $providerEventId, $normalized, $rawPayload, $sig) {
            // 3. Database-backed event deduplication
            $existingEvent = WebhookEvent::query()
                ->where('provider', $provider)
                ->where('provider_event_id', $providerEventId)
                ->lockForUpdate()
                ->first();

            if ($existingEvent) {
                // Duplicate webhook event: already processed or currently recorded
                return [
                    'status' => 'duplicate_acknowledged',
                    'event_id' => $existingEvent->uuid,
                    'payment_id' => null,
                    'message' => 'Event already processed idempotently.',
                ];
            }

            // Record webhook event (sanitized)
            $webhookRecord = WebhookEvent::query()->create([
                'provider' => $provider,
                'provider_event_id' => $providerEventId,
                'event_type' => $normalized['event_type'],
                'transaction_reference' => $normalized['transaction_reference'],
                'signature' => (string) $sig,
                'payload_json' => $rawPayload,
                'status' => 'received',
            ]);

            // 4. Locate and lock target payment
            $payment = Payment::query()
                ->where('reference_code', $normalized['transaction_reference'])
                ->orWhere('provider_transaction_reference', $normalized['transaction_reference'])
                ->orWhere('idempotency_key', $normalized['transaction_reference'])
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                $webhookRecord->status = 'ignored';
                $webhookRecord->failure_reason = "No matching payment found for reference [{$normalized['transaction_reference']}].";
                $webhookRecord->save();

                return [
                    'status' => 'ignored',
                    'event_id' => $webhookRecord->uuid,
                    'payment_id' => null,
                    'message' => 'No matching payment transaction found.',
                ];
            }

            // 5. Amount & Currency Validation
            $capturedAmount = number_format((float) $normalized['amount_etb'], 2, '.', '');
            $expectedAmount = number_format((float) $payment->total_amount_etb, 2, '.', '');

            if (bccomp($capturedAmount, $expectedAmount, 2) !== 0) {
                $webhookRecord->status = 'failed';
                $webhookRecord->failure_reason = "Captured amount [{$capturedAmount}] does not match expected payment amount [{$expectedAmount}].";
                $webhookRecord->save();

                $payment->failure_reason = "Payment amount mismatch: provider sent {$capturedAmount} ETB, expected {$expectedAmount} ETB.";
                $payment->save();

                throw new DomainException("Financial reconciliation mismatch: Captured [{$capturedAmount}] != Expected [{$expectedAmount}].");
            }

            // 6. Handle Out-of-Order Lifecycle Transitions
            // If already successful, an older or duplicate event must not downgrade it
            if ($payment->status === PaymentStatus::Successful) {
                $webhookRecord->status = 'processed';
                $webhookRecord->processed_at = now();
                $webhookRecord->save();

                return [
                    'status' => 'already_successful',
                    'event_id' => $webhookRecord->uuid,
                    'payment_id' => $payment->id,
                    'message' => 'Payment already confirmed as successful.',
                ];
            }

            // 7. Transition Payment Status & Determine Agreement Allocatability
            if ($normalized['status'] === 'successful') {
                // Record provider event timestamp if present in payload
                if (! empty($normalized['raw']['tradeTime']) || ! empty($normalized['raw']['timestamp']) || ! empty($normalized['raw']['created_at'])) {
                    $rawTs = $normalized['raw']['tradeTime'] ?? $normalized['raw']['timestamp'] ?? $normalized['raw']['created_at'];
                    try {
                        $payment->provider_event_timestamp = \Illuminate\Support\Carbon::parse($rawTs);
                        $webhookRecord->provider_event_timestamp = $payment->provider_event_timestamp;
                    } catch (\Throwable) {}
                }

                // Lock canonical purchase agreement to protect against concurrent competing allocations
                $payable = $payment->payable;
                $lockedPayable = match (true) {
                    $payable instanceof PropertyOffer => PropertyOffer::query()->whereKey($payable->getKey())->lockForUpdate()->first(),
                    $payable instanceof DeveloperSalesContract => DeveloperSalesContract::query()->whereKey($payable->getKey())->lockForUpdate()->first(),
                    $payable instanceof ContractInstallment => tap(ContractInstallment::query()->whereKey($payable->getKey())->lockForUpdate()->first(), function ($inst): void {
                        if ($inst?->contract_id) {
                            DeveloperSalesContract::query()->whereKey($inst->contract_id)->lockForUpdate()->first();
                        }
                    }),
                    $payable instanceof \App\Domains\Developer\Models\UnitReservation => \App\Domains\Developer\Models\UnitReservation::query()->whereKey($payable->getKey())->lockForUpdate()->first(),
                    default => $payable,
                };

                // Check allocatability against current contractual state
                $canAllocate = true;
                $reconciliationReason = null;

                // When this payment's hold has expired, calculate active holds of other pending payments
                // to prevent an expired hold callback from consuming or corrupting a newer valid reservation
                $hasExpiredReservation = $payment->hasExpiredReservation();
                $otherActivePendingHolds = '0.00';
                if ($hasExpiredReservation && $lockedPayable && method_exists($lockedPayable, 'payments')) {
                    $otherActivePendingHolds = $lockedPayable->payments()
                        ->where('id', '!=', $payment->id)
                        ->where('status', PaymentStatus::Pending->value)
                        ->where(function ($q): void {
                            $q->where(function ($inner): void {
                                $inner->whereNull('reservation_expires_at')
                                    ->where('created_at', '>=', now()->subMinutes(30));
                            })->orWhere(function ($inner): void {
                                $inner->whereNotNull('reservation_expires_at')
                                    ->where('reservation_expires_at', '>=', now());
                            });
                        })
                        ->get()
                        ->reduce(fn(string $sum, Payment $p): string => bcadd($sum, number_format((float) $p->total_amount_etb, 2, '.', ''), 2), '0.00');
                }

                if ($lockedPayable instanceof PropertyOffer) {
                    if ($lockedPayable->status !== 'accepted') {
                        $canAllocate = false;
                        $reconciliationReason = "Property offer [#{$lockedPayable->offer_code}] is in '{$lockedPayable->status}' state and is not an active accepted purchase agreement.";
                    } elseif ($lockedPayable->expires_at && $lockedPayable->expires_at->isPast()) {
                        $canAllocate = false;
                        $reconciliationReason = "Accepted purchase offer [#{$lockedPayable->offer_code}] has expired.";
                    } elseif ($lockedPayable->property && in_array($lockedPayable->property->status, ['sold', 'withdrawn', 'archived'], true)) {
                        $canAllocate = false;
                        $reconciliationReason = "Underlying property is no longer in an eligible sale state (status: {$lockedPayable->property->status}).";
                    } else {
                        $remaining = $lockedPayable->calculateRemainingBalanceEtb();
                        $unreservedHeadroom = bcsub($remaining, $otherActivePendingHolds, 2);
                        if (bccomp($unreservedHeadroom, '0.00', 2) < 0) {
                            $unreservedHeadroom = '0.00';
                        }

                        if (bccomp($remaining, '0.00', 2) <= 0) {
                            $canAllocate = false;
                            $reconciliationReason = "Purchase agreement [#{$lockedPayable->offer_code}] was already fully paid by previous allocations (remaining balance: 0.00 ETB).";
                        } elseif ($hasExpiredReservation && bccomp((string) $payment->total_amount_etb, $unreservedHeadroom, 2) > 0) {
                            $canAllocate = false;
                            $reconciliationReason = "Late provider callback arrived after reservation hold expired while an active reservation is reserving remaining purchase headroom. Preserved in Account 2040 to protect active reservation from corruption.";
                        } elseif (bccomp((string) $payment->total_amount_etb, $remaining, 2) > 0) {
                            $canAllocate = false;
                            $reconciliationReason = "Late payment callback amount [{$payment->total_amount_etb} ETB] exceeds remaining contractual balance [{$remaining} ETB].";
                        }
                    }
                } elseif ($lockedPayable instanceof DeveloperSalesContract) {
                    $remaining = $lockedPayable->calculateRemainingBalanceEtb();
                    $unreservedHeadroom = bcsub($remaining, $otherActivePendingHolds, 2);
                    if (bccomp($unreservedHeadroom, '0.00', 2) < 0) {
                        $unreservedHeadroom = '0.00';
                    }

                    if (bccomp($remaining, '0.00', 2) <= 0) {
                        $canAllocate = false;
                        $reconciliationReason = "Developer sales contract [#{$lockedPayable->contract_code}] was already fully paid (remaining balance: 0.00 ETB).";
                    } elseif ($hasExpiredReservation && bccomp((string) $payment->total_amount_etb, $unreservedHeadroom, 2) > 0) {
                        $canAllocate = false;
                        $reconciliationReason = "Late provider callback arrived after reservation hold expired while an active reservation is reserving remaining purchase headroom. Preserved in Account 2040 to protect active reservation from corruption.";
                    } elseif (bccomp((string) $payment->total_amount_etb, $remaining, 2) > 0) {
                        $canAllocate = false;
                        $reconciliationReason = "Payment amount [{$payment->total_amount_etb} ETB] exceeds remaining contract balance [{$remaining} ETB].";
                    }
                } elseif ($lockedPayable instanceof ContractInstallment) {
                    $remaining = $lockedPayable->calculateOutstandingBalanceEtb();
                    if (bccomp($remaining, '0.00', 2) <= 0) {
                        $canAllocate = false;
                        $reconciliationReason = "Contract installment was already fully settled.";
                    } elseif (bccomp((string) $payment->total_amount_etb, $remaining, 2) > 0) {
                        $canAllocate = false;
                        $reconciliationReason = "Payment amount exceeds remaining installment balance.";
                    }
                }

                $payment->transitionTo(PaymentStatus::Successful);
                $payment->provider_transaction_reference = $normalized['provider_transaction_reference'] ?: $payment->provider_transaction_reference;
                $payment->confirmed_at = now();

                if ($canAllocate) {
                    $payment->allocation_status = PurchaseAllocationStatus::Allocated;
                    $payment->allocated_amount_etb = $payment->total_amount_etb;
                    $payment->unapplied_amount_etb = '0.00';
                    $payment->save();

                    // 8. Post double-entry financial ledger entries exactly once
                    $this->postFinancialLedgerForPayment($payment);

                    // 9. Coordinate business record confirmation
                    $this->coordinateBusinessRecordUpdate($payment);

                    $webhookRecord->status = 'processed';
                    $webhookRecord->processed_at = now();
                    $webhookRecord->save();

                    return [
                        'status' => 'success',
                        'event_id' => $webhookRecord->uuid,
                        'payment_id' => $payment->id,
                        'message' => 'Payment verified, ledger posted, and purchase allocation confirmed.',
                    ];
                }

                // Balance consumed or agreement invalid: Record as unapplied customer funds liability!
                // Never lose or discard provider-confirmed money.
                $payment->allocation_status = PurchaseAllocationStatus::ReconciliationRequired;
                $payment->allocated_amount_etb = '0.00';
                $payment->unapplied_amount_etb = $payment->total_amount_etb;
                $payment->reconciliation_notes = $reconciliationReason;
                $payment->save();

                // Post unapplied customer funds liability ledger entry:
                // Dr 1010 Cash & Clearing (Total Received)
                // Cr 2040 Unapplied Customer Funds & Pending Reconciliation
                $this->postingService->postUnappliedCustomerFunds($payment, $reconciliationReason);

                // DO NOT allocate to purchase agreement or update agreement snapshot

                $webhookRecord->status = 'processed';
                $webhookRecord->processed_at = now();
                $webhookRecord->save();

                return [
                    'status' => 'unapplied_reconciliation_required',
                    'event_id' => $webhookRecord->uuid,
                    'payment_id' => $payment->id,
                    'message' => 'Provider payment confirmed but unallocated due to balance limits. Recorded as unapplied customer funds liability awaiting reconciliation.',
                ];
            }

            // Handle provider failure/cancellation
            $payment->transitionTo(PaymentStatus::Failed, 'External provider reported transaction failed.');
            $payment->allocation_status = PurchaseAllocationStatus::Unallocated;
            $payment->save();
            $webhookRecord->status = 'processed';
            $webhookRecord->processed_at = now();
            $webhookRecord->save();

            return [
                'status' => 'failed_recorded',
                'event_id' => $webhookRecord->uuid,
                'payment_id' => $payment->id,
                'message' => 'Payment failure recorded.',
            ];
        });
    }

    private function postFinancialLedgerForPayment(Payment $payment): void
    {
        match ($payment->payment_type) {
            PaymentType::Booking => $this->postingService->postBookingPaymentCaptured($payment),
            PaymentType::Subscription => $this->postingService->postSubscriptionPayment($payment),
            PaymentType::PropertyPurchase, PaymentType::Reservation, PaymentType::DeveloperInstallment =>
                $this->postingService->postPropertyPurchasePayment($payment),
            default => $this->postingService->postPropertyPurchasePayment($payment),
        };
    }

    private function coordinateBusinessRecordUpdate(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof \App\Domains\Subscription\Models\UserSubscription) {
            app(\App\Domains\Subscription\Actions\ActivateSubscriptionAction::class)->execute($payable, $payment);
        } elseif ($payable instanceof Booking) {
            $isConfirmed = $payable->status instanceof \App\Domains\Booking\Enums\BookingStatus
                ? $payable->status === \App\Domains\Booking\Enums\BookingStatus::Confirmed
                : $payable->status === 'confirmed';

            if (! $isConfirmed) {
                $payable->status = \App\Domains\Booking\Enums\BookingStatus::Confirmed;
                $payable->confirmed_at = now();
                $payable->save();
            }

            // Automatically schedule host payout waterfall
            $this->schedulePayoutAction->execute($payable);
        } elseif ($payable instanceof PropertyOffer) {
            // Update purchase agreement paid amount tracking from authoritative confirmed payments
            // Note: In accordance with Proclamation No. 818/2014, does NOT transfer legal ownership or title
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
            if ($payable->outstanding_balance_etb <= 0.0) {
                $payable->status = 'paid';
                $payable->paid_at = now();
            } else {
                $payable->status = 'partially_paid';
            }
            $payable->save();
        } elseif ($payable instanceof Invoice) {
            $payable->recordPayment((float) $payment->total_amount_etb, $payment->reference_code);
        }
    }
}
