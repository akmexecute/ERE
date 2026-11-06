<?php

declare(strict_types=1);

namespace App\Domains\Dispute\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Host\Models\Host;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class SecurityDeposit extends Model
{
    // Escrow lifecycle states (SRS Section 66)
    public const STATUS_PENDING = 'pending';
    public const STATUS_HELD = 'held';
    public const STATUS_RELEASED = 'released';
    public const STATUS_PARTIALLY_DEDUCTED = 'partially_deducted';
    public const STATUS_FULLY_DEDUCTED = 'fully_deducted';
    public const STATUS_DISPUTED = 'disputed';

    // Provider payment states
    public const PAYMENT_STATUS_PENDING = 'pending_verification';
    public const PAYMENT_STATUS_HELD = 'held';
    public const PAYMENT_STATUS_SETTLED = 'settled';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';
    public const PAYMENT_STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    protected $casts = [
        'held_amount_etb' => 'float',
        'deducted_amount_etb' => 'float',
        'released_to_guest_etb' => 'float',
        'released_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function damageClaims(): HasMany
    {
        return $this->hasMany(DamageClaim::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function remainingEligibleDepositEtb(): float
    {
        return round(max(0.0, (float) $this->held_amount_etb - (float) $this->deducted_amount_etb), 2);
    }
}
