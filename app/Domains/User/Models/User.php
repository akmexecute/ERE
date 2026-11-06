<?php

declare(strict_types=1);

namespace App\Domains\User\Models;

use App\Domains\Identity\Enums\PhonePrivacyMode;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Auditable, HasFactory, Notifiable, SoftDeletes;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('assigned_at');
    }

    protected $fillable = [
        'uuid',
        'name',
        'phone',
        'email',
        'password',
        'profile_photo_path',
        'preferred_locale',
        'phone_privacy',
        'verification_level',
        'status',
        'phone_verified_at',
        'email_verified_at',
        'national_id_number',
        'national_id_type',
        'id_front_document_id',
        'id_back_document_id',
        'identity_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'national_id_number',
    ];

    protected function casts(): array
    {
        return [
            'phone_privacy' => PhonePrivacyMode::class,
            'verification_level' => 'integer',
            'phone_verified_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'identity_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string $roleSlug): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->where('roles.slug', $roleSlug)
            ->exists();
    }

    /**
     * @param array<int, string> $roleSlugs
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->whereIn('roles.slug', $roleSlugs)
            ->exists();
    }

    public function assignRole(string $roleSlug): void
    {
        $role = DB::table('roles')->where('slug', $roleSlug)->first();
        if ($role) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $this->id, 'role_id' => $role->id],
                []
            );
        }
    }

    public function hasPermission(string $permissionSlug): bool
    {
        // Suspended or inactive accounts are strictly denied all privileges
        if ($this->status !== 'active') {
            return false;
        }

        // Active super_admin possesses all permissions
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $hasViaRole = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $this->id)
            ->where('permissions.slug', $permissionSlug)
            ->exists();

        if ($hasViaRole) {
            return true;
        }

        return DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $this->id)
            ->where('permissions.slug', $permissionSlug)
            ->exists();
    }

    public function assignRoleBySlug(string $roleSlug): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
        if ($roleId) {
            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $this->id,
                'role_id' => $roleId,
                'assigned_at' => now(),
            ]);
        }
    }

    public function verifications(): MorphMany
    {
        return $this->morphMany(\App\Domains\Verification\Models\Verification::class, 'verifiable');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(\App\Domains\Engagement\Models\Favorite::class, 'user_id');
    }

    public function savedCollections(): HasMany
    {
        return $this->hasMany(\App\Domains\Engagement\Models\SavedCollection::class, 'user_id');
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(\App\Domains\Engagement\Models\SavedSearch::class, 'user_id');
    }

    public function priceAlerts(): HasMany
    {
        return $this->hasMany(\App\Domains\Engagement\Models\PriceAlert::class, 'user_id');
    }

    public function buyerInquiries(): HasMany
    {
        return $this->hasMany(\App\Domains\Inquiry\Models\Inquiry::class, 'buyer_id')->orderByDesc('last_message_at');
    }

    public function sellerInquiries(): HasMany
    {
        return $this->hasMany(\App\Domains\Inquiry\Models\Inquiry::class, 'seller_id')->orderByDesc('last_message_at');
    }

    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(\App\Domains\Notification\Models\NotificationPreference::class, 'user_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(\App\Domains\Support\Models\SupportTicket::class, 'user_id')->orderByDesc('created_at');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(\App\Domains\Property\Models\Property::class, 'owner_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(\App\Domains\Listing\Models\Listing::class, 'published_by_user_id');
    }

    public function hostProfile(): HasOne
    {
        return $this->hasOne(\App\Domains\Host\Models\Host::class, 'user_id');
    }

    public function tenantProfile(): HasOne
    {
        return $this->hasOne(\App\Domains\Tenant\Models\TenantProfile::class, 'user_id');
    }

    public function agentProfile(): HasOne
    {
        return $this->hasOne(\App\Domains\Agency\Models\AgentProfile::class, 'user_id');
    }

    public function developerProfile(): HasOne
    {
        return $this->hasOne(\App\Domains\Developer\Models\DeveloperProfile::class, 'user_id');
    }

    public function guestBookings(): HasMany
    {
        return $this->hasMany(\App\Domains\Booking\Models\Booking::class, 'guest_user_id')->orderByDesc('check_in_date');
    }

    public function leasesAsTenant(): HasMany
    {
        return $this->hasMany(\App\Domains\Lease\Models\Lease::class, 'tenant_id')->orderByDesc('start_date');
    }

    public function leasesAsLandlord(): HasMany
    {
        return $this->hasMany(\App\Domains\Lease\Models\Lease::class, 'landlord_id')->orderByDesc('start_date');
    }

    public function rentalApplicationsAsTenant(): HasMany
    {
        return $this->hasMany(\App\Domains\Tenant\Models\RentalApplication::class, 'tenant_id')->orderByDesc('created_at');
    }

    public function rentalApplicationsAsLandlord(): HasMany
    {
        return $this->hasMany(\App\Domains\Tenant\Models\RentalApplication::class, 'landlord_id')->orderByDesc('created_at');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(\App\Domains\Company\Models\Company::class, 'owner_user_id');
    }

    public function unitReservations(): HasMany
    {
        return $this->hasMany(\App\Domains\Developer\Models\UnitReservation::class, 'buyer_user_id')->orderByDesc('created_at');
    }

    public function offersAsBuyer(): HasMany
    {
        return $this->hasMany(\App\Domains\Offer\Models\PropertyOffer::class, 'buyer_user_id')->orderByDesc('created_at');
    }

    public function offersAsSeller(): HasMany
    {
        return $this->hasMany(\App\Domains\Offer\Models\PropertyOffer::class, 'seller_user_id')->orderByDesc('created_at');
    }

    public function isHost(): bool
    {
        return $this->hostProfile()->exists() || $this->hasRole('host');
    }

    public function isTenant(): bool
    {
        return $this->tenantProfile()->exists() || $this->leasesAsTenant()->exists() || $this->rentalApplicationsAsTenant()->exists() || $this->hasRole('tenant');
    }

    public function isLandlord(): bool
    {
        return $this->leasesAsLandlord()->exists() || $this->properties()->exists() || $this->hasRole('property_owner');
    }

    public function isAgent(): bool
    {
        return $this->agentProfile()->exists() || $this->hasRole('agent');
    }

    public function isDeveloper(): bool
    {
        return $this->developerProfile()->exists() || $this->hasRole('developer');
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole([
            'super_admin',
            'platform_admin',
            'property_admin',
            'verification_officer',
            'moderator',
            'finance_officer',
            'customer_support',
        ]);
    }

    public function idFrontDocument(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Document\Models\Document::class, 'id_front_document_id');
    }

    public function idBackDocument(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Document\Models\Document::class, 'id_back_document_id');
    }

    public function damageClaimsAsGuest(): HasMany
    {
        return $this->hasMany(\App\Domains\Dispute\Models\DamageClaim::class, 'guest_user_id');
    }

    public function securityDepositsAsGuest(): HasMany
    {
        return $this->hasMany(\App\Domains\Dispute\Models\SecurityDeposit::class, 'guest_user_id');
    }

    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(\App\Domains\Review\Models\StayReview::class, 'reviewer_user_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(\App\Domains\Review\Models\StayReview::class, 'reviewee_user_id');
    }

    public function isIdentityVerified(): bool
    {
        return $this->verification_level >= 1
            && $this->phone_verified_at !== null
            && ! empty($this->national_id_number);
    }

    public function hasIdentitySubmitted(): bool
    {
        return ! empty($this->national_id_number)
            && $this->id_front_document_id !== null;
    }

    public function getMaskedNationalIdAttribute(): ?string
    {
        if (empty($this->national_id_number)) {
            return null;
        }

        $val = trim($this->national_id_number);
        $len = strlen($val);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        $last4 = substr($val, -4);
        return 'FAN-****-'.$last4;
    }
}
