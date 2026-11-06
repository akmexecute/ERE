<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use App\Domains\Document\Models\Document;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Builders\PropertyBuilder;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

final class Property extends Model
{
    use Auditable, SoftDeletes;

    public bool $isSavingModel = false;

    public function newEloquentBuilder($query): PropertyBuilder
    {
        return new PropertyBuilder($query);
    }

    public function save(array $options = []): bool
    {
        $this->isSavingModel = true;

        try {
            return DB::transaction(fn () => parent::save($options));
        } finally {
            $this->isSavingModel = false;
        }
    }

    protected $fillable = [
        'uuid',
        'property_code',
        'owner_id',
        'company_id',
        'ownership_relationship',
        'category_id',
        'property_type_id',
        'region_id',
        'zone_id',
        'city_id',
        'subcity_id',
        'woreda_id',
        'kebele_id',
        'neighborhood_id',
        'address_line',
        'landmark_notes',
        'title_en',
        'title_am',
        'title_om',
        'description_en',
        'description_am',
        'description_om',
        'land_area_sqm',
        'building_area_sqm',
        'compound_size_sqm',
        'bedrooms',
        'bathrooms',
        'total_rooms',
        'floors',
        'floor_level',
        'parking_spaces',
        'balconies',
        'construction_year',
        'condition',
        'construction_status',
        'construction_material',
        'completion_percentage',
        'electricity_type',
        'water_supply',
        'sewer_type',
        'internet_type',
        'has_generator',
        'has_solar',
        'has_security',
        'latitude',
        'longitude',
        'is_exact_location_public',
        'status',
        'verification_level',
        'ownership_status',
        'ownership_evidence_type',
        'ownership_verified_at',
        'ownership_verified_by_user_id',
        'authorized_seller_user_id',
        'authority_valid_until',
        'ownership_legal_disclaimer',
    ];

    public ?string $status_change_reason = null;
    public ?int $status_changed_by_user_id = null;

    protected static function booted(): void
    {
        static::created(function (Property $property): void {
            if ($property->status) {
                PropertyStatusHistory::query()->create([
                    'property_id' => $property->id,
                    'from_status' => null,
                    'to_status' => $property->status,
                    'changed_by' => $property->status_changed_by_user_id ?? auth()->id() ?? $property->owner_id,
                    'reason' => $property->status_change_reason ?? 'Initial property creation',
                    'created_at' => now(),
                ]);
            }
        });

        static::updating(function (Property $property): void {
            if ($property->isDirty('status')) {
                $fromStatus = $property->getOriginal('status');
                $toStatus = $property->status;

                $fromValue = $fromStatus instanceof PropertyStatus ? $fromStatus : ($fromStatus ? PropertyStatus::tryFrom((string) $fromStatus) : null);
                $toValue = $toStatus instanceof PropertyStatus ? $toStatus : PropertyStatus::from((string) $toStatus);

                if ($fromValue !== $toValue) {
                    PropertyStatusHistory::query()->create([
                        'property_id' => $property->id,
                        'from_status' => $fromValue,
                        'to_status' => $toValue,
                        'changed_by' => $property->status_changed_by_user_id ?? auth()->id() ?? $property->owner_id,
                        'reason' => $property->status_change_reason ?? 'Property status transition',
                        'created_at' => now(),
                    ]);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PropertyStatus::class,
            'land_area_sqm' => 'decimal:2',
            'building_area_sqm' => 'decimal:2',
            'compound_size_sqm' => 'decimal:2',
            'has_generator' => 'boolean',
            'has_solar' => 'boolean',
            'has_security' => 'boolean',
            'is_exact_location_public' => 'boolean',
            'verification_level' => 'integer',
            'ownership_verified_at' => 'datetime',
            'authority_valid_until' => 'date',
        ];
    }

    public function ownershipVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ownership_verified_by_user_id');
    }

    public function authorizedSeller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_seller_user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'region_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'zone_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'city_id');
    }

    public function subcity(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'subcity_id');
    }

    public function woreda(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'woreda_id');
    }

    public function kebele(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'kebele_id');
    }

    public function neighborhood(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Location\Models\Location::class, 'neighborhood_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PropertyStatusHistory::class, 'property_id')->orderByDesc('id');
    }

    /**
     * SRS Section 3.1: One Property can have multiple Listings (Sale, Long-Term Rent, Short-Stay).
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'property_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class, 'property_id')->orderBy('sort_order');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(PropertyVideo::class, 'property_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(
            related: PropertyAmenity::class,
            table: 'property_amenity_pivot',
            foreignPivotKey: 'property_id',
            relatedPivotKey: 'amenity_id'
        );
    }

    public function getTitleAttribute(): string
    {
        return $this->title_en ?? $this->title_am ?? "Property #{$this->property_code}";
    }

    public function getPriceEtbAttribute(): ?float
    {
        return (float) ($this->listings()->first()?->price_etb ?? 0.0);
    }

    public function mandates(): HasMany
    {
        return $this->hasMany(\App\Domains\Portfolio\Models\PropertyManagementMandate::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(\App\Domains\Lease\Models\Lease::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(\App\Domains\Booking\Models\Booking::class);
    }

    public function getOwnerUserIdAttribute(): ?int
    {
        return $this->owner_id ?? null;
    }

    public function accommodationUnits(): HasMany
    {
        return $this->hasMany(\App\Domains\Stay\Models\AccommodationUnit::class);
    }

    public function projectUnits(): HasMany
    {
        return $this->hasMany(\App\Domains\Developer\Models\ProjectUnit::class, 'project_id');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(\App\Domains\Maintenance\Models\MaintenanceRequest::class);
    }

    public function verifications(): MorphMany
    {
        return $this->morphMany(\App\Domains\Verification\Models\Verification::class, 'verifiable');
    }

    public function fraudSignals(): HasMany
    {
        return $this->hasMany(\App\Domains\Moderation\Models\FraudSignal::class, 'property_id');
    }

    public function propertyReports(): HasMany
    {
        return $this->hasMany(\App\Domains\Moderation\Models\PropertyReport::class, 'property_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(\App\Domains\Engagement\Models\Favorite::class, 'property_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(\App\Domains\Inquiry\Models\Inquiry::class, 'property_id')->orderByDesc('last_message_at');
    }

    public function priceAlerts(): HasMany
    {
        return $this->hasMany(\App\Domains\Engagement\Models\PriceAlert::class, 'property_id');
    }

    public function stayProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domains\Stay\Models\StayListingProfile::class, 'property_id');
    }
}
