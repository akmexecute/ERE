<?php

declare(strict_types=1);

namespace App\Domains\VirtualTour\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PropertyTourScene extends Model
{
    use HasFactory;

    protected $table = 'property_tour_scenes';

    protected $fillable = [
        'tour_id',
        'room_name',
        'panorama_image_path',
        'sort_order',
        'initial_pitch',
        'initial_yaw',
        'initial_zoom',
        'is_default_entry',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'initial_pitch' => 'float',
            'initial_yaw' => 'float',
            'initial_zoom' => 'float',
            'is_default_entry' => 'boolean',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(PropertyVirtualTour::class, 'tour_id');
    }

    public function hotspots(): HasMany
    {
        return $this->hasMany(PropertyTourHotspot::class, 'scene_id');
    }
}

