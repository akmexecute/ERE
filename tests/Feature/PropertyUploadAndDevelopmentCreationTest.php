<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Company\Models\Company;
use App\Domains\Developer\Enums\ProjectStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\DeveloperProfile;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Location\Models\Location;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertyUploadAndDevelopmentCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('s3_private');
    }

    public function test_user_can_submit_buy_property_with_photos_and_document(): void
    {
        $user = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'QA Test Owner',
            'phone' => '+251911999888',
            'status' => 'active',
            'verification_level' => 2,
        ]);

        $city = Location::where('level', 'city')->first() ?? Location::create([
            'name_en' => 'Addis Ababa',
            'slug' => 'addis-ababa',
            'level' => 'city',
            'code' => 'AA',
        ]);

        $subcity = Location::where('level', 'subcity')->first() ?? Location::create([
            'name_en' => 'Bole',
            'slug' => 'bole',
            'level' => 'subcity',
            'code' => 'BOLE',
            'parent_id' => $city->id,
        ]);

        $resCat = PropertyCategory::where('code', 'RES')->first() ?? PropertyCategory::create([
            'name_en' => 'Residential',
            'code' => 'RES',
            'slug' => 'residential',
            'is_active' => true,
        ]);

        $aptType = PropertyType::where('category_id', $resCat->id)->first() ?? PropertyType::create([
            'category_id' => $resCat->id,
            'name_en' => 'Apartment',
            'code' => 'APT',
            'slug' => 'apartment',
            'is_active' => true,
        ]);

        $listingType = ListingType::where('transaction_mode', 'BUY')->first() ?? ListingType::create([
            'name_en' => 'Standard Sale',
            'code' => 'BUY_SALE',
            'slug' => 'standard-sale',
            'transaction_mode' => 'BUY',
            'is_phase1_enabled' => true,
        ]);

        // 5 valid photos
        $photos = [
            UploadedFile::fake()->image('exterior.jpg', 800, 600)->size(500),
            UploadedFile::fake()->image('living_room.png', 800, 600)->size(600),
            UploadedFile::fake()->image('bedroom.webp', 800, 600)->size(400),
            UploadedFile::fake()->image('kitchen.jpg', 800, 600)->size(550),
            UploadedFile::fake()->image('compound.png', 800, 600)->size(700),
        ];

        // 1 valid deed document
        $document = UploadedFile::fake()->create('title_deed.pdf', 1200, 'application/pdf');

        $payload = [
            'transaction_mode' => 'BUY',
            'listing_type_id' => $listingType->id,
            'category_code' => 'RES',
            'property_type_code' => $aptType->code,
            'title_en' => 'QA Manual Property Creation — BUY — Bole',
            'description_en' => 'Luxury modern 2-bedroom apartment located in Bole with underground parking and elevator.',
            'city_id' => $city->id,
            'subcity_id' => $subcity->id,
            'land_area_sqm' => 120.0,
            'building_area_sqm' => 120.0,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'price_etb' => 8500000,
            'price_period' => 'total',
            'is_negotiable' => true,
            'photos' => $photos,
            'documents' => [$document],
            'submit_action' => 'moderation',
        ];

        $response = $this->actingAs($user)->post('/listings', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/dashboard');

        // Verify property was created in database
        $this->assertDatabaseHas('properties', [
            'title_en' => 'QA Manual Property Creation — BUY — Bole',
            'owner_id' => $user->id,
            'bedrooms' => 2,
            'bathrooms' => 2,
        ]);

        $property = Property::where('title_en', 'QA Manual Property Creation — BUY — Bole')->firstOrFail();
        $this->assertCount(5, $property->images);
        $this->assertDatabaseHas('listings', [
            'property_id' => $property->id,
            'transaction_mode' => 'BUY',
        ]);
    }

    public function test_user_can_submit_rent_and_lease_and_land_properties(): void
    {
        $user = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Multi Mode Landlord',
            'phone' => '+251911333444',
            'status' => 'active',
            'verification_level' => 2,
        ]);

        $city = Location::create([
            'name_en' => 'Addis Ababa',
            'slug' => 'addis-ababa',
            'level' => 'city',
            'code' => 'AA',
        ]);

        $subcity = Location::create([
            'name_en' => 'Kirkos',
            'slug' => 'kirkos',
            'level' => 'subcity',
            'code' => 'KIRKOS',
            'parent_id' => $city->id,
        ]);

        $resCat = PropertyCategory::create([
            'name_en' => 'Residential',
            'code' => 'RES',
            'slug' => 'residential',
            'is_active' => true,
        ]);

        $comCat = PropertyCategory::create([
            'name_en' => 'Commercial',
            'code' => 'COM',
            'slug' => 'commercial',
            'is_active' => true,
        ]);

        $lndCat = PropertyCategory::create([
            'name_en' => 'Land',
            'code' => 'LND',
            'slug' => 'land',
            'is_active' => true,
        ]);

        $aptType = PropertyType::create([
            'category_id' => $resCat->id,
            'name_en' => 'Apartment',
            'code' => 'APT',
            'slug' => 'apartment',
            'is_active' => true,
        ]);

        $offType = PropertyType::create([
            'category_id' => $comCat->id,
            'name_en' => 'Office',
            'code' => 'OFF',
            'slug' => 'office',
            'is_active' => true,
        ]);

        $plotType = PropertyType::create([
            'category_id' => $lndCat->id,
            'name_en' => 'Residential Land',
            'code' => 'RLND',
            'slug' => 'residential-land',
            'is_active' => true,
        ]);

        ListingType::create([
            'name_en' => 'Standard Rent',
            'code' => 'RENT_STD',
            'slug' => 'standard-rent',
            'transaction_mode' => 'RENT',
            'is_phase1_enabled' => true,
        ]);

        ListingType::create([
            'name_en' => 'Commercial Lease',
            'code' => 'LEASE_COM',
            'slug' => 'commercial-lease',
            'transaction_mode' => 'LEASE',
            'is_phase1_enabled' => true,
        ]);

        ListingType::create([
            'name_en' => 'Land Sale',
            'code' => 'LAND_SALE',
            'slug' => 'land-sale',
            'transaction_mode' => 'BUY',
            'is_phase1_enabled' => true,
        ]);

        ListingType::create([
            'name_en' => 'Short Stay',
            'code' => 'SHORT_STAY',
            'slug' => 'short-stay',
            'transaction_mode' => 'STAY',
            'is_phase1_enabled' => true,
        ]);

        // 1. Submit RENT Property
        $rentPayload = [
            'transaction_mode' => 'RENT',
            'category_code' => 'RES',
            'property_type_code' => 'APT',
            'title_en' => 'QA Manual RENT — Kazanchis Apartment',
            'description_en' => 'Fully furnished luxury apartment for monthly lease in Kirkos Kazanchis.',
            'city_id' => $city->id,
            'subcity_id' => $subcity->id,
            'land_area_sqm' => 110.0,
            'building_area_sqm' => 110.0,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'price_etb' => 35000,
            'price_period' => 'per_month',
            'is_negotiable' => true,
            'photos' => [UploadedFile::fake()->image('rent1.jpg', 600, 400)],
        ];
        $rentRes = $this->actingAs($user)->post('/listings', $rentPayload);
        $rentRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('listings', ['transaction_mode' => 'RENT', 'price_period' => 'per_month']);

        // 2. Submit LEASE Property
        $leasePayload = [
            'transaction_mode' => 'LEASE',
            'category_code' => 'COM',
            'property_type_code' => 'OFF',
            'title_en' => 'QA Manual LEASE — Bole Office Space',
            'description_en' => 'Modern office floor space available for corporate multi-year lease.',
            'city_id' => $city->id,
            'subcity_id' => $subcity->id,
            'land_area_sqm' => 250.0,
            'building_area_sqm' => 250.0,
            'bedrooms' => 0,
            'bathrooms' => 2,
            'price_etb' => 180000,
            'price_period' => 'per_year',
            'is_negotiable' => true,
            'photos' => [UploadedFile::fake()->image('lease1.jpg', 600, 400)],
        ];
        $leaseRes = $this->actingAs($user)->post('/listings', $leasePayload);
        $leaseRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('listings', ['transaction_mode' => 'LEASE', 'price_period' => 'per_year']);

        // 3. Submit LAND Property (No bedrooms/bathrooms required)
        $landPayload = [
            'transaction_mode' => 'BUY',
            'category_code' => 'LND',
            'property_type_code' => 'RLND',
            'title_en' => 'QA Manual LAND — Summit Plot',
            'description_en' => 'Clear title deed residential plot ready for construction.',
            'city_id' => $city->id,
            'subcity_id' => $subcity->id,
            'land_area_sqm' => 300.0,
            'price_etb' => 6500000,
            'price_period' => 'total',
            'is_negotiable' => true,
            'photos' => [UploadedFile::fake()->image('land1.jpg', 600, 400)],
        ];
        $landRes = $this->actingAs($user)->post('/listings', $landPayload);
        $landRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('properties', ['title_en' => 'QA Manual LAND — Summit Plot', 'land_area_sqm' => 300.0]);

        // 4. Submit STAY Property (Auto creates StayListingProfile)
        $stayPayload = [
            'transaction_mode' => 'STAY',
            'category_code' => 'RES',
            'property_type_code' => 'APT',
            'title_en' => 'QA Manual STAY — Bole Studio',
            'description_en' => 'Short stay studio apartment with WiFi and daily cleaning.',
            'city_id' => $city->id,
            'subcity_id' => $subcity->id,
            'land_area_sqm' => 50.0,
            'building_area_sqm' => 50.0,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'price_etb' => 2500,
            'price_period' => 'per_night',
            'is_negotiable' => false,
            'photos' => [UploadedFile::fake()->image('stay1.jpg', 600, 400)],
        ];
        $stayRes = $this->actingAs($user)->post('/listings', $stayPayload);
        $stayRes->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stay_listing_profiles', ['base_nightly_rate_etb' => 2500.0]);
    }

    public function test_authorized_developer_can_create_project_and_add_unit(): void
    {
        $user = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'QA Developer Admin',
            'phone' => '+251911777666',
            'status' => 'active',
            'verification_level' => 3,
        ]);

        $company = Company::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'owner_user_id' => $user->id,
            'legal_name' => 'Addis Test Developments PLC',
            'trade_name' => 'Addis Test Towers',
            'company_type' => 'developer',
            'verification_level' => 3,
            'status' => 'verified',
        ]);

        $dev = DeveloperProfile::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'developer_code' => 'DEV-ETH-000777',
            'license_number' => 'DEV/LIC/2026/777',
            'establishment_year' => 2021,
            'delivered_projects_count' => 2,
            'delivered_units_count' => 40,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $city = Location::create([
            'name_en' => 'Addis Ababa',
            'slug' => 'addis-ababa',
            'level' => 'city',
            'code' => 'AA',
        ]);

        // 1. Visit Create Project Form
        $createPageResponse = $this->actingAs($user)->get('/projects/create');
        $createPageResponse->assertOk();
        $createPageResponse->assertSee('Create New Development Project');

        // 2. Submit New Project
        $projectPayload = [
            'title_en' => 'QA Manual Development Creation — Addis Test Residences',
            'title_am' => 'አዲስ ቴስት ሬዚደንስ',
            'description_en' => 'State of the art high rise development with panoramic skyline views.',
            'company_id' => $company->id,
            'city_id' => $city->id,
            'address_line' => 'Bole Rwanda, Addis Ababa',
            'project_type' => 'residential_complex',
            'status' => 'under_construction',
            'land_area_sqm' => 4000.0,
            'building_permit_number' => 'BP-2026-QA-001',
            'expected_completion_date' => '2028-06-30',
            'overall_progress_pct' => 25.0,
        ];

        $postProjectResponse = $this->actingAs($user)->post('/projects', $projectPayload);
        $postProjectResponse->assertSessionHasNoErrors();

        $project = DevelopmentProject::where('slug', 'qa-manual-development-creation-addis-test-residences')->firstOrFail();
        $this->assertEquals('QA Manual Development Creation — Addis Test Residences', $project->title_en);
        $this->assertEquals(ProjectStatus::UnderConstruction, $project->status);
        $this->assertEquals($company->id, $project->company_id);

        // 3. Add Unit to Project
        $unitPayload = [
            'unit_number' => 'A-101',
            'unit_type' => '2br',
            'floor_number' => 1,
            'area_sqm' => 110.0,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'price_etb' => 8800000.0,
            'orientation' => 'north_east',
            'parking_allocation' => 1,
            'status' => 'available',
        ];

        $postUnitResponse = $this->actingAs($user)->post("/projects/{$project->slug}/units", $unitPayload);
        $postUnitResponse->assertSessionHasNoErrors();
        $postUnitResponse->assertRedirect("/projects/{$project->slug}/manage?tab=units");

        // Verify Unit in Database
        $this->assertDatabaseHas('project_units', [
            'project_id' => $project->id,
            'unit_number' => 'A-101',
            'price_etb' => 8800000.0,
            'status' => UnitInventoryStatus::Available,
        ]);

        // 4. Verify Public Showcase Page displays project and unit
        $showResponse = $this->get("/projects/{$project->slug}");
        $showResponse->assertOk();
        $showResponse->assertSee('QA Manual Development Creation — Addis Test Residences');
        $showResponse->assertSee('A-101');
    }
}
