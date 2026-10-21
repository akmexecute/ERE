<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PropertyImage extends Model
{
    protected $fillable = [
        'property_id',
        'disk',
        'original_sanitized_path',
        'webp_thumb_path',
        'webp_medium_path',
        'webp_large_path',
        'avif_medium_path',
        'room_tag',
        'caption',
        'perceptual_hash',
        'exif_stripped',
        'watermarked',
        'is_cover',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'exif_stripped' => 'boolean',
            'watermarked' => 'boolean',
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
