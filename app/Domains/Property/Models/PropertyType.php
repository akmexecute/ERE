<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PropertyType extends Model
{
    protected $fillable = [
        'category_id',
        'code',
        'slug',
        'name_en',
        'name_am',
        'name_om',
        'area_basis',
        'requires_bedrooms',
        'requires_floors',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_bedrooms' => 'boolean',
            'requires_floors' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'property_type_id');
    }

    public function localizedName(?string $locale = null): string
    {
        $lang = $locale ?? app()->getLocale();

        return match ($lang) {
            'am' => $this->name_am ?: $this->name_en,
            'om' => $this->name_om ?: $this->name_en,
            default => $this->name_en,
        };
    }
}
