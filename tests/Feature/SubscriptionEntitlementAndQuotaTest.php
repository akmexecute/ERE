<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\SubscriptionPlanFeatureValue;
use App\Domains\Subscription\Models\UserSubscription;
use App\Domains\Subscription\Services\SubscriptionEntitlementService;
use App\Domains\User\Models\User;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Database\Seeders\SubscriptionFeatureSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SubscriptionEntitlementAndQuotaTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionEntitlementService $service;
    private User $user;
    private PropertyCategory $category;
    private PropertyType $propType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(SubscriptionFeatureSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->service = app(SubscriptionEntitlementService::class);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Almaz',
            'phone' => '+251911999888',
            'status' => 'active',
        ]);
        $this->user->assignRole('property_owner');

        $this->category = PropertyCategory::query()->firstOrCreate(
            ['code' => 'RES'],
            ['name_en' => 'Residential', 'sort_order' => 1, 'is_active' => true]
        );
        $this->propType = PropertyType::query()->firstOrCreate(
            ['code' => 'VILLA'],
            ['category_id' => $this->category->id, 'name_en' => 'Villa', 'sort_order' => 1, 'is_active' => true]
        );
    }

    private function createPublishedListing(User $owner, int $num): Listing
    {
        $prop = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-TEST-PROP-' . $num . '-' . rand(1000, 9999),
            'title_en' => "Test Property {$num}",
            'category_id' => $this->category->id,
            'property_type_id' => $this->propType->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'status' => PropertyStatus::Available,
        ]);

        $listingTypeId = \Illuminate\Support\Facades\DB::table('listing_types')->where('code', 'FOR_SALE')->value('id') ?? 1;

        return Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => "LST-TEST-{$num}-" . rand(1000, 9999),
            'slug' => "test-listing-{$num}-" . Str::random(6),
            'property_id' => $prop->id,
            'listing_type_id' => $listingTypeId,
            'published_by_user_id' => $owner->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 5_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
        ]);
    }

    public function test_user_without_active_subscription_uses_free_tier_entitlements(): void
    {
        $maxListings = $this->service->getEntitlement($this->user, 'property.max_active_listings');
        $this->assertSame(2, $maxListings);

        $hasVirtualTours = $this->service->hasEntitlement($this->user, 'property.virtual_tours');
        $this->assertFalse($hasVirtualTours);

        $hasAdvancedGis = $this->service->hasEntitlement($this->user, 'property.advanced_gis');
        $this->assertFalse($hasAdvancedGis);
    }

    public function test_user_cannot_exceed_active_listings_limit(): void
    {
        // Free tier allows 2 listings
        $this->createPublishedListing($this->user, 1);
        $this->createPublishedListing($this->user, 2);

        // Attempting to publish a 3rd listing throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Active listing quota reached');

        $this->service->validateListingPublishing($this->user);
    }

    public function test_subscribing_to_agent_pro_expands_active_listings_and_grants_virtual_tours(): void
    {
        // First 2 listings published
        $this->createPublishedListing($this->user, 1);
        $this->createPublishedListing($this->user, 2);

        // Assign Agent Pro subscription
        $agentProPlan = SubscriptionPlan::query()->where('code', 'agent_pro')->firstOrFail();
        $snapshot = [];
        foreach ($agentProPlan->featureValues()->with('feature')->get() as $fv) {
            if ($fv->feature) {
                $snapshot[$fv->feature->key] = $fv->value;
            }
        }

        UserSubscription::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'plan_id' => $agentProPlan->id,
            'status' => SubscriptionStatus::Active,
            'price_etb' => 1200.00,
            'currency' => 'ETB',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'features_snapshot_json' => $snapshot,
        ]);

        // Entitlements now reflect Agent Pro
        $this->assertSame(25, $this->service->getEntitlement($this->user, 'property.max_active_listings'));
        $this->assertTrue($this->service->hasEntitlement($this->user, 'property.virtual_tours'));
        $this->assertTrue($this->service->hasEntitlement($this->user, 'property.advanced_gis'));

        // Can now publish 3rd listing without exception
        $this->service->validateListingPublishing($this->user);
        $this->assertTrue(true);
    }

    public function test_property_photos_quota_enforcement(): void
    {
        $prop = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-TEST-PROP-IMG-01',
            'title_en' => 'Test Property For Images',
            'category_id' => $this->category->id,
            'property_type_id' => $this->propType->id,
            'owner_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => PropertyStatus::Available,
        ]);

        // Free tier allows up to 8 photos
        $this->service->validatePropertyImages($this->user, $prop, 8);
        $this->assertTrue(true);

        // Uploading 9th photo exceeds limit
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Property photo limit reached');

        $this->service->validatePropertyImages($this->user, $prop, 9);
    }

    public function test_features_snapshot_preserves_customer_terms_if_admin_changes_plan_later(): void
    {
        $agentProPlan = SubscriptionPlan::query()->where('code', 'agent_pro')->firstOrFail();

        // Customer purchases Agent Pro with snapshot of 25 listings
        $snapshot = ['property.max_active_listings' => '25'];
        UserSubscription::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'plan_id' => $agentProPlan->id,
            'status' => SubscriptionStatus::Active,
            'price_etb' => 1200.00,
            'currency' => 'ETB',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'features_snapshot_json' => $snapshot,
        ]);

        // Admin later changes Agent Pro plan in DB down to 10 listings
        $featMaxListings = \App\Domains\Subscription\Models\SubscriptionFeature::where('key', 'property.max_active_listings')->firstOrFail();
        SubscriptionPlanFeatureValue::query()->updateOrCreate(
            ['plan_id' => $agentProPlan->id, 'feature_id' => $featMaxListings->id],
            ['value' => '10']
        );

        // Existing customer keeps their grandfathered 25 listings from their purchased snapshot!
        $customerQuota = $this->service->getEntitlement($this->user, 'property.max_active_listings');
        $this->assertSame(25, $customerQuota);
    }

    public function test_generic_staff_roles_do_not_receive_unlimited_subscription_entitlements(): void
    {
        $staffModerator = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Moderator Dawit',
            'phone' => '+251911888777',
            'status' => 'active',
        ]);
        $staffModerator->assignRole('moderator');

        // Staff does NOT receive platform testing exemption
        $this->assertFalse($this->service->hasPlatformTestingExemption($staffModerator));

        // Staff without paid plan is evaluated on Free tier (limit 2 listings)
        $this->assertSame(2, $this->service->getLimit($staffModerator, 'property.max_active_listings'));
        $this->assertFalse($this->service->can($staffModerator, 'property.virtual_tours'));
        $this->assertFalse($this->service->isUnlimited($staffModerator, 'property.max_active_listings'));

        // Staff user publishes 2 listings
        $this->createPublishedListing($staffModerator, 101);
        $this->createPublishedListing($staffModerator, 102);

        // Attempting to publish 3rd listing triggers DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Active listing quota reached');
        $this->service->validateListingPublishing($staffModerator);
    }

    public function test_super_admin_retains_narrow_platform_testing_exemption_for_system_listings(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'phone' => '+251911000001',
            'status' => 'active',
        ]);
        $admin->assignRole('super_admin');

        // Platform testing exemption exists solely for super_admin
        $this->assertTrue($this->service->hasPlatformTestingExemption($admin));

        // Publish multiple listings under admin without throwing DomainException in validateListingPublishing
        $this->createPublishedListing($admin, 103);
        $this->createPublishedListing($admin, 104);
        $this->createPublishedListing($admin, 105);

        $this->service->validateListingPublishing($admin);
        $this->assertTrue(true);
    }

    public function test_moderation_approval_gracefully_handles_quota_exceeded_and_supports_admin_override(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Moderator Admin',
            'phone' => '+251911000002',
            'status' => 'active',
        ]);
        $admin->assignRole('super_admin');

        // Create standard seller with 2 published listings (hitting free quota limit)
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Standard Seller',
            'phone' => '+251911000003',
            'status' => 'active',
        ]);
        $seller->assignRole('property_owner');
        $this->createPublishedListing($seller, 201);
        $this->createPublishedListing($seller, 202);

        // Third listing pending moderation
        $prop3 = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-TEST-PROP-203',
            'title_en' => 'Test Property 203',
            'category_id' => $this->category->id,
            'property_type_id' => $this->propType->id,
            'owner_id' => $seller->id,
            'created_by' => $seller->id,
            'status' => PropertyStatus::Available,
        ]);
        $listing3 = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-TEST-203',
            'slug' => 'test-listing-203-' . Str::random(6),
            'property_id' => $prop3->id,
            'listing_type_id' => 1,
            'published_by_user_id' => $seller->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 4_500_000,
            'currency' => 'ETB',
            'status' => ListingStatus::PendingReview->value,
        ]);

        // Attempting to approve without bypass quota should redirect back with error (not crash 500)
        $response = $this->actingAs($admin)
            ->from('/admin/moderation/' . $listing3->id)
            ->post('/admin/listings/' . $listing3->id . '/approve');

        $response->assertRedirect('/admin/moderation/' . $listing3->id);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Active listing quota reached', session('error'));
        $this->assertSame(ListingStatus::PendingReview->value, $listing3->fresh()->status->value);

        // Attempting to approve WITH bypass_quota and mandatory reason should succeed
        $overrideResponse = $this->actingAs($admin)
            ->from('/admin/moderation/' . $listing3->id)
            ->post('/admin/listings/' . $listing3->id . '/approve', [
                'bypass_quota' => '1',
                'override_reason' => 'Platform-approved demonstration property exception',
            ]);

        $overrideResponse->assertRedirect('/admin/moderation/' . $listing3->id);
        $overrideResponse->assertSessionHas('success');
        $this->assertSame(ListingStatus::Published->value, $listing3->fresh()->status->value);
    }
}
