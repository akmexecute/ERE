<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Models;

use App\Domains\Document\Models\Document;
use App\Domains\Lease\Models\Lease;
use App\Domains\RentalFinancial\Enums\RentalPaymentStatus;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class RentalPayment extends Model
{
    use Auditable, HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'payment_reference',
        'idempotency_key',
        'lease_id',
        'rent_schedule_id',
        'tenant_id',
        'amount_etb',
        'payment_method',
        'provider_reference',
        'status',
        'confirmed_at',
        'confirmed_by_user_id',
        'proof_document_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RentalPaymentStatus::class,
            'amount_etb' => 'decimal:2',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function rentSchedule(): BelongsTo
    {
        return $this->belongsTo(RentSchedule::class, 'rent_schedule_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function proofDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'proof_document_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(RentalReceipt::class, 'rental_payment_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(RentalPaymentAdjustment::class, 'rental_payment_id')->orderBy('created_at');
    }

    /**
     * Calculates the effective net monetary amount after applying all append-only adjustments (SRS Section 189).
     * Effective Amount = Original Amount + Sum(Adjustments)
     */
    public function effectiveAmountEtb(): float
    {
        $adjustmentsSum = (float) $this->adjustments()->sum('adjustment_amount_etb');

        return round(((float) $this->amount_etb) + $adjustmentsSum, 2);
    }
}
