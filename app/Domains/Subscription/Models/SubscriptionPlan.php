<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Models;

use App\Domains\Subscription\Enums\SubscriberType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'subscription_plans';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'code',
        'name_en',
        'name_am',
        'name_om',
        'description_en',
        'description_am',
        'description_om',
        'subscriber_type',
        'price_etb',
        'currency',
        'duration_days',
        'is_active',
        'is_public',
        'is_featured',
        'sort_order',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'subscriber_type' => SubscriberType::class,
            'price_etb' => 'decimal:2',
            'duration_days' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function localizedName(?string $locale = null): string
    {
        $loc = $locale ?? app()->getLocale();
        if ($loc === 'am' && ! empty($this->name_am)) {
            return $this->name_am;
        }
        if ($loc === 'om' && ! empty($this->name_om)) {
            return $this->name_om;
        }
        return $this->name_en;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $loc = $locale ?? app()->getLocale();
        if ($loc === 'am' && ! empty($this->description_am)) {
            return $this->description_am;
        }
        if ($loc === 'om' && ! empty($this->description_om)) {
            return $this->description_om;
        }
        return $this->description_en;
    }

    public function isFree(): bool
    {
        return bccomp((string) $this->price_etb, '0.00', 2) === 0;
    }

    public function featureValues(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeatureValue::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'plan_id');
    }

    public function userSubscriptions(): HasMany
    {
        return $this->subscriptions();
    }

    /**
     * Get configured value for a specific feature key on this plan.
     */
    public function getFeatureValue(string $key): ?string
    {
        $pv = $this->featureValues()
            ->whereHas('feature', fn ($q) => $q->where('key', $key))
            ->first();

        return $pv?->value;
    }
}

