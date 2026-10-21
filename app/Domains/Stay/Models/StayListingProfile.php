<?php

declare(strict_types=1);

namespace App\Domains\Stay\Models;

use App\Domains\Calendar\Models\SeasonalPrice;
use App\Domains\Host\Models\Host;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class StayListingProfile extends Model
{
    use Auditable;

    protected $fillable = [
        'listing_id',
        'property_id',
        'host_id',
        'accommodation_model',
        'base_nightly_rate_etb',
        'weekend_nightly_rate_etb',
        'weekly_rate_etb',
        'monthly_rate_etb',
        'weekly_discount_pct',
        'monthly_discount_pct',
        'cleaning_fee_etb',
        'security_deposit_etb',
        'guest_service_fee_pct',
        'host_commission_pct',
        'tourism_tax_pct',
        'min_stay_nights',
        'max_stay_nights',
        'check_in_after_time',
        'check_out_before_time',
        'max_guests',
        'max_adults',
        'max_children',
        'max_infants',
        'allows_pets',
        'instant_book_enabled',
        'cancellation_policy',
        'hold_duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'base_nightly_rate_etb' => 'decimal:2',
            'weekend_nightly_rate_etb' => 'decimal:2',
            'weekly_rate_etb' => 'decimal:2',
            'monthly_rate_etb' => 'decimal:2',
            'weekly_discount_pct' => 'decimal:2',
            'monthly_discount_pct' => 'decimal:2',
            'cleaning_fee_etb' => 'decimal:2',
            'security_deposit_etb' => 'decimal:2',
            'guest_service_fee_pct' => 'decimal:2',
            'host_commission_pct' => 'decimal:2',
            'tourism_tax_pct' => 'decimal:2',
            'min_stay_nights' => 'integer',
            'max_stay_nights' => 'integer',
            'max_guests' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'max_infants' => 'integer',
            'allows_pets' => 'boolean',
            'instant_book_enabled' => 'boolean',
            'hold_duration_minutes' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function unitTypes(): HasMany
    {
        return $this->hasMany(AccommodationUnitType::class);
    }

    public function bedConfigurations(): HasMany
    {
        return $this->hasMany(BedConfiguration::class)->orderBy('sort_order');
    }

    public function houseRules(): HasOne
    {
        return $this->hasOne(HouseRule::class);
    }

    public function houseManual(): HasOne
    {
        return $this->hasOne(HouseManual::class);
    }

    public function localGuides(): HasMany
    {
        return $this->hasMany(LocalGuide::class);
    }

    public function seasonalPrices(): HasMany
    {
        return $this->hasMany(SeasonalPrice::class);
    }
}
