<?php

declare(strict_types=1);

namespace App\Domains\Property\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PropertyVideo extends Model
{
    protected $fillable = [
        'property_id',
        'video_type',
        'storage_disk',
        'video_path_or_url',
        'thumbnail_path',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
