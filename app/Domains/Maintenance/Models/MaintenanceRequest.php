<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Lease\Models\Lease;
use App\Domains\Maintenance\Enums\MaintenanceCategory;
use App\Domains\Maintenance\Enums\MaintenancePriority;
use App\Domains\Maintenance\Enums\MaintenanceStatus;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class MaintenanceRequest extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::deleting(function (MaintenanceRequest $ticket): void {
            if ((float) $ticket->actual_cost_etb > 0.00) {
                throw new \DomainException('Maintenance tickets with incurred actual costs cannot be deleted to preserve financial audit integrity.');
            }
        });
    }

    protected $fillable = [
        'uuid',
        'ticket_code',
        'property_id',
        'project_unit_id',
        'accommodation_unit_id',
        'lease_id',
        'booking_id',
        'company_id',
        'branch_id',
        'reported_by_user_id',
        'category',
        'priority',
        'title',
        'description',
        'assigned_to_user_id',
        'contractor_name',
        'contractor_phone',
        'estimated_cost_etb',
        'actual_cost_etb',
        'billable_party',
        'status',
        'resolution_notes',
        'assigned_at',
        'started_at',
        'completed_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => MaintenanceCategory::class,
            'status' => MaintenanceStatus::class,
            'priority' => MaintenancePriority::class,
            'estimated_cost_etb' => 'decimal:2',
            'actual_cost_etb' => 'decimal:2',
            'assigned_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function projectUnit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class);
    }

    public function accommodationUnit(): BelongsTo
    {
        return $this->belongsTo(AccommodationUnit::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MaintenanceRequestHistory::class)->orderByDesc('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MaintenanceAttachment::class);
    }
}
