<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Models;

use App\Domains\Advertising\Models\AdCampaign;
use App\Domains\Company\Models\Company;
use App\Domains\Payment\Models\Payment;
use App\Domains\Promotion\Models\ListingPromotion;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class UserSubscription extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'user_subscriptions';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'user_id',
        'company_id',
        'plan_id',
        'status',
        'price_etb',
        'currency',
        'starts_at',
        'expires_at',
        'cancelled_at',
        'auto_renew',
        'features_snapshot_json',
        'payment_id',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'price_etb' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
            'features_snapshot_json' => 'array',
            'metadata_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(ListingPromotion::class, 'subscription_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class, 'subscription_id');
    }

    /**
     * Check if this subscription is currently active and within its valid date range.
     */
    public function isActive(): bool
    {
        if ($this->status !== SubscriptionStatus::Active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get feature value from the historical snapshot recorded at purchase time.
     * This protects customer rights even if an administrator subsequently changes the plan!
     */
    public function getFeatureValue(string $key): ?string
    {
        $snapshot = $this->features_snapshot_json ?? [];
        if (array_key_exists($key, $snapshot)) {
            return (string) $snapshot[$key];
        }

        // Fallback to active plan definition if not in snapshot
        return $this->plan?->getFeatureValue($key);
    }

    /**
     * Check if a boolean feature is enabled in this subscription.
     */
    public function hasFeature(string $key): bool
    {
        $val = $this->getFeatureValue($key);
        if ($val === null) {
            return false;
        }

        return in_array(strtolower($val), ['1', 'true', 'yes', 'enabled', 'unlimited'], true);
    }

    /**
     * Check if an integer feature has unlimited quota.
     */
    public function isFeatureUnlimited(string $key): bool
    {
        $val = $this->getFeatureValue($key);
        if ($val === null) {
            return false;
        }

        return strtolower($val) === 'unlimited' || (int) $val === -1;
    }

    /**
     * Get numerical quota limit for a feature.
     */
    public function getFeatureIntegerLimit(string $key): int
    {
        if ($this->isFeatureUnlimited($key)) {
            return PHP_INT_MAX;
        }

        $val = $this->getFeatureValue($key);
        return $val !== null ? (int) $val : 0;
    }
}

