<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Calendar\Models\AvailabilityCalendar;
use App\Domains\Calendar\Models\ExternalIcalFeed;
use App\Domains\Property\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AccommodationUnit extends Model
{
    protected $fillable = [
        'unit_type_id',
        'property_id',
        'unit_number_or_code',
        'status',
    ];

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnitType::class, 'unit_type_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(AvailabilityCalendar::class);
    }

    public function icalFeeds(): HasMany
    {
        return $this->hasMany(ExternalIcalFeed::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
