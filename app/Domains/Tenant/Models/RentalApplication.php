<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Lease\Models\Lease;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\Tenant\Enums\RentalApplicationStatus;
use App\Domains\User\Models\User;
use App\Models\ProjectUnit;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class RentalApplication extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'application_code',
        'property_id',
        'listing_id',
        'project_unit_id',
        'tenant_id',
        'tenant_profile_id',
        'landlord_id',
        'company_id',
        'desired_move_in_date',
        'lease_duration_months',
        'proposed_monthly_rent_etb',
        'payment_frequency',
        'occupant_count',
        'applicant_message',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'converted_lease_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RentalApplicationStatus::class,
            'payment_frequency' => \App\Domains\Lease\Enums\PaymentFrequency::class,
            'desired_move_in_date' => 'date',
            'lease_duration_months' => 'integer',
            'occupant_count' => 'integer',
            'proposed_monthly_rent_etb' => 'decimal:2',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function projectUnit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function tenantProfile(): BelongsTo
    {
        return $this->belongsTo(TenantProfile::class, 'tenant_profile_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function convertedLease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'converted_lease_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RentalApplicationDocument::class, 'rental_application_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RentalApplicationHistory::class, 'rental_application_id')->orderBy('created_at');
    }
}
