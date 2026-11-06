<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\Developer\Enums\SalesContractStatus;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DeveloperSalesContract extends Model
{
    use SoftDeletes;

    protected $table = 'developer_sales_contracts';

    protected $fillable = [
        'uuid',
        'contract_code',
        'reservation_id',
        'project_unit_id',
        'project_id',
        'buyer_user_id',
        'developer_company_id',
        'agent_user_id',
        'contract_price_etb',
        'deposit_paid_etb',
        'balance_payable_etb',
        'payment_plan_snapshot_json',
        'seller_identity_snapshot_json',
        'status',
        'disclosure_version',
        'disclosure_accepted_by_buyer',
        'disclosure_accepted_at',
        'disclosure_client_ip',
        'signed_at',
    ];

    protected $casts = [
        'status' => SalesContractStatus::class,
        'contract_price_etb' => 'decimal:2',
        'deposit_paid_etb' => 'decimal:2',
        'balance_payable_etb' => 'decimal:2',
        'payment_plan_snapshot_json' => 'array',
        'seller_identity_snapshot_json' => 'array',
        'disclosure_accepted_by_buyer' => 'boolean',
        'disclosure_accepted_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->contract_code)) {
                $model->contract_code = 'CNT-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
        });

        static::updating(function (self $model): void {
            $originalSnapshot = $model->getOriginal('seller_identity_snapshot_json');
            if ($originalSnapshot !== null && $model->isDirty('seller_identity_snapshot_json')) {
                $newSnapshot = $model->seller_identity_snapshot_json;
                $isAuditedCorrection = is_array($newSnapshot)
                    && ! empty($newSnapshot['snapshot_correction'])
                    && ! empty($newSnapshot['snapshot_correction']['reason'])
                    && ! empty($newSnapshot['snapshot_correction']['corrected_by_user_id']);

                if (! $isAuditedCorrection) {
                    throw new \DomainException('Immutable transaction evidence: historical developer seller identity snapshot cannot be silently modified or overwritten.');
                }
            }
        });
    }

    /**
     * Apply an auditable administrative correction to the developer seller identity snapshot.
     * Preserves the prior snapshot in an immutable history log.
     *
     * @param  array<string, mixed>  $updates
     */
    public function applyAuditedSnapshotCorrection(array $updates, int $officerUserId, string $reason): void
    {
        $current = $this->seller_identity_snapshot_json ?? [];
        $history = $current['snapshot_correction']['history'] ?? [];

        $priorState = $current;
        unset($priorState['snapshot_correction']['history']);
        $history[] = [
            'archived_at' => now()->toIso8601String(),
            'state' => $priorState,
        ];

        $newSnapshot = array_merge($current, $updates);
        $newSnapshot['snapshot_correction'] = [
            'corrected_by_user_id' => $officerUserId,
            'reason' => $reason,
            'corrected_at' => now()->toIso8601String(),
            'history' => $history,
        ];

        $this->seller_identity_snapshot_json = $newSnapshot;
        $this->save();
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(UnitReservation::class, 'reservation_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ContractInstallment::class, 'contract_id')->orderBy('sequence_number');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ContractPaymentTransaction::class, 'contract_id')->latest('created_at');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class, 'contract_id')->latest('created_at');
    }

    public function developerCompany(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'developer_company_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(\App\Domains\Support\Models\SupportTicket::class, 'contract_id')->latest('created_at');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Authoritative sum of all confirmed payments minus refunds across:
     * 1. Direct contract payments
     * 2. Installment payments
     * 3. Initial reservation deposit payments (if linked)
     * Returns decimal string with scale 2.
     */
    public function calculateTotalPaidEtb(): string
    {
        $totalPaid = '0.00';

        $filterValidAllocations = function ($query): void {
            $query->whereIn('status', [PaymentStatus::Successful->value, PaymentStatus::PartiallyRefunded->value])
                ->where(function ($q): void {
                    $q->whereNull('allocation_status')
                        ->orWhereNotIn('allocation_status', [
                            PurchaseAllocationStatus::ReconciliationRequired->value,
                            PurchaseAllocationStatus::Unallocated->value,
                        ]);
                });
        };

        // 1. Confirmed payments directly on this contract
        $directPayments = $this->payments()
            ->where($filterValidAllocations)
            ->get();
        foreach ($directPayments as $payment) {
            $allocated = ($payment->allocated_amount_etb !== null && bccomp((string) $payment->allocated_amount_etb, '0.00', 2) > 0)
                ? number_format((float) $payment->allocated_amount_etb, 2, '.', '')
                : number_format((float) $payment->total_amount_etb, 2, '.', '');
            $refunded = number_format((float) ($payment->refunded_amount_etb ?? 0.0), 2, '.', '');
            $totalPaid = bcadd($totalPaid, bcsub($allocated, $refunded, 2), 2);
        }

        // 2. Confirmed payments on child installments
        $installmentIds = $this->installments()->pluck('id');
        if ($installmentIds->isNotEmpty()) {
            $instPayments = Payment::query()
                ->where('payable_type', ContractInstallment::class)
                ->whereIn('payable_id', $installmentIds)
                ->where($filterValidAllocations)
                ->get();
            foreach ($instPayments as $payment) {
                $allocated = ($payment->allocated_amount_etb !== null && bccomp((string) $payment->allocated_amount_etb, '0.00', 2) > 0)
                    ? number_format((float) $payment->allocated_amount_etb, 2, '.', '')
                    : number_format((float) $payment->total_amount_etb, 2, '.', '');
                $refunded = number_format((float) ($payment->refunded_amount_etb ?? 0.0), 2, '.', '');
                $totalPaid = bcadd($totalPaid, bcsub($allocated, $refunded, 2), 2);
            }
        }

        // 3. Confirmed payments on reservation (if reservation_id is set)
        if ($this->reservation_id) {
            $resPayments = Payment::query()
                ->where('payable_type', UnitReservation::class)
                ->where('payable_id', $this->reservation_id)
                ->where($filterValidAllocations)
                ->get();
            foreach ($resPayments as $payment) {
                $allocated = ($payment->allocated_amount_etb !== null && bccomp((string) $payment->allocated_amount_etb, '0.00', 2) > 0)
                    ? number_format((float) $payment->allocated_amount_etb, 2, '.', '')
                    : number_format((float) $payment->total_amount_etb, 2, '.', '');
                $refunded = number_format((float) ($payment->refunded_amount_etb ?? 0.0), 2, '.', '');
                $totalPaid = bcadd($totalPaid, bcsub($allocated, $refunded, 2), 2);
            }
        }

        return $totalPaid;
    }

    /**
     * Authoritative remaining balance on contract:
     * Contract Price - Total Confirmed Payments (Net of refunds)
     * Returns decimal string with scale 2.
     */
    public function calculateRemainingBalanceEtb(): string
    {
        $contractPrice = number_format((float) $this->contract_price_etb, 2, '.', '');
        $totalPaid = $this->calculateTotalPaidEtb();
        $remaining = bcsub($contractPrice, $totalPaid, 2);

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
    }

    /**
     * Authoritative sum of active unexpired pending payments holding contract balance.
     * Returns decimal string with scale 2.
     */
    public function calculatePendingAllocatedEtb(int $timeoutMinutes = 30): string
    {
        $cutoff = now()->subMinutes($timeoutMinutes);
        $installmentIds = $this->installments()->pluck('id');

        $pendingPayments = Payment::query()
            ->where(function ($query) use ($installmentIds) {
                $query->where(function ($q) {
                    $q->where('payable_type', DeveloperSalesContract::class)
                        ->where('payable_id', $this->id);
                });
                if ($installmentIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($installmentIds) {
                        $q->where('payable_type', ContractInstallment::class)
                            ->whereIn('payable_id', $installmentIds);
                    });
                }
            })
            ->where('status', PaymentStatus::Pending->value)
            ->where(function ($query) use ($cutoff): void {
                $query->where('created_at', '>=', $cutoff)
                    ->orWhere(function ($q): void {
                        $q->whereNotNull('reservation_expires_at')
                            ->where('reservation_expires_at', '>=', now());
                    });
            })
            ->get();

        $pendingTotal = '0.00';
        foreach ($pendingPayments as $p) {
            $pendingTotal = bcadd($pendingTotal, number_format((float) $p->total_amount_etb, 2, '.', ''), 2);
        }

        return $pendingTotal;
    }

    /**
     * Available remaining headroom for initiating new payments.
     * Allocatable = Remaining Balance - Active Pending Reservations.
     * Returns decimal string with scale 2.
     */
    public function calculateAllocatableRemainingBalanceEtb(int $timeoutMinutes = 30): string
    {
        $remaining = $this->calculateRemainingBalanceEtb();
        $pending = $this->calculatePendingAllocatedEtb($timeoutMinutes);
        $allocatable = bcsub($remaining, $pending, 2);

        return bccomp($allocatable, '0.00', 2) < 0 ? '0.00' : $allocatable;
    }

    public function isFullyPaid(): bool
    {
        return bccomp($this->calculateRemainingBalanceEtb(), '0.00', 2) <= 0;
    }

    /**
     * Get physical construction progress from associated development project.
     */
    public function getConstructionProgressPct(): float
    {
        return (float) ($this->project?->overall_progress_pct ?? 0.0);
    }

    /**
     * Get independent financial status (separate from physical/delivery status).
     */
    public function getFinancialStatus(): string
    {
        $remaining = $this->calculateRemainingBalanceEtb();
        $totalPaid = $this->calculateTotalPaidEtb();

        if (bccomp($remaining, '0.00', 2) <= 0) {
            return 'fully_paid';
        }
        if (bccomp($totalPaid, '0.00', 2) > 0) {
            return 'partially_paid';
        }

        return 'unpaid';
    }

    /**
     * Get physical delivery/handover status.
     */
    public function getHandoverStatus(): string
    {
        return $this->status === SalesContractStatus::Completed ? 'handed_over' : 'pending_handover';
    }

    /**
     * Get legal ownership status.
     * Paying in full does NOT automatically transfer legal ownership before municipal registration.
     */
    public function getLegalOwnershipStatus(): string
    {
        return 'pending_cadastre_registration';
    }

    /**
     * Formally complete unit handover following physical inspection and authorized signoff.
     */
    public function completeHandover(User $officer, string $inspectionNotes = ''): void
    {
        if (! $this->isFullyPaid()) {
            throw new DomainException("Cannot complete handover: Contract [#{$this->contract_code}] has an unsettled remaining balance.");
        }

        $this->status = SalesContractStatus::Completed;
        $planSnapshot = $this->payment_plan_snapshot_json ?? [];
        $planSnapshot['handover'] = [
            'handed_over_at' => now()->toIso8601String(),
            'officer_user_id' => $officer->id,
            'inspection_notes' => $inspectionNotes,
        ];
        $this->payment_plan_snapshot_json = $planSnapshot;
        $this->save();
    }

    /**
     * Enforce strict pre-payment eligibility guards for developer sales contracts.
     *
     * @throws DomainException
     */
    public function validateEligibleForPayment(float|string|null $requestedAmount = null, bool $checkPendingHold = true): void
    {
        if (in_array($this->status, [SalesContractStatus::Draft, SalesContractStatus::Cancelled, SalesContractStatus::Terminated], true)) {
            $statusVal = $this->status instanceof SalesContractStatus ? $this->status->value : (string) $this->status;
            throw new DomainException("Cannot initiate payment: Developer contract [#{$this->contract_code}] is in '{$statusVal}' state.");
        }

        $remaining = $this->calculateRemainingBalanceEtb();
        if (bccomp($remaining, '0.00', 2) <= 0) {
            throw new DomainException("Cannot initiate payment: Developer contract [#{$this->contract_code}] is already fully paid and settled.");
        }

        if ($requestedAmount !== null) {
            $formattedRequested = number_format((float) $requestedAmount, 2, '.', '');

            if (bccomp($formattedRequested, '0.00', 2) <= 0) {
                throw new DomainException('Payment amount must be strictly positive.');
            }

            if (bccomp($formattedRequested, $remaining, 2) > 0) {
                throw new DomainException("Payment amount [{$formattedRequested} ETB] exceeds the remaining contractual balance [{$remaining} ETB] for contract [#{$this->contract_code}].");
            }

            if ($checkPendingHold) {
                $allocatable = $this->calculateAllocatableRemainingBalanceEtb();
                if (bccomp($formattedRequested, $allocatable, 2) > 0) {
                    $pending = $this->calculatePendingAllocatedEtb();
                    throw new DomainException("Payment amount [{$formattedRequested} ETB] exceeds the available unreserved balance [{$allocatable} ETB] for contract [#{$this->contract_code}] (active pending payments: {$pending} ETB).");
                }
            }
        }
    }

    /**
     * Syncs calculated financial state into database columns and metadata.
     * CRITICAL: Financial completion does NOT transition contract status to Completed.
     * Handover and physical completion are managed independently.
     */
    public function syncFinancialBalanceSnapshot(): void
    {
        $totalPaid = $this->calculateTotalPaidEtb();
        $remaining = $this->calculateRemainingBalanceEtb();

        $this->deposit_paid_etb = $totalPaid;
        $this->balance_payable_etb = $remaining;

        $planSnapshot = $this->payment_plan_snapshot_json ?? [];
        $planSnapshot['financial_status'] = bccomp($remaining, '0.00', 2) <= 0 ? 'fully_paid' : (bccomp($totalPaid, '0.00', 2) > 0 ? 'partially_paid' : 'unpaid');
        $planSnapshot['total_paid_etb'] = $totalPaid;
        $planSnapshot['remaining_balance_etb'] = $remaining;
        $planSnapshot['last_reconciled_at'] = now()->toIso8601String();
        $this->payment_plan_snapshot_json = $planSnapshot;

        $this->saveQuietly();
    }
}
