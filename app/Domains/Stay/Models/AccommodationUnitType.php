<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use App\Domains\Property\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AccommodationUnitType extends Model
{
    protected $fillable = [
        'stay_listing_profile_id',
        'property_id',
        'name_en',
        'name_am',
        'name_om',
        'max_guests',
        'base_nightly_rate_etb',
        'total_rooms_count',
    ];

    protected function casts(): array
    {
        return [
            'max_guests' => 'integer',
            'base_nightly_rate_etb' => 'decimal:2',
            'total_rooms_count' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StayListingProfile::class, 'stay_listing_profile_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(AccommodationUnit::class, 'unit_type_id');
    }
}
