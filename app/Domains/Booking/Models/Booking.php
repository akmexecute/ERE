<?php

declare(strict_types=1);

namespace App\Domains\Booking\Models;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Host\Models\Host;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Booking extends Model
{
    use Auditable;

    protected $fillable = [
        'uuid',
        'booking_code',
        'idempotency_key',
        'listing_id',
        'property_id',
        'stay_listing_profile_id',
        'accommodation_unit_id',
        'host_id',
        'guest_user_id',
        'booking_mode',
        'check_in_date',
        'check_out_date',
        'nights_count',
        'adults_count',
        'children_count',
        'infants_count',
        'pets_count',
        'accommodation_raw_subtotal_etb',
        'discount_amount_etb',
        'accommodation_net_subtotal_etb',
        'cleaning_fee_etb',
        'platform_fee_etb',
        'tax_amount_etb',
        'security_deposit_etb',
        'total_amount_etb',
        'currency',
        'cancellation_policy',
        'status',
        'hold_expires_at',
        'confirmed_at',
        'checked_in_at',
        'checked_out_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'check_in_date' => 'date:Y-m-d',
            'check_out_date' => 'date:Y-m-d',
            'nights_count' => 'integer',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'infants_count' => 'integer',
            'pets_count' => 'integer',
            'accommodation_raw_subtotal_etb' => 'decimal:2',
            'discount_amount_etb' => 'decimal:2',
            'accommodation_net_subtotal_etb' => 'decimal:2',
            'cleaning_fee_etb' => 'decimal:2',
            'platform_fee_etb' => 'decimal:2',
            'tax_amount_etb' => 'decimal:2',
            'security_deposit_etb' => 'decimal:2',
            'total_amount_etb' => 'decimal:2',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function stayProfile(): BelongsTo
    {
        return $this->belongsTo(StayListingProfile::class, 'stay_listing_profile_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class, 'accommodation_unit_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function priceItems(): HasMany
    {
        return $this->hasMany(BookingPriceItem::class);
    }
}
