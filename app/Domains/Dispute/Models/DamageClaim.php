<?php

declare(strict_types=1);

namespace App\Domains\Dispute\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Host\Models\Host;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class DamageClaim extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'claimed_amount_etb' => 'float',
        'approved_amount_etb' => 'float',
        'evidence_photos_json' => 'array',
        'responded_at' => 'datetime',
        'claim_deadline_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->claim_code)) {
                $model->claim_code = 'CLM-'.date('Y').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function securityDeposit(): BelongsTo
    {
        return $this->belongsTo(SecurityDeposit::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }
}
