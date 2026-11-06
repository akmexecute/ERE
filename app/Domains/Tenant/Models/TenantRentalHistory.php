<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Models;

use App\Domains\Lease\Models\Lease;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantRentalHistory extends Model
{
    protected $fillable = [
        'tenant_profile_id',
        'property_name',
        'landlord_or_agency_name',
        'contact_phone',
        'start_date',
        'end_date',
        'monthly_rent_etb',
        'is_verified_platform_record',
        'verified_lease_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_rent_etb' => 'decimal:2',
            'is_verified_platform_record' => 'boolean',
        ];
    }

    public function tenantProfile(): BelongsTo
    {
        return $this->belongsTo(TenantProfile::class, 'tenant_profile_id');
    }

    public function verifiedLease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'verified_lease_id');
    }
}
