<?php

declare(strict_types=1);

namespace App\Domains\Developer\Models;

use App\Domains\Developer\Enums\UnitInventoryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProjectUnit extends Model
{
    use SoftDeletes;

    protected $table = 'project_units';

    protected $fillable = [
        'uuid',
        'project_id',
        'building_id',
        'floor_id',
        'unit_number',
        'floor_number',
        'unit_type',
        'area_sqm',
        'bedrooms',
        'bathrooms',
        'price_etb',
        'price_per_sqm_etb',
        'orientation',
        'parking_allocation',
        'floor_plan_document_id',
        'status',
    ];

    protected $casts = [
        'status' => UnitInventoryStatus::class,
        'area_sqm' => 'decimal:2',
        'price_etb' => 'decimal:2',
        'price_per_sqm_etb' => 'decimal:2',
        'floor_number' => 'integer',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'parking_allocation' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if ($model->floor && empty($model->floor_number)) {
                $model->floor_number = $model->floor->floor_number;
            }
            if ($model->price_etb > 0 && $model->area_sqm > 0 && empty($model->price_per_sqm_etb)) {
                $model->price_per_sqm_etb = round($model->price_etb / $model->area_sqm, 2);
            }
        });

        static::saving(function (self $model): void {
            // Enforce 4-level hierarchy consistency (Developer -> Project -> Building -> Floor -> Unit)
            if ($model->building_id && $model->project_id) {
                $building = $model->building ?: ProjectBuilding::find($model->building_id);
                if ($building && (int) $building->project_id !== (int) $model->project_id) {
                    throw new \InvalidArgumentException(sprintf(
                        "Hierarchy validation failed: Building #%d belongs to project #%d, not project #%d.",
                        $model->building_id,
                        $building->project_id,
                        $model->project_id
                    ));
                }
            }

            if ($model->floor_id && $model->building_id) {
                $floor = $model->floor ?: BuildingFloor::find($model->floor_id);
                if ($floor && (int) $floor->building_id !== (int) $model->building_id) {
                    throw new \InvalidArgumentException(sprintf(
                        "Hierarchy validation failed: Floor #%d belongs to building #%d, not building #%d.",
                        $model->floor_id,
                        $floor->building_id,
                        $model->building_id
                    ));
                }
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(ProjectBuilding::class, 'building_id');
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(BuildingFloor::class, 'floor_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(UnitReservation::class, 'project_unit_id');
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(UnitReservation::class, 'project_unit_id')
            ->whereIn('status', ['requested', 'pending_payment', 'reserved']);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(DeveloperSalesContract::class, 'project_unit_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === UnitInventoryStatus::Available
            && ! $this->activeReservation()->exists();
    }
}
