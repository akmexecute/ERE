<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Host\Models\Host;
use App\Domains\Lease\Models\Lease;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\TenantProfile;
use App\Domains\User\Models\User;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UnifiedDashboardAndListingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
    }

    private function createUser(string $name, string $phone, array $roles = ['buyer']): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'phone' => $phone,
            'status' => 'active',
            'verification_level' => 1,
        ]);

        foreach ($roles as $role) {
            $user->assignRoleBySlug($role);
        }

        return $user;
    }

    public function test_authenticated_user_can_access_unified_dashboard_and_see_identity_card(): void
    {
        $user = $this->createUser('Dawit Test', '+251911000001');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertSee('Dawit Test');
        $response->assertSee('Verified Level 1');
        $response->assertSee('+251911000001');
        $response->assertSee('My Listings');
        $response->assertSee('My Stays');
        $response->assertSee('My Purchases');
        $response->assertSee('My Profile');
    }

    public function test_dashboard_tabs_switch_correctly_with_query_params(): void
    {
        $user = $this->createUser('Sara Query', '+251911000002');

        $tabs = ['listings', 'stays', 'purchases', 'profile'];

        foreach ($tabs as $tab) {
            $response = $this->actingAs($user)->get('/dashboard?tab=' . $tab);
            $response->assertOk();
            $response->assertViewHas('activeTab', $tab);
        }
    }

    public function test_dashboard_displays_users_own_listings_and_calculates_counts_correctly(): void
    {
        $owner = $this->createUser('Owner User', '+251911000003', ['property_owner']);
        $stranger = $this->createUser('Stranger User', '+251911000004');

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('category_id', $category->id)->firstOrFail();
        $listingType = ListingType::query()->where('transaction_mode', 'BUY')->firstOrFail();

        // 1. Owner's published property & listing
        $prop1 = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-000001',
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Owner Luxury Villa',
        ]);
        Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-000001',
            'slug' => 'owner-luxury-villa-1',
            'transaction_mode' => 'BUY',
            'property_id' => $prop1->id,
            'listing_type_id' => $listingType->id,
            'published_by_user_id' => $owner->id,
            'price_etb' => 15000000.00,
            'status' => ListingStatus::Published->value,
            'views_count' => 45,
            'favorites_count' => 12,
        ]);

        // 2. Owner's draft property & listing
        $prop2 = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-000002',
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Owner Draft Apartment',
        ]);
        Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-000002',
            'slug' => 'owner-draft-apt-2',
            'transaction_mode' => 'BUY',
            'property_id' => $prop2->id,
            'listing_type_id' => $listingType->id,
            'published_by_user_id' => $owner->id,
            'price_etb' => 8500000.00,
            'status' => ListingStatus::Draft->value,
        ]);

        // 3. Stranger's listing
        $propStranger = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-000003',
            'owner_id' => $stranger->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Stranger Hidden Property',
        ]);
        Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-000003',
            'slug' => 'stranger-hidden-3',
            'transaction_mode' => 'BUY',
            'property_id' => $propStranger->id,
            'listing_type_id' => $listingType->id,
            'published_by_user_id' => $stranger->id,
            'price_etb' => 5000000.00,
            'status' => ListingStatus::Published->value,
        ]);

        $response = $this->actingAs($owner)->get('/dashboard?tab=listings');

        $response->assertOk();
        $response->assertSee('Owner Luxury Villa');
        $response->assertSee('Owner Draft Apartment');
        $response->assertDontSee('Stranger Hidden Property');

        $listingsCounts = $response->viewData('listingsCounts');
        $this->assertSame(2, $listingsCounts['total']);
        $this->assertSame(1, $listingsCounts['published']);
        $this->assertSame(1, $listingsCounts['draft']);
        $this->assertSame(45, $listingsCounts['totalViews']);
        $this->assertSame(12, $listingsCounts['totalFavorites']);
    }

    public function test_non_host_user_sees_host_onboarding_empty_state_and_can_activate_hosting(): void
    {
        $user = $this->createUser('Potential Host', '+251911000005');

        // Check Stays tab when host profile does not exist
        $response = $this->actingAs($user)->get('/dashboard?tab=stays');
        $response->assertOk();
        $response->assertSee('Start Hosting with ERE Short Stays');
        $response->assertSee('Activate hosting');

        // Submit host activation form
        $postResponse = $this->actingAs($user)->post('/host/profile', [
            'display_name' => 'Almaz Guest House',
            'host_type' => 'individual_owner',
            'payout_schedule' => 'after_checkin',
        ]);

        $postResponse->assertRedirect('/dashboard?tab=stays');

        $this->assertDatabaseHas('hosts', [
            'user_id' => $user->id,
            'display_name' => 'Almaz Guest House',
            'host_type' => 'individual_owner',
        ]);

        $this->assertTrue($user->fresh()->hasRole('host'));
    }

    public function test_consumer_bookings_applications_and_leases_display_under_purchases_tab(): void
    {
        $tenantUser = $this->createUser('Tenant Consumer', '+251911000006');
        $landlord = $this->createUser('Landlord Provider', '+251911000007', ['property_owner']);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('category_id', $category->id)->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-000009',
            'owner_id' => $landlord->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Kazanchis Rental Suite',
        ]);

        // Tenant Profile
        $tProfile = TenantProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_code' => 'TNT-2026-000009',
            'user_id' => $tenantUser->id,
            'status' => 'active',
        ]);

        // Rental Application
        RentalApplication::query()->create([
            'uuid' => (string) Str::uuid(),
            'application_code' => 'APP-2026-000009',
            'property_id' => $property->id,
            'tenant_id' => $tenantUser->id,
            'tenant_profile_id' => $tProfile->id,
            'landlord_id' => $landlord->id,
            'desired_move_in_date' => '2026-11-01',
            'lease_duration_months' => 12,
            'proposed_monthly_rent_etb' => 35000.00,
            'payment_frequency' => 'monthly',
            'status' => 'submitted',
        ]);

        // Active Lease
        Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-2026-000009',
            'property_id' => $property->id,
            'tenant_id' => $tenantUser->id,
            'landlord_id' => $landlord->id,
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
            'monthly_rent_etb' => 35000.00,
            'security_deposit_etb' => 70000.00,
            'payment_frequency' => 'monthly',
            'due_day_of_month' => 1,
            'status' => 'active',
        ]);

        $response = $this->actingAs($tenantUser)->get('/dashboard?tab=purchases');

        $response->assertOk();
        $response->assertSee('Kazanchis Rental Suite');
        $response->assertSee('APP-2026-000009');
        $response->assertSee('LSE-2026-000009');
        $response->assertSee('35,000');
    }

    public function test_data_isolation_user_cannot_view_another_users_bookings_or_listings(): void
    {
        $userA = $this->createUser('User Alpha', '+251911000010');
        $userB = $this->createUser('User Beta', '+251911000011');

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('category_id', $category->id)->firstOrFail();

        $propertyB = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-000012',
            'owner_id' => $userB->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Private Property Beta',
        ]);

        $tProfileB = TenantProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_code' => 'TNT-2026-000012',
            'user_id' => $userB->id,
            'status' => 'active',
        ]);

        RentalApplication::query()->create([
            'uuid' => (string) Str::uuid(),
            'application_code' => 'APP-2026-SECRET',
            'property_id' => $propertyB->id,
            'tenant_id' => $userB->id,
            'tenant_profile_id' => $tProfileB->id,
            'landlord_id' => $userB->id,
            'desired_move_in_date' => '2026-11-01',
            'lease_duration_months' => 6,
            'proposed_monthly_rent_etb' => 20000.00,
            'payment_frequency' => 'monthly',
            'status' => 'submitted',
        ]);

        // User A visits their purchases
        $responseA = $this->actingAs($userA)->get('/dashboard?tab=purchases');
        $responseA->assertOk();
        $responseA->assertDontSee('APP-2026-SECRET');
        $responseA->assertDontSee('Private Property Beta');
    }

    public function test_guided_create_listing_entry_point_displays_6_choices(): void
    {
        $user = $this->createUser('Listing Creator', '+251911000012');

        $response = $this->actingAs($user)->get('/listings/create');

        $response->assertOk();
        $response->assertViewIs('listings.create-selector');
        $response->assertSee('What would you like to publish?');
        $response->assertSee('Property for Sale');
        $response->assertSee('Property for Rent');
        $response->assertSee('Commercial / Long Lease');
        $response->assertSee('Land / Plot');
        $response->assertSee('Short-Stay Accommodation');
        $response->assertSee('Development Project');
    }

    public function test_guided_branches_preconfigure_wizard_mode_and_category(): void
    {
        $user = $this->createUser('Branch Tester', '+251911000013');

        // 1. Sale Branch
        $resSale = $this->actingAs($user)->get('/listings/create?type=sale');
        $resSale->assertOk();
        $resSale->assertViewIs('listings.wizard');
        $this->assertSame('BUY', $resSale->viewData('initialTransactionMode'));

        // 2. Rent Branch
        $resRent = $this->actingAs($user)->get('/listings/create?type=rent');
        $resRent->assertOk();
        $this->assertSame('RENT', $resRent->viewData('initialTransactionMode'));

        // 3. Lease Branch
        $resLease = $this->actingAs($user)->get('/listings/create?type=lease');
        $resLease->assertOk();
        $this->assertSame('LEASE', $resLease->viewData('initialTransactionMode'));

        // 4. Land Branch (Category LND)
        $resLand = $this->actingAs($user)->get('/listings/create?type=land');
        $resLand->assertOk();
        $this->assertSame('LND', $resLand->viewData('initialCategoryCode'));
        $this->assertSame('land', $resLand->viewData('initialType'));

        // 5. Short Stay Branch
        $resStay = $this->actingAs($user)->get('/listings/create?type=stay');
        $resStay->assertOk();
        $this->assertSame('STAY', $resStay->viewData('initialTransactionMode'));
    }

    public function test_existing_property_reuse_attaches_listing_without_creating_new_property(): void
    {
        $user = $this->createUser('Multi Offer Owner', '+251911000014', ['property_owner']);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('category_id', $category->id)->firstOrFail();
        $listingType = ListingType::query()->where('transaction_mode', 'RENT')->firstOrFail();

        // 1. Existing registered property
        $existingProperty = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-000088',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Existing Bole Villa 88',
            'land_area_sqm' => 500,
            'bedrooms' => 4,
            'bathrooms' => 3,
        ]);

        $initialPropertyCount = Property::query()->count();

        // 2. Submit new commercial rental offer reusing existing physical asset
        $response = $this->actingAs($user)->post('/listings', [
            'use_existing_property' => 1,
            'existing_property_id' => (string) $existingProperty->id,
            'transaction_mode' => 'RENT',
            'listing_type_id' => $listingType->id,
            'title_en' => 'Bole Villa 88 for Rent',
            'price_etb' => 75000.00,
            'price_period' => 'per_month',
            'is_negotiable' => 1,
            'submit_action' => 'draft',
        ]);

        $response->assertRedirect();

        // Asset decoupling verification: No new Property record was inserted
        $this->assertSame($initialPropertyCount, Property::query()->count());

        // A new commercial listing was created for the existing property
        $this->assertDatabaseHas('listings', [
            'property_id' => $existingProperty->id,
            'published_by_user_id' => $user->id,
            'price_etb' => 75000.00,
            'status' => 'draft',
        ]);
    }

    public function test_unauthorized_user_cannot_reuse_another_users_property(): void
    {
        $owner = $this->createUser('Real Owner', '+251911000015');
        $attacker = $this->createUser('Bad Actor', '+251911000016');

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('category_id', $category->id)->firstOrFail();
        $listingType = ListingType::query()->where('transaction_mode', 'RENT')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-000099',
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Owner Private Villa',
        ]);

        // Attacker attempts to hijack owner's property
        $response = $this->actingAs($attacker)->post('/listings', [
            'use_existing_property' => 1,
            'existing_property_id' => (string) $property->id,
            'transaction_mode' => 'RENT',
            'listing_type_id' => $listingType->id,
            'title_en' => 'Hijacked Listing',
            'price_etb' => 50000.00,
            'price_period' => 'per_month',
            'submit_action' => 'draft',
        ]);

        $response->assertNotFound();
    }

    public function test_adaptive_navigation_exposes_admin_center_only_to_staff_users(): void
    {
        $regularUser = $this->createUser('Regular Customer', '+251911000017');
        $staffUser = $this->createUser('Admin Staff', '+251911000018', ['platform_admin']);

        // 1. Regular user does not see Administration links in header navigation
        $resRegular = $this->actingAs($regularUser)->get('/dashboard');
        $resRegular->assertOk();
        $resRegular->assertDontSee('Moderation Queue');

        // 2. Staff user sees Administration and Moderation Queue links
        $resStaff = $this->actingAs($staffUser)->get('/dashboard');
        $resStaff->assertOk();
        $resStaff->assertSee('Moderation Queue');
    }
}
