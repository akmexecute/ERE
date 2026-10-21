<?php

declare(strict_types=1);

namespace App\Domains\Listing\Models;

use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ListingPriceHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'listing_id',
        'old_price_etb',
        'new_price_etb',
        'change_amount_etb',
        'change_percentage',
        'changed_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price_etb' => 'decimal:2',
            'new_price_etb' => 'decimal:2',
            'change_amount_etb' => 'decimal:2',
            'change_percentage' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
