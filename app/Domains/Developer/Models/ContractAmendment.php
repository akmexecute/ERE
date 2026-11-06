<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContractAmendment extends Model
{
    protected $table = 'contract_amendments';

    protected $fillable = [
        'uuid',
        'amendment_code',
        'status',
        'contract_id',
        'previous_price_etb',
        'revised_price_etb',
        'previous_schedule_snapshot_json',
        'revised_schedule_snapshot_json',
        'reason',
        'rejection_reason',
        'authorized_by_user_id',
        'decided_by_user_id',
        'rejected_by_user_id',
        'accepted_by_buyer',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'status' => 'string',
        'previous_price_etb' => 'decimal:2',
        'revised_price_etb' => 'decimal:2',
        'previous_schedule_snapshot_json' => 'array',
        'revised_schedule_snapshot_json' => 'array',
        'accepted_by_buyer' => 'boolean',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->amendment_code)) {
                $model->amendment_code = 'AMD-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
            if (empty($model->status)) {
                $model->status = 'pending';
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(DeveloperSalesContract::class, 'contract_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}

