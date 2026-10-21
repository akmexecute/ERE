<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LocalGuide extends Model
{
    protected $fillable = [
        'stay_listing_profile_id',
        'category',
        'place_name',
        'distance_note',
        'host_tip_en',
        'host_tip_am',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StayListingProfile::class, 'stay_listing_profile_id');
    }
}
