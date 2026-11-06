<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\Developer\Enums\SalesContractStatus;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Models\Payment;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ContractInstallment extends Model
{
    protected $table = 'contract_installments';

    protected $fillable = [
        'contract_id',
        'sequence_number',
        'milestone_label_en',
        'milestone_label_am',
        'percentage',
        'amount_etb',
        'due_date',
        'paid_amount_etb',
        'outstanding_balance_etb',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'sequence_number' => 'integer',
        'percentage' => 'decimal:2',
        'amount_etb' => 'decimal:2',
        'paid_amount_etb' => 'decimal:2',
        'outstanding_balance_etb' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(DeveloperSalesContract::class, 'contract_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Authoritative sum of confirmed payments minus refunds for this installment.
     * Returns decimal string with scale 2.
     */
    public function calculateTotalPaidEtb(): string
    {
        $successfulPayments = $this->payments()
            ->whereIn('status', [PaymentStatus::Successful->value, PaymentStatus::PartiallyRefunded->value])
            ->get();

        $totalPaid = '0.00';
        foreach ($successfulPayments as $payment) {
            $captured = number_format((float) $payment->total_amount_etb, 2, '.', '');
            $refunded = number_format((float) ($payment->refunded_amount_etb ?? 0.0), 2, '.', '');
            $net = bcsub($captured, $refunded, 2);
            $totalPaid = bcadd($totalPaid, $net, 2);
        }

        return $totalPaid;
    }

    /**
     * Authoritative outstanding balance for this installment.
     * Returns decimal string with scale 2.
     */
    public function calculateOutstandingBalanceEtb(): string
    {
        $amount = number_format((float) $this->amount_etb, 2, '.', '');
        $paid = $this->calculateTotalPaidEtb();
        $remaining = bcsub($amount, $paid, 2);

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
    }

    /**
     * Sum of active unexpired pending payments holding this installment.
     * Returns decimal string with scale 2.
     */
    public function calculatePendingAllocatedEtb(int $timeoutMinutes = 30): string
    {
        $cutoff = now()->subMinutes($timeoutMinutes);
        $pendingPayments = $this->payments()
            ->where('status', PaymentStatus::Pending->value)
            ->where('created_at', '>=', $cutoff)
            ->get();

        $pendingTotal = '0.00';
        foreach ($pendingPayments as $p) {
            $pendingTotal = bcadd($pendingTotal, number_format((float) $p->total_amount_etb, 2, '.', ''), 2);
        }

        return $pendingTotal;
    }

    /**
     * Available remaining headroom for initiating payments against this installment.
     * Returns decimal string with scale 2.
     */
    public function calculateAllocatableRemainingBalanceEtb(int $timeoutMinutes = 30): string
    {
        $remaining = $this->calculateOutstandingBalanceEtb();
        $pending = $this->calculatePendingAllocatedEtb($timeoutMinutes);
        $allocatable = bcsub($remaining, $pending, 2);

        return bccomp($allocatable, '0.00', 2) < 0 ? '0.00' : $allocatable;
    }

    public function isFullyPaid(): bool
    {
        return bccomp($this->calculateOutstandingBalanceEtb(), '0.00', 2) <= 0;
    }

    /**
     * Enforce strict pre-payment eligibility guards for an installment.
     *
     * @throws DomainException
     */
    public function validateEligibleForPayment(float|string|null $requestedAmount = null, bool $checkPendingHold = true): void
    {
        if ($this->contract && in_array($this->contract->status, [SalesContractStatus::Cancelled, SalesContractStatus::Terminated], true)) {
            $statusVal = $this->contract->status instanceof SalesContractStatus ? $this->contract->status->value : (string) $this->contract->status;
            throw new DomainException("Cannot initiate payment: Parent sales contract [#{$this->contract->contract_code}] is in '{$statusVal}' state.");
        }

        $remaining = $this->calculateOutstandingBalanceEtb();
        if (bccomp($remaining, '0.00', 2) <= 0) {
            throw new DomainException("Cannot initiate payment: Milestone installment #{$this->sequence_number} ({$this->milestone_label_en}) is already fully paid.");
        }

        if ($requestedAmount !== null) {
            $formattedRequested = number_format((float) $requestedAmount, 2, '.', '');

            if (bccomp($formattedRequested, '0.00', 2) <= 0) {
                throw new DomainException('Payment amount must be strictly positive.');
            }

            if (bccomp($formattedRequested, $remaining, 2) > 0) {
                throw new DomainException("Payment amount [{$formattedRequested} ETB] exceeds the remaining balance [{$remaining} ETB] for milestone installment #{$this->sequence_number}.");
            }

            if ($checkPendingHold) {
                $allocatable = $this->calculateAllocatableRemainingBalanceEtb();
                if (bccomp($formattedRequested, $allocatable, 2) > 0) {
                    $pending = $this->calculatePendingAllocatedEtb();
                    throw new DomainException("Payment amount [{$formattedRequested} ETB] exceeds the available unreserved balance [{$allocatable} ETB] for milestone installment #{$this->sequence_number} (active pending payments: {$pending} ETB).");
                }
            }
        }
    }

    /**
     * Syncs calculated financial state into database columns.
     */
    public function syncFinancialBalanceSnapshot(): void
    {
        $totalPaid = $this->calculateTotalPaidEtb();
        $remaining = $this->calculateOutstandingBalanceEtb();

        $this->paid_amount_etb = $totalPaid;
        $this->outstanding_balance_etb = $remaining;

        if (bccomp($remaining, '0.00', 2) <= 0) {
            $this->status = 'paid';
            $this->paid_at = $this->paid_at ?? now();
        } elseif (bccomp($totalPaid, '0.00', 2) > 0) {
            $this->status = 'partially_paid';
            $this->paid_at = null;
        } else {
            $this->status = 'pending';
            $this->paid_at = null;
        }

        $this->saveQuietly();
    }
}
