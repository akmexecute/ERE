<?php

declare(strict_types=1);

namespace App\Domains\Portfolio\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Portfolio\Enums\PropertyManagementMandateStatus;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PropertyManagementMandate extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'mandate_code',
        'property_id',
        'owner_user_id',
        'company_id',
        'branch_id',
        'assigned_manager_user_id',
        'management_fee_pct',
        'start_date',
        'end_date',
        'status',
        'terms',
        'termination_reason',
        'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PropertyManagementMandateStatus::class,
            'management_fee_pct' => 'decimal:2',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'terminated_at' => 'immutable_datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }

    public function assignedManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_manager_user_id');
    }
}
