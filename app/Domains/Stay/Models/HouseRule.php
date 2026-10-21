<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HouseRule extends Model
{
    protected $fillable = [
        'stay_listing_profile_id',
        'smoking_allowed',
        'pets_allowed',
        'parties_allowed',
        'visitors_allowed',
        'children_allowed',
        'events_allowed',
        'quiet_hours_start',
        'quiet_hours_end',
        'additional_rules_en',
        'additional_rules_am',
    ];

    protected function casts(): array
    {
        return [
            'smoking_allowed' => 'boolean',
            'pets_allowed' => 'boolean',
            'parties_allowed' => 'boolean',
            'visitors_allowed' => 'boolean',
            'children_allowed' => 'boolean',
            'events_allowed' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StayListingProfile::class, 'stay_listing_profile_id');
    }
}
