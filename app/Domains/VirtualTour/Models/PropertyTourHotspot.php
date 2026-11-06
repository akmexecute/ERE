<?php

declare(strict_types=1);

namespace App\Domains\VirtualTour\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PropertyTourHotspot extends Model
{
    use HasFactory;

    protected $table = 'property_tour_hotspots';

    protected $fillable = [
        'scene_id',
        'target_scene_id',
        'title',
        'pitch',
        'yaw',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'pitch' => 'float',
            'yaw' => 'float',
        ];
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(PropertyTourScene::class, 'scene_id');
    }

    public function targetScene(): BelongsTo
    {
        return $this->belongsTo(PropertyTourScene::class, 'target_scene_id');
    }
}

