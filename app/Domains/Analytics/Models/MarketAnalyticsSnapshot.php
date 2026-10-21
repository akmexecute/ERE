<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use App\Domains\Location\Models\Location;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MarketAnalyticsSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'region_id',
        'city_id',
        'subcity_id',
        'category_id',
        'property_type_id',
        'transaction_mode',
        'active_listings_count',
        'new_listings_count',
        'inquiries_count',
        'avg_asking_price_etb',
        'median_asking_price_etb',
        'avg_price_per_sqm_etb',
        'data_basis',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'active_listings_count' => 'integer',
            'new_listings_count' => 'integer',
            'inquiries_count' => 'integer',
            'avg_asking_price_etb' => 'decimal:2',
            'median_asking_price_etb' => 'decimal:2',
            'avg_price_per_sqm_etb' => 'decimal:2',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'region_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'city_id');
    }

    public function subcity(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'subcity_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }
}
