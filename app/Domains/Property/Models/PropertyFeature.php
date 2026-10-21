<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use Illuminate\Database\Eloquent\Model;

final class PropertyFeature extends Model
{
    protected $fillable = [
        'slug',
        'name_en',
        'name_am',
        'name_om',
        'input_type',
        'options_json',
        'unit',
        'applicable_category_codes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'applicable_category_codes' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
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
