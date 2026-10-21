<?php

declare(strict_types=1);

namespace App\Domains\Calendar\Models;

use App\Domains\Stay\Models\StayListingProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SeasonalPrice extends Model
{
    protected $fillable = [
        'stay_listing_profile_id',
        'name',
        'rule_type',
        'start_date',
        'end_date',
        'nightly_rate_etb',
        'rate_multiplier',
        'min_stay_nights',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'nightly_rate_etb' => 'decimal:2',
            'rate_multiplier' => 'decimal:2',
            'min_stay_nights' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StayListingProfile::class, 'stay_listing_profile_id');
    }
}
