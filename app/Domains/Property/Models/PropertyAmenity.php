<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class PropertyAmenity extends Model
{
    protected $fillable = [
        'group_key',
        'slug',
        'name_en',
        'name_am',
        'name_om',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Property::class,
            table: 'property_amenity_pivot',
            foreignPivotKey: 'amenity_id',
            relatedPivotKey: 'property_id'
        );
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
