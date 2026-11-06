<?php

declare(strict_types=1);

namespace App\Domains\Lease\Models;

use App\Domains\Lease\Enums\InspectionType;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Models\ProjectUnit;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PropertyInspection extends Model
{
    use Auditable, HasUuids;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'lease_id',
        'property_id',
        'project_unit_id',
        'inspection_type',
        'inspection_date',
        'inspector_user_id',
        'electricity_meter_reading',
        'water_meter_reading',
        'condition_checklist_json',
        'photographs_json',
        'notes',
        'tenant_acknowledged_at',
        'tenant_signature_ip',
        'landlord_acknowledged_at',
        'landlord_signature_ip',
    ];

    protected function casts(): array
    {
        return [
            'inspection_type' => InspectionType::class,
            'inspection_date' => 'date',
            'electricity_meter_reading' => 'decimal:2',
            'water_meter_reading' => 'decimal:2',
            'condition_checklist_json' => 'array',
            'photographs_json' => 'array',
            'tenant_acknowledged_at' => 'immutable_datetime',
            'landlord_acknowledged_at' => 'immutable_datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function projectUnit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    public function isFullyAcknowledged(): bool
    {
        return ! empty($this->tenant_acknowledged_at) && ! empty($this->landlord_acknowledged_at);
    }

    public function getAcknowledgedByTenantAtAttribute()
    {
        return $this->tenant_acknowledged_at;
    }

    public function getAcknowledgedByLandlordAtAttribute()
    {
        return $this->landlord_acknowledged_at;
    }
}
