<?php

declare(strict_types=1);

namespace App\Domains\Financial\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

final class PayoutAdjustment extends Model
{
    use HasUuids;

    protected $table = 'payout_adjustments';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'host_payout_id',
        'booking_id',
        'amount_etb',
        'direction',
        'reason',
        'authorized_by_user_id',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_etb' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (! in_array($model->direction, ['deduction', 'addition'], true)) {
                throw new DomainException("Invalid adjustment direction [{$model->direction}]. Must be 'deduction' or 'addition'.");
            }
            if ((float) $model->amount_etb <= 0.0) {
                throw new DomainException('Adjustment amount must be positive.');
            }
        });
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(HostPayout::class, 'host_payout_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}

