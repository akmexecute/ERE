<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BedConfiguration extends Model
{
    protected $fillable = [
        'stay_listing_profile_id',
        'room_label_en',
        'room_label_am',
        'bed_type',
        'bed_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'bed_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StayListingProfile::class, 'stay_listing_profile_id');
    }
}
