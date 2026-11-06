<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Models;

use App\Domains\Lease\Models\Lease;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RentalReceipt extends Model
{
    use Auditable, HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'receipt_number',
        'rental_payment_id',
        'lease_id',
        'rent_schedule_id',
        'payer_user_id',
        'recipient_user_id',
        'amount_etb',
        'currency',
        'payment_method',
        'issued_at',
        'receipt_data_json',
    ];

    protected function casts(): array
    {
        return [
            'amount_etb' => 'decimal:2',
            'issued_at' => 'immutable_datetime',
            'receipt_data_json' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(RentalPayment::class, 'rental_payment_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function rentSchedule(): BelongsTo
    {
        return $this->belongsTo(RentSchedule::class, 'rent_schedule_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
