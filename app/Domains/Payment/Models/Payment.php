<?php

declare(strict_types=1);

namespace App\Domains\Payment\Models;

use App\Domains\Financial\Models\JournalPostingGroup;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

final class Payment extends Model
{
    use Auditable, HasUuids;

    protected $table = 'payments';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'transaction_id',
        'reference_code',
        'idempotency_key',
        'customer_user_id',
        'payable_type',
        'payable_id',
        'property_id',
        'payment_type',
        'principal_amount_etb',
        'fee_amount_etb',
        'tax_amount_etb',
        'total_amount_etb',
        'refunded_amount_etb',
        'currency',
        'payment_method',
        'provider',
        'provider_transaction_reference',
        'status',
        'allocation_status',
        'allocated_amount_etb',
        'unapplied_amount_etb',
        'reconciliation_notes',
        'provider_event_timestamp',
        'reservation_expires_at',
        'failure_reason',
        'metadata_json',
        'confirmed_at',
    ];

    /**
     * Allowed polymorphic payable models to prevent arbitrary class injection from clients.
     */
    public const ALLOWED_PAYABLE_TYPES = [
        \App\Domains\Booking\Models\Booking::class,
        \App\Domains\Developer\Models\UnitReservation::class,
        \App\Domains\Developer\Models\DeveloperSalesContract::class,
        \App\Domains\Developer\Models\ContractInstallment::class,
        \App\Domains\Offer\Models\PropertyOffer::class,
        \App\Domains\Lease\Models\Lease::class,
        \App\Domains\RentalFinancial\Models\RentSchedule::class,
        \App\Domains\Invoice\Models\Invoice::class,
        \App\Domains\Listing\Models\Listing::class,
        \App\Domains\Subscription\Models\UserSubscription::class,
    ];

    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'payment_method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'allocation_status' => PurchaseAllocationStatus::class,
            'principal_amount_etb' => 'decimal:2',
            'fee_amount_etb' => 'decimal:2',
            'tax_amount_etb' => 'decimal:2',
            'total_amount_etb' => 'decimal:2',
            'refunded_amount_etb' => 'decimal:2',
            'allocated_amount_etb' => 'decimal:2',
            'unapplied_amount_etb' => 'decimal:2',
            'confirmed_at' => 'immutable_datetime',
            'provider_event_timestamp' => 'immutable_datetime',
            'reservation_expires_at' => 'immutable_datetime',
            'metadata_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->transaction_id)) {
                $model->transaction_id = 'TXN-' . date('Y') . '-' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT);
            }
            if (empty($model->reference_code)) {
                $model->reference_code = 'PAY-' . date('Y') . '-' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT);
            }

            if (! in_array($model->payable_type, self::ALLOWED_PAYABLE_TYPES, true)) {
                throw new DomainException("Unauthorized payable type [{$model->payable_type}]. Only verified platform models may receive payments.");
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function bankTransferProof(): HasOne
    {
        return $this->hasOne(BankTransferProof::class, 'payment_id');
    }

    public function journalPostingGroups(): MorphMany
    {
        return $this->morphMany(JournalPostingGroup::class, 'source');
    }

    public function transitionTo(PaymentStatus $targetStatus, ?string $reason = null): void
    {
        if (! $this->status->canTransitionTo($targetStatus)) {
            throw new DomainException("Invalid payment transition from [{$this->status->value}] to [{$targetStatus->value}].");
        }

        $this->status = $targetStatus;
        if ($targetStatus === PaymentStatus::Successful && ! $this->confirmed_at) {
            $this->confirmed_at = now();
        }
        if ($reason) {
            $this->failure_reason = $reason;
        }
        $this->save();
    }

    public function isAllocated(): bool
    {
        return $this->allocation_status === PurchaseAllocationStatus::Allocated;
    }

    public function requiresReconciliation(): bool
    {
        return $this->allocation_status === PurchaseAllocationStatus::ReconciliationRequired;
    }

    public function hasExpiredReservation(int $timeoutMinutes = 30): bool
    {
        if ($this->reservation_expires_at) {
            return $this->reservation_expires_at->isPast();
        }

        return $this->created_at->addMinutes($timeoutMinutes)->isPast();
    }

    public function markAllocated(string $amount): void
    {
        $this->allocation_status = PurchaseAllocationStatus::Allocated;
        $this->allocated_amount_etb = $amount;
        $this->unapplied_amount_etb = '0.00';
        $this->save();
    }

    public function markReconciliationRequired(string $amount, string $reason): void
    {
        $this->allocation_status = PurchaseAllocationStatus::ReconciliationRequired;
        $this->allocated_amount_etb = '0.00';
        $this->unapplied_amount_etb = $amount;
        $this->reconciliation_notes = $reason;
        $this->save();
    }
}

