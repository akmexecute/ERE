<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Administration\Models\AuditLog;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Moderation\Actions\ModerateListingAction;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Services\SubscriptionEntitlementService;
use App\Domains\User\Models\User;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Database\Seeders\SubscriptionFeatureSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class ModerationQuotaOverrideSecurityTest extends TestCase
{
    use RefreshDatabase;

    private ModerateListingAction $action;
    private SubscriptionEntitlementService $entitlementService;
    private PropertyCategory $category;
    private PropertyType $propertyType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(SubscriptionFeatureSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->action = app(ModerateListingAction::class);
        $this->entitlementService = app(SubscriptionEntitlementService::class);

        $this->category = PropertyCategory::query()->firstOrCreate(
            ['code' => 'RES'],
            ['name_en' => 'Residential', 'sort_order' => 1, 'is_active' => true]
        );
        $this->propertyType = PropertyType::query()->firstOrCreate(
            ['code' => 'APT'],
            ['category_id' => $this->category->id, 'name_en' => 'Apartment', 'sort_order' => 1, 'is_active' => true]
        );
    }

    private function createPublishedListing(User $owner, int $num): Listing
    {
        $prop = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-SEC-' . $num . '-' . rand(1000, 9999),
            'title_en' => "Test Property {$num}",
            'category_id' => $this->category->id,
            'property_type_id' => $this->propertyType->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'status' => PropertyStatus::Available,
        ]);

        return Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => "LST-SEC-{$num}-" . rand(1000, 9999),
            'slug' => "sec-listing-{$num}-" . Str::random(6),
            'property_id' => $prop->id,
            'listing_type_id' => 1,
            'published_by_user_id' => $owner->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 5_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    private function createPendingListing(User $owner, int $num): Listing
    {
        $prop = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-PEND-' . $num . '-' . rand(1000, 9999),
            'title_en' => "Pending Property {$num}",
            'category_id' => $this->category->id,
            'property_type_id' => $this->propertyType->id,
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'status' => PropertyStatus::Available,
        ]);

        return Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => "LST-PEND-{$num}-" . rand(1000, 9999),
            'slug' => "pending-listing-{$num}-" . Str::random(6),
            'property_id' => $prop->id,
            'listing_type_id' => 1,
            'published_by_user_id' => $owner->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 6_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::PendingReview->value,
        ]);
    }

    public function test_unauthorized_user_without_moderation_authority_cannot_approve(): void
    {
        $regularUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular Buyer',
            'phone' => '+251911100001',
            'status' => 'active',
        ]);
        $regularUser->assignRole('buyer');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Almaz',
            'phone' => '+251911100002',
            'status' => 'active',
        ]);
        $seller->assignRole('property_owner');

        $listing = $this->createPendingListing($seller, 1);

        $response = $this->actingAs($regularUser)
            ->post("/admin/listings/{$listing->id}/approve");

        $response->assertStatus(403);
        $this->assertSame(ListingStatus::PendingReview->value, $listing->fresh()->status->value);
    }

    public function test_ordinary_moderator_can_approve_when_seller_is_within_quota(): void
    {
        $moderator = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Listing Moderator',
            'phone' => '+251911100003',
            'status' => 'active',
        ]);
        $moderator->assignRole('moderator');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Chala',
            'phone' => '+251911100004',
            'status' => 'active',
        ]);
        $seller->assignRole('property_owner');

        // Seller has 0 published listings (quota is 2)
        $listing = $this->createPendingListing($seller, 2);

        $response = $this->actingAs($moderator)
            ->post("/admin/listings/{$listing->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(ListingStatus::Published->value, $listing->fresh()->status->value);
    }

    public function test_ordinary_moderator_cannot_override_quota_and_attempt_is_audited(): void
    {
        $moderator = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Listing Moderator Junior',
            'phone' => '+251911100005',
            'status' => 'active',
        ]);
        $moderator->assignRole('moderator');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Helen',
            'phone' => '+251911100006',
            'status' => 'active',
        ]);
        $seller->assignRole('property_owner');

        // Seller already has 2 published listings (hitting Free tier limit)
        $this->createPublishedListing($seller, 11);
        $this->createPublishedListing($seller, 12);

        $listing = $this->createPendingListing($seller, 13);

        // Moderator attempts to pass bypass_quota=1
        $response = $this->actingAs($moderator)
            ->from("/admin/moderation/{$listing->id}")
            ->post("/admin/listings/{$listing->id}/approve", [
                'bypass_quota' => '1',
                'override_reason' => 'Unauthorized attempt by ordinary moderator',
            ]);

        // Must be rejected with 403 Forbidden
        $response->assertStatus(403);
        $this->assertSame(ListingStatus::PendingReview->value, $listing->fresh()->status->value);

        // Verify denial is audited in append-only audit_logs
        $deniedAudit = AuditLog::query()
            ->where('action', 'listing_subscription_quota_override_denied')
            ->where('auditable_id', (string) $listing->id)
            ->first();

        $this->assertNotNull($deniedAudit);
        $this->assertSame($moderator->id, $deniedAudit->user_id);
        $this->assertStringContainsString('listings.moderate.override_subscription_quota', $deniedAudit->reason);
    }

    public function test_authorized_platform_admin_requires_valid_override_reason(): void
    {
        $platformAdmin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Platform Admin Solomon',
            'phone' => '+251911100007',
            'status' => 'active',
        ]);
        $platformAdmin->assignRole('platform_admin');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Tigist',
            'phone' => '+251911100008',
            'status' => 'active',
        ]);
        $seller->assignRole('property_owner');

        $this->createPublishedListing($seller, 21);
        $this->createPublishedListing($seller, 22);

        $listing = $this->createPendingListing($seller, 23);

        // Empty reason should fail validation
        $response = $this->actingAs($platformAdmin)
            ->from("/admin/moderation/{$listing->id}")
            ->post("/admin/listings/{$listing->id}/approve", [
                'bypass_quota' => '1',
                'override_reason' => '',
            ]);

        $response->assertRedirect("/admin/moderation/{$listing->id}");
        $response->assertSessionHas('error');
        $this->assertStringContainsString('override reason', session('error'));
        $this->assertSame(ListingStatus::PendingReview->value, $listing->fresh()->status->value);

        // Audit rejection recorded
        $rejectedAudit = AuditLog::query()
            ->where('action', 'listing_subscription_quota_override_rejected')
            ->where('auditable_id', (string) $listing->id)
            ->first();

        $this->assertNotNull($rejectedAudit);
        $this->assertSame($platformAdmin->id, $rejectedAudit->user_id);
    }

    public function test_authorized_admin_can_approve_with_override_and_audit_log_is_recorded(): void
    {
        $superAdmin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'phone' => '+251911100009',
            'status' => 'active',
        ]);
        $superAdmin->assignRole('super_admin');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Berhanu',
            'phone' => '+251911100010',
            'status' => 'active',
        ]);
        $seller->assignRole('property_owner');

        $this->createPublishedListing($seller, 31);
        $this->createPublishedListing($seller, 32);

        $listing = $this->createPendingListing($seller, 33);

        $response = $this->actingAs($superAdmin)
            ->from("/admin/moderation/{$listing->id}")
            ->post("/admin/listings/{$listing->id}/approve", [
                'bypass_quota' => '1',
                'override_reason' => 'Government institutional demonstration showcase exception',
            ]);

        $response->assertRedirect("/admin/moderation/{$listing->id}");
        $response->assertSessionHas('success');
        $this->assertSame(ListingStatus::Published->value, $listing->fresh()->status->value);

        // Verify successful audit log entry
        $overrideAudit = AuditLog::query()
            ->where('action', 'listing_subscription_quota_override')
            ->where('auditable_id', (string) $listing->id)
            ->first();

        $this->assertNotNull($overrideAudit);
        $this->assertSame($superAdmin->id, $overrideAudit->user_id);
        $this->assertSame('Government institutional demonstration showcase exception', $overrideAudit->reason);
        $this->assertSame($seller->id, $overrideAudit->old_values['listing_owner_id']);
        $this->assertSame(2, $overrideAudit->old_values['owner_listing_limit']);
        $this->assertSame(2, $overrideAudit->old_values['owner_active_listings_count']);
        $this->assertTrue($overrideAudit->old_values['quota_exceeded']);
        $this->assertTrue($overrideAudit->new_values['override_applied']);
    }

    public function test_override_is_strictly_scoped_and_does_not_upgrade_seller_or_grant_future_privileges(): void
    {
        $superAdmin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Administrator Scope Test',
            'phone' => '+251911100011',
            'status' => 'active',
        ]);
        $superAdmin->assignRole('super_admin');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Mulu',
            'phone' => '+251911100012',
            'status' => 'active',
        ]);
        $seller->assignRole('property_owner');

        $this->createPublishedListing($seller, 41);
        $this->createPublishedListing($seller, 42);

        // Listing 43 approved with override
        $listing43 = $this->createPendingListing($seller, 43);
        $this->actingAs($superAdmin)->post("/admin/listings/{$listing43->id}/approve", [
            'bypass_quota' => '1',
            'override_reason' => 'One-time exception for listing 43',
        ]);
        $this->assertSame(ListingStatus::Published->value, $listing43->fresh()->status->value);

        // 1. Seller's subscription plan is NOT changed (remains on Free)
        $effectivePlan = $this->entitlementService->getEffectivePlan($seller);
        $this->assertSame('free', $effectivePlan->code);

        // 2. Seller's listing limit is still 2
        $this->assertSame(2, $this->entitlementService->getLimit($seller, 'property.max_active_listings'));

        // 3. Seller cannot publish listing 44 without another override
        $listing44 = $this->createPendingListing($seller, 44);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Active listing quota reached');
        $this->entitlementService->validateListingPublishing($seller, $listing44);
    }

    public function test_audit_logs_are_append_only_and_cannot_be_deleted(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Auditor',
            'phone' => '+251911100013',
            'status' => 'active',
        ]);
        $admin->assignRole('super_admin');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Audit Target',
            'phone' => '+251911100014',
            'status' => 'active',
        ]);
        $seller->assignRole('property_owner');

        $this->createPublishedListing($seller, 51);
        $this->createPublishedListing($seller, 52);

        $listing = $this->createPendingListing($seller, 53);

        $this->actingAs($admin)->post("/admin/listings/{$listing->id}/approve", [
            'bypass_quota' => '1',
            'override_reason' => 'Audit immutability verification exception',
        ]);

        $audit = AuditLog::query()
            ->where('action', 'listing_subscription_quota_override')
            ->where('auditable_id', (string) $listing->id)
            ->firstOrFail();

        // Attempting to delete the audit log record must throw LogicException
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit logs are append-only and cannot be deleted');
        $audit->delete();
    }
}

