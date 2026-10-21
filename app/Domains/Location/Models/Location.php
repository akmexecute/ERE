<?php

declare(strict_types=1);

namespace App\Domains\Location\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Location extends Model
{
    protected $fillable = [
        'parent_id',
        'level',
        'code',
        'slug',
        'ancestry_path',
        'name_en',
        'name_am',
        'name_om',
        'latitude',
        'longitude',
        'is_active',
        'sort_order',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en');
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
