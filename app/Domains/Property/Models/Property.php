<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use App\Domains\Document\Models\Document;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Property extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'property_code',
        'owner_id',
        'company_id',
        'ownership_relationship',
        'category_id',
        'property_type_id',
        'region_id',
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
    ];

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
        ];
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
}
