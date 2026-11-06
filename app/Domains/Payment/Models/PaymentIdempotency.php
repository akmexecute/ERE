<?php

declare(strict_types=1);

namespace App\Domains\Payment\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentIdempotency extends Model
{
    protected $table = 'payment_idempotencies';

    protected $fillable = [
        'idempotency_key',
        'request_hash',
        'customer_user_id',
        'payment_id',
        'status',
        'response_payload_json',
    ];

    protected function casts(): array
    {
        return [
            'response_payload_json' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}

