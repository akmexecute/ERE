<?php

declare(strict_types=1);

namespace App\Domains\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BookingPriceItem extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'booking_id',
        'item_type',
        'label_en',
        'label_am',
        'quantity',
        'unit_amount_etb',
        'total_amount_etb',
        'metadata_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_etb' => 'decimal:2',
            'total_amount_etb' => 'decimal:2',
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
