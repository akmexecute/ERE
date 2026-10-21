<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PropertyCategory extends Model
{
    protected $fillable = [
        'code',
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

    public function propertyTypes(): HasMany
    {
        return $this->hasMany(PropertyType::class, 'category_id')->orderBy('sort_order');
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
