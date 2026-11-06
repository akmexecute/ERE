<?php

declare(strict_types=1);

namespace App\Domains\Offer\Models;

use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class PropertyOffer extends Model
{
    public bool $isAuditedAmendment = false;

    protected $table = 'property_offers';

    protected $fillable = [
        'uuid',
        'offer_code',
        'listing_id',
        'property_id',
        'buyer_user_id',
        'seller_user_id',
        'agent_user_id',
        'crm_lead_id',
        'listed_asking_price_etb',
        'initial_offer_amount_etb',
        'latest_amount_etb',
        'awaiting_response_from',
        'status',
        'expires_at',
        'accepted_at',
        'rejected_at',
        'seller_identity_snapshot_json',
        'metadata_json',
    ];

    protected $casts = [
        'listed_asking_price_etb' => 'decimal:2',
        'initial_offer_amount_etb' => 'decimal:2',
        'latest_amount_etb' => 'decimal:2',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'seller_identity_snapshot_json' => 'array',
        'metadata_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->offer_code)) {
                $model->offer_code = 'OFR-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
        });

        static::deleting(function (self $model): void {
            if ($model->status === 'accepted') {
                throw new DomainException('Immutable property offer: Accepted deals and offers cannot be deleted.');
            }
        });

        static::updating(function (self $model): void {
            if ($model->getOriginal('status') === 'accepted' && $model->isDirty('latest_amount_etb') && ! $model->isAuditedAmendment) {
                throw new DomainException('Immutable purchase terms: Accepted agreement price cannot be silently modified without a formal versioned amendment.');
            }

            $originalSnapshot = $model->getOriginal('seller_identity_snapshot_json');
            if ($originalSnapshot !== null && $model->isDirty('seller_identity_snapshot_json')) {
                $newSnapshot = $model->seller_identity_snapshot_json;
                $isAuditedCorrection = is_array($newSnapshot)
                    && ! empty($newSnapshot['snapshot_correction'])
                    && ! empty($newSnapshot['snapshot_correction']['reason'])
                    && ! empty($newSnapshot['snapshot_correction']['corrected_by_user_id']);

                if (! $isAuditedCorrection) {
                    throw new DomainException('Immutable transaction evidence: historical seller identity snapshot cannot be silently modified or overwritten.');
                }
            }
        });
    }

    /**
     * Apply an auditable administrative correction to the seller identity snapshot.
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

    public function events(): HasMany
    {
        return $this->hasMany(OfferNegotiationEvent::class, 'property_offer_id')->orderBy('round_number');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Property\Models\Property::class, 'property_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Listing\Models\Listing::class, 'listing_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Formalize an accepted property purchase agreement terms snapshot.
     * Captures immutable buyer/seller identities, agreed price, statutory disclosures,
     * and legal conveyance disclaimers per Proclamation No. 1357/2024 & Proclamation No. 818/2014.
     *
     * @return array<string, mixed>
     */
    public function formalizePurchaseAgreement(): array
    {
        $meta = $this->metadata_json ?? [];
        $existingAgreement = $meta['formal_purchase_agreement'] ?? null;

        if ($existingAgreement) {
            return $existingAgreement;
        }

        $agreedPrice = number_format((float) $this->latest_amount_etb, 2, '.', '');
        $nowIso = ($this->accepted_at ?? now())->toIso8601String();

        $agreement = [
            'agreement_code' => $this->offer_code,
            'version' => '1.0',
            'agreed_price_etb' => $agreedPrice,
            'currency' => 'ETB',
            'buyer' => [
                'user_id' => $this->buyer_user_id,
                'name' => $this->buyer?->name,
                'phone' => $this->buyer?->phone,
            ],
            'seller' => [
                'user_id' => $this->seller_user_id,
                'name' => $this->seller?->name,
                'phone' => $this->seller?->phone,
            ],
            'property' => [
                'property_id' => $this->property_id,
                'property_code' => $this->property?->property_code,
                'category_code' => $this->property?->category?->code,
                'title' => $this->listing?->title_en ?? $this->property?->title_en ?? ('Property #' . $this->property_id),
            ],
            'statutory_disclosures' => [
                'statutory_code' => 'Proclamation No. 1357/2024 & Proclamation No. 818/2014',
                'disclosure_version' => 'v1.0-proc1357',
                'accepted' => true,
                'accepted_at' => $nowIso,
            ],
            'legal_conveyance_notice' => 'In accordance with Ethiopian Civil Code Art. 1723 & 2877 and Proclamation No. 818/2014, completion of financial purchase payments establishes a valid equitable contract and does NOT constitute automatic transfer of title deed until formal registration before the competent municipal cadastre.',
            'amendments' => [],
            'formalized_at' => $nowIso,
        ];

        $meta['formal_purchase_agreement'] = $agreement;
        $this->metadata_json = $meta;
        $this->saveQuietly();

        return $agreement;
    }

    /**
     * Apply a versioned, auditable amendment to the accepted purchase agreement.
     * Prevents silent modification of historical terms.
     */
    public function applyAuditedAmendment(string|float $revisedPriceEtb, string $reason, int $authorizedUserId): void
    {
        if ($this->status !== 'accepted') {
            throw new DomainException("Cannot amend unaccepted offer [#{$this->offer_code}]. Only accepted purchase agreements are amendable.");
        }

        $formattedRevised = number_format((float) $revisedPriceEtb, 2, '.', '');
        if (bccomp($formattedRevised, '0.00', 2) <= 0) {
            throw new DomainException('Revised agreement price must be strictly positive.');
        }

        if (trim($reason) === '') {
            throw new DomainException('A valid contractual reason must be provided for the price amendment.');
        }

        $agreement = $this->formalizePurchaseAgreement();
        $previousPrice = $agreement['agreed_price_etb'];
        $amendments = $agreement['amendments'] ?? [];

        $versionNumber = 1.0 + (count($amendments) + 1) * 0.1;
        $versionString = number_format($versionNumber, 1, '.', '');

        $amendments[] = [
            'amendment_number' => count($amendments) + 1,
            'version' => $versionString,
            'previous_price_etb' => $previousPrice,
            'revised_price_etb' => $formattedRevised,
            'reason' => $reason,
            'authorized_by_user_id' => $authorizedUserId,
            'amended_at' => now()->toIso8601String(),
        ];

        $agreement['version'] = $versionString;
        $agreement['agreed_price_etb'] = $formattedRevised;
        $agreement['amendments'] = $amendments;

        $meta = $this->metadata_json ?? [];
        $meta['formal_purchase_agreement'] = $agreement;
        $this->metadata_json = $meta;

        $this->isAuditedAmendment = true;
        $this->latest_amount_etb = $formattedRevised;
        $this->save();
        $this->isAuditedAmendment = false;

        $this->syncFinancialBalanceSnapshot();
    }

    /**
     * Authoritative sum of all confirmed payments minus refunds.
     * Computed directly from the database, never from editable JSON metadata.
     * Returns decimal string with scale 2.
     */
    public function calculateTotalPaidEtb(): string
    {
        $successfulPayments = $this->payments()
            ->whereIn('status', [PaymentStatus::Successful->value, PaymentStatus::PartiallyRefunded->value])
            ->where(function ($query): void {
                $query->whereNull('allocation_status')
                    ->orWhereNotIn('allocation_status', [
                        PurchaseAllocationStatus::ReconciliationRequired->value,
                        PurchaseAllocationStatus::Unallocated->value,
                    ]);
            })
            ->get();

        $totalPaid = '0.00';
        foreach ($successfulPayments as $payment) {
            $allocated = ($payment->allocated_amount_etb !== null && bccomp((string) $payment->allocated_amount_etb, '0.00', 2) > 0)
                ? number_format((float) $payment->allocated_amount_etb, 2, '.', '')
                : number_format((float) $payment->total_amount_etb, 2, '.', '');
            $refunded = number_format((float) ($payment->refunded_amount_etb ?? 0.0), 2, '.', '');
            $net = bcsub($allocated, $refunded, 2);
            $totalPaid = bcadd($totalPaid, $net, 2);
        }

        return $totalPaid;
    }

    /**
     * Authoritative remaining contractual purchase balance.
     * Remaining Balance = Agreed Purchase Price - Confirmed Allocated Payments (Net of refunds)
     * Returns decimal string with scale 2.
     */
    public function calculateRemainingBalanceEtb(): string
    {
        $agreedPrice = number_format((float) $this->latest_amount_etb, 2, '.', '');
        $totalPaid = $this->calculateTotalPaidEtb();
        $remaining = bcsub($agreedPrice, $totalPaid, 2);

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
    }

    /**
     * Authoritative sum of active unexpired pending payments currently holding agreement balance.
     * Returns decimal string with scale 2.
     */
    public function calculatePendingAllocatedEtb(int $timeoutMinutes = 30): string
    {
        $cutoff = now()->subMinutes($timeoutMinutes);
        $pendingPayments = $this->payments()
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
     * Enforce strict pre-payment eligibility guards.
     *
     * @throws DomainException
     */
    public function validateEligibleForPayment(float|string|null $requestedAmount = null, bool $checkPendingHold = true): void
    {
        if ($this->status !== 'accepted') {
            throw new DomainException("Cannot initiate payment: Property offer [#{$this->offer_code}] is in '{$this->status}' state and is not an accepted purchase agreement.");
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            throw new DomainException("Cannot initiate payment: Accepted purchase offer [#{$this->offer_code}] has expired.");
        }

        if ($this->property && in_array($this->property->status, ['sold', 'withdrawn', 'archived'], true)) {
            throw new DomainException("Cannot initiate payment: Property is no longer in an eligible sale state (status: {$this->property->status}).");
        }

        $remaining = $this->calculateRemainingBalanceEtb();
        if (bccomp($remaining, '0.00', 2) <= 0) {
            throw new DomainException("Cannot initiate payment: Purchase agreement [#{$this->offer_code}] is already fully paid and settled.");
        }

        if ($requestedAmount !== null) {
            $formattedRequested = number_format((float) $requestedAmount, 2, '.', '');

            if (bccomp($formattedRequested, '0.00', 2) <= 0) {
                throw new DomainException('Payment amount must be strictly positive.');
            }

            if (bccomp($formattedRequested, $remaining, 2) > 0) {
                throw new DomainException("Payment amount [{$formattedRequested} ETB] exceeds the remaining contractual balance [{$remaining} ETB] for agreement [#{$this->offer_code}].");
            }

            if ($checkPendingHold) {
                $allocatable = $this->calculateAllocatableRemainingBalanceEtb();
                if (bccomp($formattedRequested, $allocatable, 2) > 0) {
                    $pending = $this->calculatePendingAllocatedEtb();
                    throw new DomainException("Payment amount [{$formattedRequested} ETB] exceeds the available unreserved balance [{$allocatable} ETB] for agreement [#{$this->offer_code}] (active pending payments: {$pending} ETB).");
                }
            }
        }
    }

    /**
     * Syncs calculated financial state into metadata_json as an informational snapshot.
     */
    public function syncFinancialBalanceSnapshot(): void
    {
        $totalPaid = $this->calculateTotalPaidEtb();
        $remaining = $this->calculateRemainingBalanceEtb();

        $meta = $this->metadata_json ?? [];
        $meta['total_paid_etb'] = $totalPaid;
        $meta['remaining_balance_etb'] = $remaining;
        $meta['is_fully_settled'] = (bccomp($remaining, '0.00', 2) <= 0);
        $meta['confirmed_payment_count'] = $this->payments()->where('status', PaymentStatus::Successful->value)->count();
        $meta['last_reconciled_at'] = now()->toIso8601String();

        $this->metadata_json = $meta;
        $this->saveQuietly();
    }
}
