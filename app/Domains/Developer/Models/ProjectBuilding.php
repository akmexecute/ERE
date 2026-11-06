<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBuilding extends Model
{
    protected $table = 'project_buildings';

    protected $fillable = [
        'project_id',
        'building_code',
        'name_en',
        'name_am',
        'floors_count',
        'basement_floors_count',
        'media_json',
    ];

    protected $casts = [
        'floors_count' => 'integer',
        'basement_floors_count' => 'integer',
        'media_json' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function floors(): HasMany
    {
        return $this->hasMany(BuildingFloor::class, 'building_id')->orderBy('floor_number');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProjectUnit::class, 'building_id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMediaByType(string $type): array
    {
        $items = $this->media_json ?? [];
        return array_values(array_filter($items, fn ($item) => ($item['type'] ?? '') === $type));
    }

    public function getArchitecturalRendersAttribute(): array
    {
        return $this->getMediaByType('architectural_render');
    }

    public function getConstructionPhotosAttribute(): array
    {
        return $this->getMediaByType('construction_photo');
    }

    public function getCompletedPhotosAttribute(): array
    {
        return $this->getMediaByType('completed_photo');
    }
}
