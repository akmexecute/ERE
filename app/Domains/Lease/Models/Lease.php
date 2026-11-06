<?php

declare(strict_types=1);

namespace App\Domains\Lease\Models;

use App\Domains\Company\Models\Company;
use App\Domains\Document\Models\Document;
use App\Domains\Lease\Enums\LeaseStatus;
use App\Domains\Lease\Enums\PaymentFrequency;
use App\Domains\Property\Models\Property;
use App\Domains\RentalFinancial\Models\RentalPayment;
use App\Domains\RentalFinancial\Models\RentalPaymentAdjustment;
use App\Domains\RentalFinancial\Models\RentalReceipt;
use App\Domains\RentalFinancial\Models\RentSchedule;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\User\Models\User;
use App\Models\ProjectUnit;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Lease extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'lease_code',
        'rental_application_id',
        'property_id',
        'project_unit_id',
        'tenant_id',
        'landlord_id',
        'company_id',
        'start_date',
        'end_date',
        'monthly_rent_etb',
        'security_deposit_etb',
        'payment_frequency',
        'due_day_of_month',
        'contract_terms',
        'status',
        'tenant_signed_at',
        'tenant_signature_ip',
        'landlord_signed_at',
        'landlord_signature_ip',
        'signed_contract_document_id',
        'move_in_at',
        'move_out_at',
        'termination_reason',
        'terminated_at',
        'terminated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeaseStatus::class,
            'payment_frequency' => PaymentFrequency::class,
            'contract_terms' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_rent_etb' => 'decimal:2',
            'security_deposit_etb' => 'decimal:2',
            'due_day_of_month' => 'integer',
            'tenant_signed_at' => 'immutable_datetime',
            'landlord_signed_at' => 'immutable_datetime',
            'move_in_at' => 'immutable_datetime',
            'move_out_at' => 'immutable_datetime',
            'terminated_at' => 'immutable_datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function projectUnit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function originatingApplication(): BelongsTo
    {
        return $this->belongsTo(RentalApplication::class, 'rental_application_id');
    }

    public function signedContractDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'signed_contract_document_id');
    }

    public function resourceOccupancies(): HasMany
    {
        return $this->hasMany(LeaseResourceOccupancy::class, 'lease_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(PropertyInspection::class, 'lease_id')->orderBy('inspection_date');
    }

    public function rentSchedules(): HasMany
    {
        return $this->hasMany(RentSchedule::class, 'lease_id')->orderBy('period_sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class, 'lease_id')->orderByDesc('created_at');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(RentalReceipt::class, 'lease_id')->orderByDesc('issued_at');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(RentalPaymentAdjustment::class, 'lease_id')->orderByDesc('created_at');
    }

    /**
     * Checks if both tenant and landlord have signed the lease contract.
     */
    public function isFullySigned(): bool
    {
        return ! empty($this->tenant_signed_at) && ! empty($this->landlord_signed_at);
    }
}
