<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingFloor extends Model
{
    protected $table = 'building_floors';

    protected $fillable = [
        'building_id',
        'project_id',
        'floor_number',
        'floor_label',
        'units_count',
        'floor_plan_document_id',
    ];

    protected $casts = [
        'floor_number' => 'integer',
        'units_count' => 'integer',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(ProjectBuilding::class, 'building_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProjectUnit::class, 'floor_id')->orderBy('unit_number');
    }
}
