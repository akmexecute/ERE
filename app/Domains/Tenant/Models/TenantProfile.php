<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Models;

use App\Domains\Document\Models\Document;
use App\Domains\Lease\Models\Lease;
use App\Domains\Tenant\Enums\TenantStatus;
use App\Domains\User\Models\User;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TenantProfile extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'tenant_code',
        'user_id',
        'occupation',
        'employer_name',
        'monthly_income_etb',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'identity_document_id',
        'income_proof_document_id',
        'is_identity_verified',
        'verified_at',
        'verified_by',
        'status',
    ];

    protected $hidden = [
        'monthly_income_etb',
        'emergency_contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'monthly_income_etb' => 'decimal:2',
            'is_identity_verified' => 'boolean',
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function identityDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'identity_document_id');
    }

    public function incomeProofDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'income_proof_document_id');
    }

    public function rentalHistories(): HasMany
    {
        return $this->hasMany(TenantRentalHistory::class, 'tenant_profile_id')->orderByDesc('start_date');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RentalApplication::class, 'tenant_profile_id')->orderByDesc('created_at');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id', 'user_id')->orderByDesc('start_date');
    }

    /**
     * Safely masks the emergency contact phone number for non-privileged displays (SRS Section 145).
     */
    public function maskedEmergencyPhone(): ?string
    {
        if (empty($this->emergency_contact_phone)) {
            return null;
        }

        $phone = (string) $this->emergency_contact_phone;
        $len = strlen($phone);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        return substr($phone, 0, 7) . '***' . substr($phone, -2);
    }

    public function getMaskedEmergencyPhoneAttribute(): ?string
    {
        return $this->maskedEmergencyPhone();
    }
}
