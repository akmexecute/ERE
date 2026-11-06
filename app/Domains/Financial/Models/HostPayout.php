<?php

declare(strict_types=1);

namespace App\Domains\Financial\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Financial\Enums\PayoutSchedule;
use App\Domains\Financial\Enums\PayoutStatus;
use App\Domains\Host\Models\Host;
use App\Domains\Lease\Models\Lease;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

final class HostPayout extends Model
{
    use Auditable, HasUuids;

    protected $table = 'host_payouts';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'payout_reference',
        'host_id',
        'booking_id',
        'lease_id',
        'payout_destination_id',
        'schedule_type',
        'scheduled_for',
        'gross_revenue_etb',
        'platform_deductions_etb',
        'adjustments_amount_etb',
        'net_payable_etb',
        'currency',
        'status',
        'hold_reason',
        'held_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'paid_at',
        'provider_channel',
        'provider_reference',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'schedule_type' => PayoutSchedule::class,
            'status' => PayoutStatus::class,
            'gross_revenue_etb' => 'decimal:2',
            'platform_deductions_etb' => 'decimal:2',
            'adjustments_amount_etb' => 'decimal:2',
            'net_payable_etb' => 'decimal:2',
            'scheduled_for' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->payout_reference)) {
                $model->payout_reference = 'PO-' . date('Y') . '-' . str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT);
            }
            if ((float) $model->net_payable_etb < 0.0) {
                throw new DomainException('Host payable amount cannot be negative.');
            }
        });
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class, 'host_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(HostPayoutDestination::class, 'payout_destination_id');
    }

    public function heldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayoutAdjustment::class, 'host_payout_id');
    }

    public function journalPostingGroups(): MorphMany
    {
        return $this->morphMany(JournalPostingGroup::class, 'source');
    }
}

