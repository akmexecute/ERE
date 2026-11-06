<?php

declare(strict_types=1);

namespace App\Domains\Dispute\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Dispute extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'refund_to_guest_etb' => 'float',
        'payout_to_host_etb' => 'float',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->dispute_code)) {
                $model->dispute_code = 'DSP-'.date('Y').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function damageClaim(): BelongsTo
    {
        return $this->belongsTo(DamageClaim::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function againstUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'against_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_staff_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class);
    }
}
