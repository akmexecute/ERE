<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContractPaymentTransaction extends Model
{
    protected $table = 'contract_payment_transactions';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'transaction_reference',
        'contract_id',
        'installment_id',
        'reservation_id',
        'payer_user_id',
        'payment_method',
        'provider_name',
        'payment_account_id',
        'amount_etb',
        'transfer_reference',
        'receipt_url',
        'receipt_document_id',
        'status',
        'verified_by_user_id',
        'verified_at',
        'verification_notes',
        'rejection_reason',
        'metadata_json',
    ];

    protected $casts = [
        'amount_etb' => 'decimal:2',
        'verified_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->transaction_reference)) {
                $model->transaction_reference = 'PAY-' . date('Ymd') . '-' . strtoupper(Str::random(8));
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(DeveloperSalesContract::class, 'contract_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(ContractInstallment::class, 'installment_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(UnitReservation::class, 'reservation_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(DeveloperPaymentAccount::class, 'payment_account_id');
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }
}

