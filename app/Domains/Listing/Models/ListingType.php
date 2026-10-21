<?php

declare(strict_types=1);

namespace App\Domains\Listing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ListingType extends Model
{
    protected $fillable = [
        'code',
        'slug',
        'transaction_mode',
        'name_en',
        'name_am',
        'name_om',
        'default_price_period',
        'is_phase1_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_phase1_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'listing_type_id');
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
