<?php

declare(strict_types=1);

namespace App\Domains\Financial\Services;

use App\Domains\Financial\Enums\LedgerAccountCode;
use App\Domains\Financial\Models\JournalPostingGroup;
use App\Domains\Financial\Models\LedgerAccount;
use App\Domains\Financial\Models\LedgerEntry;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DoubleEntryPostingService
{
    /**
     * Record a balanced double-entry journal posting group with its child ledger entries.
     *
     * @param  array<int, array{
     *     account_code: string|LedgerAccountCode,
     *     entry_type: 'debit'|'credit',
     *     amount_etb: float|string,
     *     description?: string,
     *     source_reference?: string
     * }>  $entries
     */
    public function post(
        Model $source,
        string $eventType,
        string $description,
        array $entries,
        ?User $actor = null
    ): JournalPostingGroup {
        if (empty($entries)) {
            throw new DomainException('Cannot post an empty journal entry list.');
        }

        return DB::transaction(function () use ($source, $eventType, $description, $entries, $actor) {
            // Check for duplicate posting on this source and event type
            $existing = JournalPostingGroup::query()
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->where('event_type', $eventType)
                ->whereNull('reversed_posting_group_id')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new DomainException("Duplicate financial posting rejected: source [{$source->getMorphClass()} #{$source->getKey()}] already has posted event [{$eventType}].");
            }

            $totalDebits = '0.00';
            $totalCredits = '0.00';

            // Pre-validate accounts and calculate debit/credit sums using bcmath
            $preparedEntries = [];
            foreach ($entries as $raw) {
                $code = $raw['account_code'] instanceof LedgerAccountCode
                    ? $raw['account_code']->value
                    : (string) $raw['account_code'];

                $account = LedgerAccount::query()->where('code', $code)->first();
                if (! $account) {
                    throw new DomainException("Ledger account [{$code}] does not exist in chart of accounts.");
                }

                $amount = number_format((float) $raw['amount_etb'], 2, '.', '');
                if (bccomp($amount, '0.00', 2) <= 0) {
                    throw new DomainException("Entry amount for account [{$code}] must be strictly positive: [{$amount}].");
                }

                $type = strtolower((string) $raw['entry_type']);
                if (! in_array($type, ['debit', 'credit'], true)) {
                    throw new DomainException("Invalid entry type [{$type}]. Must be 'debit' or 'credit'.");
                }

                if ($type === 'debit') {
                    $totalDebits = bcadd($totalDebits, $amount, 2);
                } else {
                    $totalCredits = bcadd($totalCredits, $amount, 2);
                }

                $preparedEntries[] = [
                    'account_id' => $account->id,
                    'entry_type' => $type,
                    'amount_etb' => $amount,
                    'description' => $raw['description'] ?? null,
                    'source_reference' => $raw['source_reference'] ?? null,
                ];
            }

            // Balanced accounting assertion: Total Debits must equal Total Credits
            if (bccomp($totalDebits, $totalCredits, 2) !== 0) {
                throw new DomainException("Unbalanced journal posting rejected: Total Debits [{$totalDebits} ETB] != Total Credits [{$totalCredits} ETB].");
            }

            // Create Journal Posting Group
            $postingGroup = JournalPostingGroup::query()->create([
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'event_type' => $eventType,
                'description' => $description,
                'actor_user_id' => $actor?->id,
                'total_debit_amount_etb' => $totalDebits,
                'total_credit_amount_etb' => $totalCredits,
                'is_balanced' => true,
                'posted_at' => now(),
            ]);

            // Create individual immutable Ledger Entries
            foreach ($preparedEntries as $pe) {
                LedgerEntry::query()->create([
                    'posting_group_id' => $postingGroup->id,
                    'ledger_account_id' => $pe['account_id'],
                    'entry_type' => $pe['entry_type'],
                    'amount_etb' => $pe['amount_etb'],
                    'currency' => 'ETB',
                    'description' => $pe['description'],
                    'source_reference' => $pe['source_reference'],
                    'created_at' => now(),
                ]);
            }

            return $postingGroup;
        });
    }

    /**
     * Create an auditable reversing journal entry for an existing posting group.
     */
    public function reverse(
        JournalPostingGroup $originalGroup,
        string $reason,
        User $actor
    ): JournalPostingGroup {
        return DB::transaction(function () use ($originalGroup, $reason, $actor) {
            $originalGroup->loadMissing('entries');

            $reversalEntries = [];
            foreach ($originalGroup->entries as $entry) {
                // Invert: debit becomes credit, credit becomes debit
                $invertedType = $entry->entry_type === 'debit' ? 'credit' : 'debit';
                $reversalEntries[] = [
                    'account_code' => $entry->account->code,
                    'entry_type' => $invertedType,
                    'amount_etb' => (float) $entry->amount_etb,
                    'description' => "Reversal of #{$originalGroup->posting_reference}: {$reason}",
                    'source_reference' => $entry->source_reference,
                ];
            }

            $source = $originalGroup->source;
            if (! $source) {
                throw new DomainException('Cannot reverse journal posting without valid source model.');
            }

            // Create the reversal posting group
            $reversalGroup = $this->post(
                $source,
                'reversal',
                "Reversal of posting [{$originalGroup->posting_reference}]: {$reason}",
                $reversalEntries,
                $actor
            );

            // Link the original to the reversal
            DB::table('journal_posting_groups')
                ->where('id', $originalGroup->id)
                ->update(['reversed_posting_group_id' => $reversalGroup->id]);

            return $reversalGroup;
        });
    }

    /**
     * Standard posting for captured short-stay booking payments:
     * Dr: 1010 Cash & Clearing (Total Paid)
     * Cr: 2010 Host Payable (Accommodation Subtotal - Host Fee)
     * Cr: 4010 Platform Service Revenue (Guest Fee + Host Fee)
     * Cr: 2020 Taxes Payable (VAT / Withholding)
     */
    public function postBookingPaymentCaptured(Payment $payment): JournalPostingGroup
    {
        $booking = $payment->payable;
        if (! $booking instanceof \App\Domains\Booking\Models\Booking) {
            throw new DomainException('Payment payable must be an instance of Booking.');
        }

        $total = number_format((float) $payment->total_amount_etb, 2, '.', '');
        $tax = number_format((float) $booking->tax_amount_etb, 2, '.', '');
        $platformFee = number_format((float) $booking->platform_fee_etb + (float) $booking->cleaning_fee_etb, 2, '.', '');
        
        // Host payable = Total - Platform Fee - Tax
        $hostPayable = bcsub(bcsub($total, $platformFee, 2), $tax, 2);

        $entries = [
            [
                'account_code' => LedgerAccountCode::CashAndClearing,
                'entry_type' => 'debit',
                'amount_etb' => $total,
                'description' => "Captured booking payment for [{$booking->booking_code}]",
                'source_reference' => $payment->reference_code,
            ],
            [
                'account_code' => LedgerAccountCode::HostPayable,
                'entry_type' => 'credit',
                'amount_etb' => $hostPayable,
                'description' => "Net host payable for stay [{$booking->booking_code}]",
                'source_reference' => $booking->booking_code,
            ],
            [
                'account_code' => LedgerAccountCode::PlatformServiceRevenue,
                'entry_type' => 'credit',
                'amount_etb' => $platformFee,
                'description' => "Platform service fee revenue for stay [{$booking->booking_code}]",
                'source_reference' => $booking->booking_code,
            ],
        ];

        if (bccomp($tax, '0.00', 2) > 0) {
            $entries[] = [
                'account_code' => LedgerAccountCode::TaxesPayable,
                'entry_type' => 'credit',
                'amount_etb' => $tax,
                'description' => "Tax liability (VAT) for stay [{$booking->booking_code}]",
                'source_reference' => $booking->booking_code,
            ];
        }

        return $this->post(
            $payment,
            'payment_captured',
            "Captured customer booking funds for [{$booking->booking_code}] via {$payment->payment_method->value}",
            $entries,
            $payment->customer
        );
    }

    /**
     * Standard posting for property / land purchase deposit or installment:
     * Dr: 1010 Cash & Clearing (Total Deposited)
     * Cr: 2030 Customer Escrow & Purchase Deposits (Seller Liability / Escrow)
     */
    public function postPropertyPurchasePayment(Payment $payment): JournalPostingGroup
    {
        $amount = number_format((float) $payment->total_amount_etb, 2, '.', '');

        $entries = [
            [
                'account_code' => LedgerAccountCode::CashAndClearing,
                'entry_type' => 'debit',
                'amount_etb' => $amount,
                'description' => "Property purchase deposit/installment for payment [{$payment->reference_code}]",
                'source_reference' => $payment->reference_code,
            ],
            [
                'account_code' => LedgerAccountCode::CustomerEscrowAndDeposits,
                'entry_type' => 'credit',
                'amount_etb' => $amount,
                'description' => "Customer funds in escrow for property deal [{$payment->payable_type} #{$payment->payable_id}]",
                'source_reference' => $payment->transaction_id,
            ],
        ];

        return $this->post(
            $payment,
            'property_purchase_captured',
            "Property purchase funds received into escrow for deal [{$payment->payable_type} #{$payment->payable_id}]",
            $entries,
            $payment->customer
        );
    }

    /**
     * Standard posting for platform subscription payment:
     * Dr: 1010 Cash & Clearing (Total Collected)
     * Cr: 4020 Listing & Subscription Revenue (Platform Revenue)
     */
    public function postSubscriptionPayment(Payment $payment): JournalPostingGroup
    {
        $amount = number_format((float) $payment->total_amount_etb, 2, '.', '');

        $entries = [
            [
                'account_code' => LedgerAccountCode::CashAndClearing,
                'entry_type' => 'debit',
                'amount_etb' => $amount,
                'description' => "Captured platform subscription funds for [{$payment->reference_code}]",
                'source_reference' => $payment->reference_code,
            ],
            [
                'account_code' => LedgerAccountCode::ListingRevenue,
                'entry_type' => 'credit',
                'amount_etb' => $amount,
                'description' => "Earned subscription and promotional add-on revenue for [{$payment->reference_code}]",
                'source_reference' => $payment->reference_code,
            ],
        ];

        return $this->post(
            $payment,
            'subscription_payment_captured',
            "Captured subscription and marketing add-on revenue for [{$payment->reference_code}] via {$payment->payment_method->value}",
            $entries,
            $payment->customer
        );
    }

    /**
     * Post unapplied customer funds received from provider callback that cannot be allocated to a purchase:
     * Dr: 1010 Cash & Clearing (Total money actually received from provider)
     * Cr: 2040 Unapplied Customer Funds & Reconciliation Liability
     */
    public function postUnappliedCustomerFunds(Payment $payment, string $reason): JournalPostingGroup
    {
        $amount = number_format((float) $payment->total_amount_etb, 2, '.', '');

        $entries = [
            [
                'account_code' => LedgerAccountCode::CashAndClearing,
                'entry_type' => 'debit',
                'amount_etb' => $amount,
                'description' => "Unapplied funds received for payment [{$payment->reference_code}]",
                'source_reference' => $payment->reference_code,
            ],
            [
                'account_code' => LedgerAccountCode::UnappliedCustomerFunds,
                'entry_type' => 'credit',
                'amount_etb' => $amount,
                'description' => "Unapplied customer liability for payment [{$payment->reference_code}]: {$reason}",
                'source_reference' => $payment->transaction_id,
            ],
        ];

        return $this->post(
            $payment,
            'unapplied_customer_funds_captured',
            "Unapplied customer funds received: {$reason}",
            $entries,
            $payment->customer
        );
    }

    /**
     * Post an append-only refund for a property purchase or payment:
     * If settleImmediately is true (single-stage):
     *   If allocated: Dr 2030 Customer Escrow, Cr 1010 Cash & Clearing
     *   If unapplied: Dr 2040 Unapplied Customer Funds, Cr 1010 Cash & Clearing
     * If settleImmediately is false (Stage 1 of Two-Stage Settlement):
     *   If allocated: Dr 2030 Customer Escrow, Cr 2050 Customer Refunds Payable
     *   If unapplied: Dr 2040 Unapplied Customer Funds, Cr 2050 Customer Refunds Payable
     */
    public function postPaymentRefund(
        Payment $payment,
        float $refundAmount,
        string $reason,
        ?User $actor = null,
        bool $settleImmediately = true
    ): JournalPostingGroup {
        $amount = number_format($refundAmount, 2, '.', '');
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new DomainException("Refund amount must be strictly positive: [{$amount} ETB].");
        }

        $currentRefunded = number_format((float) ($payment->refunded_amount_etb ?? 0.0), 2, '.', '');
        $captured = number_format((float) $payment->total_amount_etb, 2, '.', '');
        $newRefunded = bcadd($currentRefunded, $amount, 2);

        if (bccomp($newRefunded, $captured, 2) > 0) {
            throw new DomainException("Total refund [{$newRefunded} ETB] exceeds captured payment [{$captured} ETB].");
        }

        $isUnapplied = ($payment->allocation_status === PurchaseAllocationStatus::ReconciliationRequired);
        $debitAccount = $isUnapplied
            ? LedgerAccountCode::UnappliedCustomerFunds
            : LedgerAccountCode::CustomerEscrowAndDeposits;

        $creditAccount = $settleImmediately
            ? LedgerAccountCode::CashAndClearing
            : LedgerAccountCode::CustomerRefundsPayable;

        $entries = [
            [
                'account_code' => $debitAccount,
                'entry_type' => 'debit',
                'amount_etb' => $amount,
                'description' => $isUnapplied
                    ? "Unapplied funds liability release / refund for [{$payment->reference_code}]: {$reason}"
                    : "Escrow release / refund for payment [{$payment->reference_code}]: {$reason}",
                'source_reference' => $payment->reference_code,
            ],
            [
                'account_code' => $creditAccount,
                'entry_type' => 'credit',
                'amount_etb' => $amount,
                'description' => $settleImmediately
                    ? "Disbursement / refund of customer funds for [{$payment->reference_code}]"
                    : "Customer refund payable recognized for [{$payment->reference_code}] awaiting disbursement",
                'source_reference' => $payment->reference_code,
            ],
        ];

        return DB::transaction(function () use ($payment, $amount, $newRefunded, $captured, $reason, $entries, $actor, $isUnapplied, $settleImmediately) {
            $postingGroup = $this->post(
                $payment,
                $settleImmediately ? 'payment_refund' : 'payment_refund_authorized',
                $settleImmediately
                    ? "Refund processed for payment [{$payment->reference_code}]: {$reason}"
                    : "Refund authorized (payable recognized) for payment [{$payment->reference_code}]: {$reason}",
                $entries,
                $actor
            );

            $payment->refunded_amount_etb = (float) $newRefunded;
            if (bccomp($newRefunded, $captured, 2) === 0) {
                $payment->transitionTo(PaymentStatus::Refunded);
                if ($isUnapplied) {
                    $payment->allocation_status = PurchaseAllocationStatus::Refunded;
                    $payment->unapplied_amount_etb = '0.00';
                }
            } else {
                $payment->transitionTo(PaymentStatus::PartiallyRefunded);
                if ($isUnapplied) {
                    $payment->unapplied_amount_etb = bcsub((string) $payment->unapplied_amount_etb, $amount, 2);
                }
            }
            $payment->save();

            // Re-sync payable financial snapshot only if allocated to agreement
            if (! $isUnapplied && $payment->payable && method_exists($payment->payable, 'syncFinancialBalanceSnapshot')) {
                $payment->payable->syncFinancialBalanceSnapshot();
                if ($payment->payable instanceof \App\Domains\Developer\Models\ContractInstallment) {
                    $payment->payable->contract?->syncFinancialBalanceSnapshot();
                }
            }

            return $postingGroup;
        });
    }

    /**
     * Stage 2 of Two-Stage Refund Settlement:
     * Disburse approved refund from Customer Refunds Payable (2050) and credit Cash & Clearing (1010).
     */
    public function confirmRefundDisbursement(
        Payment $payment,
        string $externalDisbursementReference,
        ?User $actor = null
    ): JournalPostingGroup {
        return DB::transaction(function () use ($payment, $externalDisbursementReference, $actor) {
            $payment->refresh();
            $meta = $payment->metadata_json ?? [];
            if (! empty($meta['refund_settlement']['provider_settlement_confirmed'])) {
                throw new DomainException(
                    "Refund disbursement for payment [{$payment->reference_code}] is already confirmed with reference [{$meta['refund_settlement']['disbursement_reference']}]."
                );
            }

            $refundAmount = number_format((float) ($payment->refunded_amount_etb ?? 0.0), 2, '.', '');
            if (bccomp($refundAmount, '0.00', 2) <= 0) {
                throw new DomainException("Cannot disburse refund for payment [{$payment->reference_code}]: no refund amount authorized.");
            }

            $entries = [
                [
                    'account_code' => LedgerAccountCode::CustomerRefundsPayable,
                    'entry_type' => 'debit',
                    'amount_etb' => $refundAmount,
                    'description' => "Clear customer refunds payable for [{$payment->reference_code}] upon disbursement",
                    'source_reference' => $payment->reference_code,
                ],
                [
                    'account_code' => LedgerAccountCode::CashAndClearing,
                    'entry_type' => 'credit',
                    'amount_etb' => $refundAmount,
                    'description' => "Cash disbursement via provider/bank [{$externalDisbursementReference}] for [{$payment->reference_code}]",
                    'source_reference' => $externalDisbursementReference,
                ],
            ];

            $postingGroup = $this->post(
                $payment,
                'refund_disbursement_settled',
                "Refund disbursement confirmed for payment [{$payment->reference_code}] via reference [{$externalDisbursementReference}]",
                $entries,
                $actor
            );

            // Update operational settlement metadata
            $meta['refund_settlement'] = [
                'confirmed_by_user_id' => $actor?->id,
                'confirmed_by_name' => $actor?->name,
                'confirmed_at' => now()->toIso8601String(),
                'disbursement_reference' => $externalDisbursementReference,
                'provider_settlement_confirmed' => true,
                'disbursement_status' => 'settled',
                'ledger_posting_group_id' => $postingGroup->id,
            ];
            if (isset($meta['refund_authorization'])) {
                $meta['refund_authorization']['provider_settlement_confirmed'] = true;
                $meta['refund_authorization']['disbursement_status'] = 'settled';
            }
            $payment->metadata_json = $meta;
            $payment->save();

            return $postingGroup;
        });
    }

    /**
     * Post a full accounting reversal of a payment transaction:
     */
    public function postPaymentReversal(
        Payment $payment,
        string $reason,
        ?User $actor = null
    ): JournalPostingGroup {
        return DB::transaction(function () use ($payment, $reason, $actor) {
            $originalGroup = JournalPostingGroup::query()
                ->where('source_type', $payment->getMorphClass())
                ->where('source_id', $payment->getKey())
                ->whereNull('reversed_posting_group_id')
                ->latest('created_at')
                ->firstOrFail();

            $actingUser = $actor ?? $payment->customer ?? User::firstOrFail();

            $reversalGroup = $this->reverse(
                $originalGroup,
                $reason,
                $actingUser
            );

            $payment->refunded_amount_etb = (float) $payment->total_amount_etb;
            $payment->transitionTo(PaymentStatus::Reversed);
            $payment->save();

            // Re-sync payable financial snapshot
            if ($payment->payable && method_exists($payment->payable, 'syncFinancialBalanceSnapshot')) {
                $payment->payable->syncFinancialBalanceSnapshot();
                if ($payment->payable instanceof \App\Domains\Developer\Models\ContractInstallment) {
                    $payment->payable->contract?->syncFinancialBalanceSnapshot();
                }
            }

            return $reversalGroup;
        });
    }
}

