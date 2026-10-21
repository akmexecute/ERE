<?php

declare(strict_types=1);

namespace App\Domains\Engagement\Models;

use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PriceAlert extends Model
{
    protected $fillable = [
        'user_id',
        'listing_id',
        'property_id',
        'baseline_price_etb',
        'notify_on_price_drop',
        'notify_on_status_change',
        'is_active',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'baseline_price_etb' => 'decimal:2',
            'notify_on_price_drop' => 'boolean',
            'notify_on_status_change' => 'boolean',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
