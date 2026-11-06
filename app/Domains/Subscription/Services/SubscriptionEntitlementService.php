<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Services;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\UserSubscription;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SubscriptionEntitlementService
{
    /**
     * Check if a user possesses explicit, documented platform-level testing exemption.
     * Under ERE platform policy (SRS Module 33), this exemption is strictly restricted to designated
     * super_admin accounts for publishing core seed and system demonstration listings.
     * Ordinary staff, moderators, and support accounts are strictly subject to standard plan quotas.
     */
    public function hasPlatformTestingExemption(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    /**
     * Get the currently active subscription for a user (or their primary company).
     */
    public function getActiveSubscription(User $user): ?UserSubscription
    {
        return UserSubscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->where('status', SubscriptionStatus::Active)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * Resolve the effective plan for a user (active subscription plan or Free default).
     */
    public function getEffectivePlan(User $user): ?SubscriptionPlan
    {
        $activeSub = $this->getActiveSubscription($user);
        if ($activeSub) {
            return $activeSub->plan;
        }

        // Return Free default plan if available
        return SubscriptionPlan::query()
            ->where('code', 'free')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if a feature is enabled for the user.
     */
    public function can(User $user, string $featureKey): bool
    {
        $sub = $this->getActiveSubscription($user);
        if ($sub) {
            return $sub->hasFeature($featureKey);
        }

        $freePlan = $this->getEffectivePlan($user);
        if ($freePlan) {
            $val = $freePlan->getFeatureValue($featureKey);
            return in_array(strtolower((string) $val), ['1', 'true', 'yes', 'enabled', 'unlimited'], true);
        }

        return false;
    }

    public function hasEntitlement(User $user, string $featureKey): bool
    {
        return $this->can($user, $featureKey);
    }

    public function getEntitlement(User $user, string $featureKey): mixed
    {
        $sub = $this->getActiveSubscription($user);
        if ($sub) {
            if ($sub->isFeatureUnlimited($featureKey)) {
                return PHP_INT_MAX;
            }
            $val = $sub->getFeatureValue($featureKey);
            if ($val !== null && is_numeric($val)) {
                return (int) $val;
            }
            return $val;
        }

        $freePlan = $this->getEffectivePlan($user);
        if ($freePlan) {
            $val = $freePlan->getFeatureValue($featureKey);
            if ($val !== null && strtolower((string) $val) === 'unlimited') {
                return PHP_INT_MAX;
            }
            if ($val !== null && is_numeric($val)) {
                return (int) $val;
            }
            return $val;
        }

        return match ($featureKey) {
            'property.max_active_listings' => 2,
            'property.max_images' => 8,
            'property.team_members' => 1,
            default => null,
        };
    }

    /**
     * Get numerical quota limit for a feature.
     */
    public function getLimit(User $user, string $featureKey): int
    {
        $sub = $this->getActiveSubscription($user);
        if ($sub) {
            return $sub->getFeatureIntegerLimit($featureKey);
        }

        $freePlan = $this->getEffectivePlan($user);
        if ($freePlan) {
            $val = $freePlan->getFeatureValue($featureKey);
            if ($val !== null && strtolower((string) $val) === 'unlimited') {
                return PHP_INT_MAX;
            }
            return $val !== null ? (int) $val : 0;
        }

        return match ($featureKey) {
            'property.max_active_listings' => 2,
            'property.max_images' => 8,
            'property.team_members' => 1,
            default => 0,
        };
    }

    /**
     * Check if feature limit is unlimited.
     */
    public function isUnlimited(User $user, string $featureKey): bool
    {
        $sub = $this->getActiveSubscription($user);
        if ($sub) {
            return $sub->isFeatureUnlimited($featureKey);
        }

        $freePlan = $this->getEffectivePlan($user);
        if ($freePlan) {
            return strtolower((string) $freePlan->getFeatureValue($featureKey)) === 'unlimited';
        }

        return false;
    }

    /**
     * Concurrency-safe validation for publishing a new active listing.
     * Prevents simultaneous requests from consuming more than allowed listing slots.
     */
    public function validateListingPublishing(User $user, ?Listing $currentListing = null): void
    {
        // Documented ERE platform policy (SRS Module 33): super_admin retains a narrow platform-testing
        // exemption for seeding core demonstration listings. All ordinary staff and moderators are strictly
        // subject to their account subscription plans.
        if ($this->hasPlatformTestingExemption($user)) {
            return;
        }

        DB::transaction(function () use ($user, $currentListing): void {
            // Lock user's active subscription row if present
            $sub = UserSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', SubscriptionStatus::Active)
                ->where(function ($q): void {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();

            $limit = $sub ? $sub->getFeatureIntegerLimit('property.max_active_listings')
                          : $this->getLimit($user, 'property.max_active_listings');

            if ($limit === PHP_INT_MAX) {
                return;
            }

            // Count currently published active listings owned by this user
            $activeListingsQuery = Listing::query()
                ->where('published_by_user_id', $user->id)
                ->where('status', ListingStatus::Published->value);

            if ($currentListing) {
                $activeListingsQuery->whereKeyNot($currentListing->id);
            }

            $currentActiveCount = $activeListingsQuery->count();

            if ($currentActiveCount >= $limit) {
                throw new DomainException(
                    "Active listing quota reached ({$currentActiveCount} / {$limit} listings). " .
                    "Please upgrade your subscription plan to publish additional properties."
                );
            }
        });
    }

    /**
     * Validate photo upload count against plan's max_images entitlement.
     */
    public function validatePropertyImages(User $user, Property $property, int $newImagesCount = 1): void
    {
        $limit = $this->getLimit($user, 'property.max_images');
        if ($limit === PHP_INT_MAX) {
            return;
        }

        $currentCount = $property->images()->count();
        if (($currentCount + $newImagesCount) > $limit) {
            throw new DomainException(
                "Property photo limit reached ({$currentCount} photos, limit is {$limit}). " .
                "Upgrade your subscription plan to upload more photos."
            );
        }
    }

    /**
     * Validate that the user is entitled to create or manage 360° virtual tours.
     */
    public function validateVirtualTourCreation(User $user): void
    {
        if (! $this->can($user, 'property.virtual_tours')) {
            throw new DomainException(
                "360° Virtual Tour creation requires an eligible active subscription (Agent Pro, Agency, or Enterprise). " .
                "Please upgrade your subscription to create virtual tours."
            );
        }
    }

    /**
     * Validate that the user is entitled to access Advanced PostGIS GIS heatmaps.
     */
    public function validateAdvancedGisAccess(User $user): void
    {
        if (! $this->can($user, 'property.advanced_gis')) {
            throw new DomainException(
                "Neighborhood ETB/m² GeoJSON Heatmaps require an active Agency or Enterprise subscription. " .
                "Please upgrade your plan to access Advanced PostGIS Heatmaps."
            );
        }
    }
}
