<?php

declare(strict_types=1);

namespace App\Domains\Calendar\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Stay\Models\AccommodationUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AvailabilityCalendar extends Model
{
    protected $fillable = [
        'accommodation_unit_id',
        'calendar_date',
        'status',
        'custom_nightly_rate_etb',
        'min_stay_override_nights',
        'booking_id',
        'block_reason',
    ];

    protected function casts(): array
    {
        return [
            'calendar_date' => 'date:Y-m-d',
            'custom_nightly_rate_etb' => 'decimal:2',
            'min_stay_override_nights' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class, 'accommodation_unit_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
