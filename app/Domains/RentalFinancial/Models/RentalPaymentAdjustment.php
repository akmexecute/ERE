<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Models;

use App\Domains\Lease\Models\Lease;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RentalPaymentAdjustment extends Model
{
    use HasUuids;

    public $timestamps = false;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'rental_payment_id',
        'rent_schedule_id',
        'lease_id',
        'adjustment_amount_etb',
        'reason',
        'authorized_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_amount_etb' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(RentalPayment::class, 'rental_payment_id');
    }

    public function rentSchedule(): BelongsTo
    {
        return $this->belongsTo(RentSchedule::class, 'rent_schedule_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }
}
