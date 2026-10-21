<?php

declare(strict_types=1);

namespace App\Domains\Listing\Models;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\Company;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Listing extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'listing_code',
        'slug',
        'property_id',
        'published_by_user_id',
        'company_id',
        'listing_type_id',
        'transaction_mode',
        'price_etb',
        'currency',
        'price_period',
        'price_per_sqm_etb',
        'is_negotiable',
        'quality_score',
        'missing_quality_fields',
        'phone_privacy_override',
        'status',
        'is_featured',
        'promotion_tier',
        'views_count',
        'favorites_count',
        'inquiries_count',
        'submitted_at',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'price_etb' => 'decimal:2',
            'price_per_sqm_etb' => 'decimal:2',
            'is_negotiable' => 'boolean',
            'is_featured' => 'boolean',
            'quality_score' => 'integer',
            'missing_quality_fields' => 'array',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function listingType(): BelongsTo
    {
        return $this->belongsTo(ListingType::class, 'listing_type_id');
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(ListingPriceHistory::class, 'listing_id')->orderByDesc('created_at');
    }
}
