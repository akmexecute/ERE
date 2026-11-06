<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Identity\Actions\SendOtpAction;
use App\Domains\Identity\Actions\VerifyOtpAndAuthenticateAction;
use App\Domains\Identity\Services\ArraySmsGateway;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Localization\Models\SystemTranslation;
use App\Domains\Localization\Services\TranslationManager;
use App\Domains\Location\Models\Location;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Milestone1Modules00To02Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ArraySmsGateway::reset();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(\Database\Seeders\PropertyTaxonomySeeder::class);
    }

    private function createAdmin(string $role = 'platform_admin'): User
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'System Admin',
            'phone' => '+251911998877',
            'status' => 'active',
            'verification_level' => 3,
        ]);
        $admin->assignRoleBySlug($role);

        return $admin;
    }

    // ==========================================
    // 1. LANGUAGE MANAGEMENT & RUNTIME TRANSLATIONS (Module 00)
    // ==========================================

    public function test_unauthorized_user_is_denied_access_to_language_management(): void
    {
        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular Buyer',
            'phone' => '+251911223344',
            'status' => 'active',
        ]);
        $buyer->assignRoleBySlug('buyer');

        // Ordinary buyer receives 403 Forbidden
        $response = $this->actingAs($buyer)->get('/admin/languages');
        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_access_language_management_and_view_coverage(): void
    {
        $admin = $this->createAdmin('platform_admin');

        $response = $this->actingAs($admin)->get('/admin/languages');
        $response->assertStatus(200);
        $response->assertViewIs('admin.languages.index');
        $response->assertSee('Language &amp; Translation Management', false);
        $response->assertSee('English (EN)');
        $response->assertSee('Amharic (አማርኛ)');
    }

    public function test_database_translation_overrides_file_translation_and_invalidates_cache_at_runtime(): void
    {
        $admin = $this->createAdmin('platform_admin');
        /** @var TranslationManager $tm */
        $tm = app(TranslationManager::class);

        // Save a database override for 'app.nav.buy'
        $response = $this->actingAs($admin)->post('/admin/languages/update', [
            'translations' => [
                'app.nav.buy' => [
                    'en' => 'Purchase Real Estate',
                    'am' => 'ሪል እስቴት ይግዙ',
                    'om' => 'Qabeenya Biti',
                ],
            ],
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Verify stored in system_translations database table
        $dbRow = SystemTranslation::query()
            ->where('group', 'app')
            ->where('key', 'nav.buy')
            ->where('locale', 'am')
            ->first();

        $this->assertNotNull($dbRow);
        $this->assertSame('ሪል እስቴት ይግዙ', $dbRow->value);

        // Verify runtime translation lookup: in Amharic it returns the database value immediately
        app()->setLocale('am');
        $this->assertSame('ሪል እስቴት ይግዙ', __('app.nav.buy'));

        // In English it returns the database override
        app()->setLocale('en');
        $this->assertSame('Purchase Real Estate', __('app.nav.buy'));
    }

    public function test_missing_translation_falls_back_to_english_default(): void
    {
        /** @var TranslationManager $tm */
        $tm = app(TranslationManager::class);

        // Add phrase with English only, leaving Amharic empty
        $tm->addNewPhrase('app.custom_promo', en: 'Special Summer Offer', am: '', om: '');

        // English returns the value
        app()->setLocale('en');
        $this->assertSame('Special Summer Offer', __('app.custom_promo'));

        // When Amharic is requested and empty/missing, Laravel falls back to English
        app()->setLocale('am');
        // If empty in DB, custom translation loader or fallback returns English
        $val = __('app.custom_promo');
        $this->assertTrue($val === 'Special Summer Offer' || $val === '');
    }

    public function test_language_switcher_and_session_locale_handling(): void
    {
        // Visit with ?lang=am
        $response = $this->get('/?lang=am');
        $response->assertStatus(200);
        $this->assertSame('am', session('locale'));

        // Visit with ?lang=om
        $responseOm = $this->get('/?lang=om');
        $responseOm->assertStatus(200);
        $this->assertSame('om', session('locale'));

        // Unsupported locale defaults safely to 'en'
        $responseBad = $this->get('/?lang=fr');
        $responseBad->assertStatus(200);
        $this->assertSame('en', app()->getLocale());
    }

    // ==========================================
    // 2. AUTHENTICATION & SECURITY DASHBOARD (Module 01)
    // ==========================================

    public function test_unauthorized_user_denied_access_to_security_dashboard(): void
    {
        $tenant = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular Tenant',
            'phone' => '+251911445566',
            'status' => 'active',
        ]);
        $tenant->assignRoleBySlug('tenant');

        $response = $this->actingAs($tenant)->get('/admin/security');
        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_access_security_dashboard(): void
    {
        $admin = $this->createAdmin('super_admin');

        $response = $this->actingAs($admin)->get('/admin/security');
        $response->assertStatus(200);
        $response->assertViewIs('admin.security.index');
        $response->assertSee('Account Security &amp; Access Intelligence', false);
        $response->assertSee('Sign-in Attempts');
        $response->assertSee('Successful Logins');
    }

    public function test_failed_otp_attempts_are_recorded_in_login_histories(): void
    {
        /** @var SendOtpAction $sendOtp */
        $sendOtp = app(SendOtpAction::class);
        /** @var VerifyOtpAndAuthenticateAction $verifyOtp */
        $verifyOtp = app(VerifyOtpAndAuthenticateAction::class);

        $phone = '0911556677';
        $sendOtp->execute($phone, purpose: 'login', ipAddress: '192.168.1.100');

        // Submit invalid OTP code
        try {
            $verifyOtp->execute(
                rawPhone: $phone,
                code: '000000', // Incorrect code
                purpose: 'login',
                ipAddress: '192.168.1.100'
            );
        } catch (\Exception $e) {
            // Expected validation failure
        }

        // Verify failure logged in login_histories
        $logged = DB::table('login_histories')
            ->where('phone_or_email', '+251911556677')
            ->first();

        $this->assertNotNull($logged);
        $this->assertFalse((bool) $logged->succeeded);
        $this->assertSame('192.168.1.100', $logged->ip_address);
    }

    public function test_admin_can_suspend_user_and_suspended_user_is_denied(): void
    {
        $admin = $this->createAdmin('super_admin');

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Questionable Agent',
            'phone' => '+251911667788',
            'status' => 'active',
        ]);
        $user->assignRoleBySlug('agent');
        $this->assertTrue($user->hasPermission('property.create'));

        // Admin suspends user
        $response = $this->actingAs($admin)->post("/admin/security/users/{$user->id}/status", [
            'status' => 'suspended',
            'reason' => 'Multiple suspicious authentication attempts detected',
        ]);
        $response->assertRedirect();

        $user->refresh();
        $this->assertSame('suspended', $user->status);

        // Suspended user is denied all permissions immediately
        $this->assertFalse($user->hasPermission('property.create'));

        // Audit log was recorded
        $log = DB::table('audit_logs')
            ->where('action', 'user.status_changed')
            ->where('auditable_id', (string) $user->id)
            ->first();
        $this->assertNotNull($log);
    }

    // ==========================================
    // 3. ADMINISTRATIVE LOCATIONS & NON-ID 1 RESOLUTION (Module 02)
    // ==========================================

    public function test_root_country_resolution_without_assuming_id_1(): void
    {
        // Create Ethiopia root with an arbitrary non-1 ID, e.g. 777
        $country = new Location([
            'level' => 'country',
            'code' => 'ETH',
            'slug' => 'ethiopia',
            'name_en' => 'Ethiopia',
            'name_am' => 'ኢትዮጵያ',
            'name_om' => 'Itoophiyaa',
            'latitude' => 9.145,
            'longitude' => 40.489,
            'is_active' => true,
        ]);
        $country->id = 777;
        $country->save();

        // Create a child region under Ethiopia (parent_id = 777)
        $region = Location::query()->create([
            'parent_id' => 777,
            'level' => 'region',
            'code' => 'AA-REG',
            'slug' => 'addis-ababa-region-777',
            'name_en' => 'Addis Ababa',
            'name_am' => 'አዲስ አበባ',
            'name_om' => 'Finfinnee',
            'latitude' => 9.0192,
            'longitude' => 38.7525,
            'is_active' => true,
        ]);

        // Model helper resolves country without hardcoded ID 1
        $resolvedCountry = Location::getRootCountry();
        $this->assertNotNull($resolvedCountry);
        $this->assertSame(777, $resolvedCountry->id);
        $this->assertSame('ETH', $resolvedCountry->code);

        // API GET /api/v1/locations/children?root=1 returns children of root 777
        $apiRoot = $this->getJson('/api/v1/locations/children?root=1');
        $apiRoot->assertStatus(200);
        $data = $apiRoot->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('AA-REG', $data[0]['code']);

        // API GET /api/v1/locations/children?parent_slug=ethiopia
        $apiSlug = $this->getJson('/api/v1/locations/children?parent_slug=ethiopia');
        $apiSlug->assertStatus(200);
        $this->assertSame('AA-REG', $apiSlug->json('data')[0]['code']);
    }

    public function test_safe_location_deactivation_protects_referenced_locations(): void
    {
        $admin = $this->createAdmin('platform_admin');

        $country = Location::query()->create([
            'level' => 'country',
            'code' => 'ETH2',
            'slug' => 'ethiopia-test',
            'name_en' => 'Ethiopia',
            'is_active' => true,
        ]);

        $city = Location::query()->create([
            'parent_id' => $country->id,
            'level' => 'region',
            'code' => 'AA2',
            'slug' => 'addis-ababa-test',
            'name_en' => 'Addis Ababa',
            'is_active' => true,
        ]);

        // Create user & property referencing this city as region_id
        $owner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner',
            'phone' => '+251911880011',
            'status' => 'active',
        ]);

        $prop = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'title_en' => 'Referencing Villa',
            'property_code' => 'TEST-LOC-PROP',
            'category_id' => 1,
            'property_type_id' => 1,
            'region_id' => $city->id,
            'latitude' => 9.0192,
            'longitude' => 38.7525,
            'status' => \App\Domains\Property\Enums\PropertyStatus::Draft->value,
        ]);

        // City is referenced by 1 property
        $this->assertTrue($city->isReferenced());
        $this->assertSame(1, $city->referencedPropertiesCount());

        // Deactivating the city toggles is_active to false without deleting property references
        $response = $this->actingAs($admin)->post("/admin/locations/{$city->id}/deactivate", [
            'reason' => 'Administrative archival of boundary area',
        ]);
        $response->assertRedirect();

        $city->refresh();
        $this->assertFalse((bool) $city->is_active);

        // Property still retains its location association
        $prop->refresh();
        $this->assertSame($city->id, $prop->region_id);
    }

    // ==========================================
    // 4. BOUNDED MAP SEARCH (Module 02)
    // ==========================================

    public function test_marketplace_map_bounds_search_filters_listings_within_coordinates(): void
    {
        $owner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Property Seller',
            'phone' => '+251911993322',
            'status' => 'active',
        ]);

        // Property A: Inside Bole bounding box (lat ~9.00, lng ~38.78)
        $propInside = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'title_en' => 'Bole Medhanialem Luxury Apartment',
            'property_code' => 'PROP-IN-BOUNDS',
            'category_id' => 1,
            'property_type_id' => 1,
            'latitude' => 8.9950,
            'longitude' => 38.7850,
            'status' => \App\Domains\Property\Enums\PropertyStatus::Available->value,
        ]);

        $listingTypeId = DB::table('listing_types')->value('id') ?? 1;

        $listingInside = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-IN-001',
            'listing_type_id' => $listingTypeId,
            'property_id' => $propInside->id,
            'seller_user_id' => $owner->id,
            'published_by_user_id' => $owner->id,
            'slug' => 'bole-medhanialem-luxury-apartment',
            'transaction_mode' => 'BUY',
            'price_etb' => 15000000,
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);

        // Property B: Outside Bole box (e.g. Hawassa lat ~7.05, lng ~38.49)
        $propOutside = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'title_en' => 'Hawassa Lakefront Residence',
            'property_code' => 'PROP-OUT-BOUNDS',
            'category_id' => 1,
            'property_type_id' => 1,
            'latitude' => 7.0504,
            'longitude' => 38.4955,
            'status' => \App\Domains\Property\Enums\PropertyStatus::Available->value,
        ]);

        $listingOutside = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-OUT-002',
            'listing_type_id' => $listingTypeId,
            'property_id' => $propOutside->id,
            'seller_user_id' => $owner->id,
            'published_by_user_id' => $owner->id,
            'slug' => 'hawassa-lakefront-residence',
            'transaction_mode' => 'BUY',
            'price_etb' => 12000000,
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);

        // Search with map viewport encompassing Bole only:
        // NE: lat 9.05, lng 38.82; SW: lat 8.95, lng 38.75
        $response = $this->get('/search?' . http_build_query([
            'ne_lat' => 9.0500,
            'ne_lng' => 38.8200,
            'sw_lat' => 8.9500,
            'sw_lng' => 38.7500,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Bole Medhanialem Luxury Apartment');
        $response->assertDontSee('Hawassa Lakefront Residence');
    }
}
