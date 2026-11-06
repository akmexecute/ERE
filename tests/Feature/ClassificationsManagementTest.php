<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyAmenity;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyFeature;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Property\Services\PropertyTaxonomyValidationService;
use App\Domains\Document\Actions\GenerateDocumentSignedUrlAction;
use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Document\Models\Document;
use App\Domains\User\Models\User;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\EthiopianTaxRulesSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ClassificationsManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private PropertyTaxonomyValidationService $taxonomyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(EthiopianTaxRulesSeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);

        $this->admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Taxonomy Admin',
            'phone' => '+251911999888',
            'status' => 'active',
        ]);
        $this->admin->assignRoleBySlug('super_admin');

        $this->regularUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular Member',
            'phone' => '+251911222333',
            'status' => 'active',
        ]);

        $this->taxonomyService = app(PropertyTaxonomyValidationService::class);
    }

    public function test_unauthorized_user_cannot_access_or_modify_classifications(): void
    {
        // 1. Unauthenticated request to central page redirects to login
        $guestResponse = $this->get('/admin/classifications');
        $guestResponse->assertRedirect('/login');

        // 2. Regular user receives 403 Forbidden
        $userResponse = $this->actingAs($this->regularUser)->get('/admin/classifications');
        $userResponse->assertForbidden();

        // 3. Regular user cannot create property type
        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $unauthPost = $this->actingAs($this->regularUser)->postJson('/admin/taxonomy/property-types', [
            'category_id' => $category->id,
            'name_en' => 'Unauthorized Type',
            'code' => 'UNAUTH',
            'area_basis' => 'building_area',
        ]);
        $unauthPost->assertForbidden();
    }

    public function test_authorized_admin_can_view_central_classifications_page_and_all_groups(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/classifications');
        $response->assertOk();
        $response->assertViewIs('admin.taxonomy.index');

        // Verify all 8 groups are rendered
        $response->assertSee('Classifications Management');
        $response->assertSee('1. Residential');
        $response->assertSee('2. Land');
        $response->assertSee('3. Commercial');
        $response->assertSee('4. Industrial');
        $response->assertSee('5. Hospitality');
        $response->assertSee('6. Listing Modes');
        $response->assertSee('7. Amenities');
        $response->assertSee('8. Dynamic Features');
    }

    public function test_authorized_admin_can_create_property_types_for_principal_categories(): void
    {
        $resCat = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $lndCat = PropertyCategory::query()->where('code', 'LND')->firstOrFail();

        // 1. Create a Residential Property Type
        $resPost = $this->actingAs($this->admin)->postJson('/admin/taxonomy/property-types', [
            'category_id' => $resCat->id,
            'name_en' => 'Luxury Penthouse Suite',
            'name_am' => 'የቅንጦት ፔንትሀውስ',
            'code' => 'LUX_PENT',
            'slug' => 'luxury-penthouse-suite',
            'area_basis' => 'building_area',
            'requires_bedrooms' => true,
            'requires_floors' => true,
            'sort_order' => 15,
            'is_active' => true,
        ]);
        $resPost->assertCreated();
        $this->assertDatabaseHas('property_types', [
            'category_id' => $resCat->id,
            'code' => 'LUX_PENT',
            'name_en' => 'Luxury Penthouse Suite',
        ]);

        // 2. Create a Land Property Type
        $lndPost = $this->actingAs($this->admin)->postJson('/admin/taxonomy/property-types', [
            'category_id' => $lndCat->id,
            'name_en' => 'Eco-Tourism Land',
            'name_am' => 'የኢኮ-ቱሪዝም መሬት',
            'code' => 'ECO_LAND',
            'area_basis' => 'land_area',
            'requires_bedrooms' => false,
            'requires_floors' => false,
            'sort_order' => 8,
            'is_active' => true,
        ]);
        $lndPost->assertCreated();
        $this->assertDatabaseHas('property_types', [
            'category_id' => $lndCat->id,
            'code' => 'ECO_LAND',
            'name_en' => 'Eco-Tourism Land',
        ]);
    }

    public function test_duplicate_property_type_code_is_rejected(): void
    {
        $resCat = PropertyCategory::query()->where('code', 'RES')->firstOrFail();

        // Existing code 'VILLA'
        $response = $this->actingAs($this->admin)->postJson('/admin/taxonomy/property-types', [
            'category_id' => $resCat->id,
            'name_en' => 'Duplicate Villa',
            'code' => 'VILLA',
            'area_basis' => 'building_area',
        ]);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_category_and_property_type_relationship_validation(): void
    {
        $resCat = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $officeType = PropertyType::query()->where('code', 'OFFICE')->firstOrFail(); // Belongs to COM

        // 1. Office belongs to Commercial, not Residential
        $this->expectException(ValidationException::class);
        $this->taxonomyService->validateCategoryAndType($resCat->id, $officeType->id);
    }

    public function test_authorized_admin_can_create_update_toggle_and_safely_delete_listing_modes(): void
    {
        // 1. Create a new listing mode
        $createRes = $this->actingAs($this->admin)->postJson('/admin/taxonomy/listing-types', [
            'code' => 'SHORT_TERM_RENT',
            'slug' => 'short-term-rental',
            'transaction_mode' => 'RENT',
            'name_en' => 'Short-Term Rental',
            'name_am' => 'አጭር ጊዜ ኪራይ',
            'default_price_period' => 'per_month',
            'sort_order' => 12,
            'is_phase1_enabled' => true,
        ]);
        $createRes->assertCreated();
        $createdId = $createRes->json('listing_type.id');

        $listingType = ListingType::query()->findOrFail($createdId);
        $this->assertEquals('SHORT_TERM_RENT', $listingType->code);
        $this->assertTrue($listingType->is_phase1_enabled);

        // 2. Update listing mode
        $updateRes = $this->actingAs($this->admin)->putJson("/admin/taxonomy/listing-types/{$listingType->id}", [
            'code' => 'SHORT_TERM_RENT',
            'transaction_mode' => 'RENT',
            'name_en' => 'Short-Term Serviced Rental',
            'default_price_period' => 'per_month',
            'is_phase1_enabled' => true,
        ]);
        $updateRes->assertOk();
        $this->assertEquals('Short-Term Serviced Rental', $listingType->fresh()->name_en);

        // 3. Toggle listing mode status
        $toggleRes = $this->actingAs($this->admin)->postJson("/admin/taxonomy/listing-types/{$listingType->id}/toggle");
        $toggleRes->assertOk();
        $this->assertFalse($listingType->fresh()->is_phase1_enabled);

        // 4. Safe delete when unreferenced succeeds
        $deleteRes = $this->actingAs($this->admin)->deleteJson("/admin/taxonomy/listing-types/{$listingType->id}");
        $deleteRes->assertOk();
        $this->assertDatabaseMissing('listing_types', ['id' => $createdId]);

        // 5. Safe delete when referenced is rejected
        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $property = Property::query()->first();
        if ($property) {
            DB::table('listings')->insert([
                'ulid' => (string) Str::ulid(),
                'property_id' => $property->id,
                'listing_type_id' => $saleType->id,
                'slug' => 'test-active-sale-listing-' . Str::random(5),
                'transaction_mode' => 'BUY',
                'price_etb' => 5000000,
                'price_period' => 'total',
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $blockedDelete = $this->actingAs($this->admin)->deleteJson("/admin/taxonomy/listing-types/{$saleType->id}");
            $blockedDelete->assertUnprocessable();
            $blockedDelete->assertJsonValidationErrors(['listing_type']);
        }
    }

    public function test_authorized_admin_can_create_update_toggle_and_safely_delete_amenities(): void
    {
        // 1. Create a new Amenity
        $createRes = $this->actingAs($this->admin)->postJson('/admin/taxonomy/amenities', [
            'group_key' => 'utilities',
            'name_en' => 'EV Fast Charging Station',
            'name_am' => 'የኤሌክትሪክ መኪና ቻርጀር',
            'slug' => 'ev_charging_station',
            'icon' => '🔌',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $createRes->assertCreated();
        $amenityId = $createRes->json('amenity.id');

        $amenity = PropertyAmenity::query()->findOrFail($amenityId);
        $this->assertEquals('ev_charging_station', $amenity->slug);
        $this->assertTrue($amenity->is_active);

        // 2. Update Amenity
        $updateRes = $this->actingAs($this->admin)->putJson("/admin/taxonomy/amenities/{$amenity->id}", [
            'group_key' => 'utilities',
            'name_en' => 'Level 2 EV Fast Charger',
            'slug' => 'ev_charging_station',
            'icon' => '⚡',
            'sort_order' => 11,
            'is_active' => true,
        ]);
        $updateRes->assertOk();
        $this->assertEquals('Level 2 EV Fast Charger', $amenity->fresh()->name_en);

        // 3. Toggle Amenity
        $toggleRes = $this->actingAs($this->admin)->postJson("/admin/taxonomy/amenities/{$amenity->id}/toggle");
        $toggleRes->assertOk();
        $this->assertFalse($amenity->fresh()->is_active);

        // 4. Safe delete when unreferenced succeeds
        $deleteRes = $this->actingAs($this->admin)->deleteJson("/admin/taxonomy/amenities/{$amenity->id}");
        $deleteRes->assertOk();
        $this->assertDatabaseMissing('property_amenities', ['id' => $amenityId]);
    }

    public function test_authorized_admin_can_create_update_and_validate_dynamic_features(): void
    {
        // 1. Create a multi-select dynamic feature for Industrial and Commercial
        $createRes = $this->actingAs($this->admin)->postJson('/admin/taxonomy/features', [
            'name_en' => 'Loading Dock Access Types',
            'name_am' => 'የመጫኛ ዶክ አይነት',
            'slug' => 'loading_dock_types',
            'input_type' => 'multi_select',
            'options_json' => ['Drive-In Bay', 'Cross Dock', 'Tailgate Leveler'],
            'applicable_category_codes' => ['IND', 'COM'],
            'sort_order' => 20,
            'is_active' => true,
        ]);
        $createRes->assertCreated();
        $featureId = $createRes->json('feature.id');

        $feature = PropertyFeature::query()->findOrFail($featureId);
        $this->assertEquals('loading_dock_types', $feature->slug);
        $this->assertEquals('multi_select', $feature->input_type);
        $this->assertEquals(['Drive-In Bay', 'Cross Dock', 'Tailgate Leveler'], $feature->options_json);

        // 2. Validate feature values with valid options
        $indCategory = PropertyCategory::query()->where('code', 'IND')->firstOrFail();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();

        $validated = $this->taxonomyService->validateFeatures($indCategory, [
            'loading_dock_types' => ['Drive-In Bay', 'Cross Dock'],
        ]);
        $this->assertArrayHasKey('loading_dock_types', $validated);
        $this->assertEquals('["Drive-In Bay","Cross Dock"]', $validated['loading_dock_types']);

        // 3. Validate feature values with invalid option throws ValidationException
        try {
            $this->taxonomyService->validateFeatures($indCategory, [
                'loading_dock_types' => ['Invalid Dock Type'],
            ]);
            $this->fail('Expected ValidationException was not thrown for invalid option.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('features.loading_dock_types', $e->errors());
        }

        // 4. Validate feature with incompatible category throws ValidationException
        try {
            $this->taxonomyService->validateFeatures($resCategory, [
                'loading_dock_types' => ['Drive-In Bay'],
            ]);
            $this->fail('Expected ValidationException was not thrown for incompatible category.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('features.loading_dock_types', $e->errors());
        }
    }

    public function test_newly_created_classifications_are_immediately_available_in_posting_wizard(): void
    {
        // 1. Create a new Hospitality Property Type
        $hspCat = PropertyCategory::query()->where('code', 'HSP')->firstOrFail();
        $newType = PropertyType::query()->create([
            'category_id' => $hspCat->id,
            'code' => 'BOUTIQUE_RESORT',
            'slug' => 'boutique-resort',
            'name_en' => 'Boutique Eco Resort',
            'area_basis' => 'building_area',
            'requires_bedrooms' => true,
            'requires_floors' => false,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        // 2. Create a new Amenity
        $newAmenity = PropertyAmenity::query()->create([
            'group_key' => 'lifestyle',
            'slug' => 'infinity_rooftop_pool',
            'name_en' => 'Infinity Rooftop Pool',
            'is_active' => true,
            'sort_order' => 25,
        ]);

        // 3. Load Posting Wizard
        $response = $this->actingAs($this->regularUser)->get(route('listings.create', ['type' => 'sale']));
        $response->assertOk();
        $response->assertViewIs('listings.wizard');

        // Verify newly added classifications are present in the response
        $response->assertSee('Boutique Eco Resort');
        $response->assertSee('Infinity Rooftop Pool');
    }

    public function test_authorized_admin_can_create_new_property_category_and_attach_types(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/taxonomy/categories', [
            'name_en' => 'Special Purpose',
            'name_am' => 'ልዩ አገልግሎት',
            'code' => 'SPC_TEST',
            'slug' => 'special-purpose-test',
            'icon' => '🏛️',
            'sort_order' => 60,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('property_categories', [
            'code' => 'SPC_TEST',
            'name_en' => 'Special Purpose',
        ]);

        $spc = PropertyCategory::query()->where('code', 'SPC_TEST')->firstOrFail();

        // Attach a property type to the new category
        $typeRes = $this->actingAs($this->admin)->postJson('/admin/taxonomy/property-types', [
            'category_id' => $spc->id,
            'code' => 'CLINIC_TEST',
            'name_en' => 'Health Facility / Clinic',
            'area_basis' => 'building_area',
            'requires_bedrooms' => false,
            'requires_floors' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $typeRes->assertCreated();
        $this->assertDatabaseHas('property_types', [
            'code' => 'CLINIC_TEST',
            'category_id' => $spc->id,
        ]);
    }

    public function test_duplicate_category_code_is_rejected_with_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/taxonomy/categories', [
            'name_en' => 'Duplicate Residential',
            'code' => 'RES', // Existing default code
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_dynamic_feature_can_be_associated_with_custom_category(): void
    {
        // 1. Create a custom category
        $cat = PropertyCategory::query()->create([
            'code' => 'CIVIC',
            'name_en' => 'Civic & Infrastructure',
            'slug' => 'civic-infrastructure',
            'is_active' => true,
        ]);

        // 2. Associate a dynamic feature with the custom category
        $featureRes = $this->actingAs($this->admin)->postJson('/admin/taxonomy/features', [
            'name_en' => 'Public Access Road Width',
            'slug' => 'road_width_m',
            'input_type' => 'number',
            'unit' => 'm',
            'applicable_category_codes' => ['CIVIC'],
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $featureRes->assertCreated();
        $this->assertDatabaseHas('property_features', [
            'slug' => 'road_width_m',
        ]);

        $feature = PropertyFeature::query()->where('slug', 'road_width_m')->firstOrFail();
        $this->assertContains('CIVIC', $feature->applicable_category_codes);
    }

    public function test_document_signed_url_authorization_allows_staff_roles_and_rejects_unauthorized_users(): void
    {
        // 1. Create owner and property
        $owner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Property Owner',
            'phone' => '+251911444555',
            'status' => 'active',
        ]);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00009999',
            'title_en' => 'Test Property',
            'category_id' => PropertyCategory::query()->where('code', 'RES')->value('id'),
            'property_type_id' => PropertyType::query()->where('code', 'VILLA')->value('id'),
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'country_id' => 1,
            'region_id' => 1,
            'city_id' => 1,
            'subcity_id' => 1,
            'woreda_id' => 1,
            'latitude' => 9.01,
            'longitude' => 38.75,
            'status' => 'available',
        ]);

        // 2. Create private document
        $document = Document::query()->create([
            'documentable_type' => Property::class,
            'documentable_id' => $property->id,
            'document_type_code' => 'title_deed',
            'title' => 'Official Title Deed',
            'access_level' => DocumentAccessLevel::Private,
            'uploaded_by' => $owner->id,
        ]);

        $action = app(GenerateDocumentSignedUrlAction::class);

        // 3. Verification Officer role can access
        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Officer',
            'phone' => '+251911777888',
            'status' => 'active',
        ]);
        $officer->assignRoleBySlug('verification_officer');
        $action->authorizeAccess($officer, $document); // Should not throw

        // 4. Property Admin role can access
        $propAdmin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Prop Admin',
            'phone' => '+251911777999',
            'status' => 'active',
        ]);
        $propAdmin->assignRoleBySlug('property_admin');
        $action->authorizeAccess($propAdmin, $document); // Should not throw

        // 5. Unrelated regular user throws AuthorizationException
        $this->expectException(AuthorizationException::class);
        $action->authorizeAccess($this->regularUser, $document);
    }
}
