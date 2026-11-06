<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Document\Actions\GenerateDocumentSignedUrlAction;
use App\Domains\Document\Actions\UploadDocumentVersionAction;
use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Listing\Actions\CreateOrUpdateListingAction;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Actions\GeneratePropertyCodeAction;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Property\Services\ImageSanitizerAndHasherService;
use App\Domains\Property\Services\PropertyTaxonomyValidationService;
use App\Domains\User\Models\User;
use Database\Seeders\PropertyTaxonomySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class PropertyAndListingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(\Database\Seeders\EthiopianLocationsSeeder::class);
    }

    public function test_seeder_is_idempotent_and_preserves_administrator_customizations(): void
    {
        // 1. Admin disables the Commercial category and updates its sort_order
        $comCat = PropertyCategory::query()->where('code', 'COM')->firstOrFail();
        $comCat->update(['is_active' => false, 'sort_order' => 99]);

        // 2. Re-run PropertyTaxonomySeeder
        $this->seed(PropertyTaxonomySeeder::class);

        // 3. Category must remain disabled and keep its custom sort_order
        $refreshed = PropertyCategory::query()->where('code', 'COM')->firstOrFail();
        $this->assertFalse($refreshed->is_active, 'Admin disabled status must not be overwritten by seeder.');
        $this->assertSame(99, $refreshed->sort_order, 'Admin custom ordering must not be overwritten by seeder.');
    }

    public function test_validates_category_and_property_type_compatibility(): void
    {
        $validator = new PropertyTaxonomyValidationService();

        // Valid: Residential + Villa
        $res = $validator->validateCategoryAndType('RES', 'VILLA');
        $this->assertSame('RES', $res['category']->code);
        $this->assertSame('VILLA', $res['propertyType']->code);

        // Invalid: Residential + Office (Office belongs to Commercial COM)
        $this->expectException(ValidationException::class);
        $validator->validateCategoryAndType('RES', 'OFFICE');
    }

    public function test_validates_dynamic_property_features_against_configured_schema(): void
    {
        $validator = new PropertyTaxonomyValidationService();
        $landCategory = PropertyCategory::query()->where('code', 'LND')->firstOrFail();

        // Valid features
        $validated = $validator->validateFeatures($landCategory, [
            'zoning_type' => 'commercial',
            'topography' => 'flat',
            'road_access_width_m' => '20',
        ]);

        $this->assertSame('commercial', $validated['zoning_type']);
        $this->assertSame('flat', $validated['topography']);
        $this->assertSame('20', $validated['road_access_width_m']);

        // Invalid option for topography
        $this->expectException(ValidationException::class);
        $validator->validateFeatures($landCategory, [
            'topography' => 'underwater_volcano', // Unallowed option!
        ]);
    }

    public function test_generates_sequential_human_readable_property_codes_atomically(): void
    {
        $generator = new GeneratePropertyCodeAction();

        $code1 = $generator->execute('ETH', 'AA', 'BOLE', 'RES');
        $code2 = $generator->execute('ETH', 'AA', 'BOLE', 'RES');
        $code3 = $generator->execute('ETH', 'HAWASSA', 'TABOR', 'COM');

        $this->assertSame('ETH-AA-BOLE-RES-00000001', $code1);
        $this->assertSame('ETH-AA-BOLE-RES-00000002', $code2);
        $this->assertSame('ETH-HAWASSA-TABOR-COM-00000001', $code3);
    }

    public function test_creates_listing_and_calculates_dynamic_price_per_sqm_using_property_type_area_basis(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abebe Bikila',
            'phone' => '+251911223344',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail(); // area_basis = land_area
        $aptType = PropertyType::query()->where('code', 'APT')->firstOrFail(); // area_basis = building_area

        // 1. Villa: 300 m² land, 240 m² building. Price: 20,000,000 ETB. Basis is land_area -> 20,000,000 / 300 = 66,666.67
        $villa = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001001',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Luxury G+2 Villa in Bole',
            'land_area_sqm' => 300,
            'building_area_sqm' => 240,
            'status' => PropertyStatus::Available,
        ]);

        $saleListingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        $action = app(CreateOrUpdateListingAction::class);
        $listing = $action->execute($user, $villa, [
            'listing_type_id' => $saleListingType->id,
            'price_etb' => 20_000_000,
            'price_period' => 'total',
            'is_negotiable' => true,
        ]);

        $this->assertSame(66666.67, (float) $listing->price_per_sqm_etb);
        $this->assertSame('BUY', $listing->transaction_mode);
        $this->assertStringStartsWith('LST-', $listing->listing_code);

        // 2. Apartment: 120 m² building, land_area null. Price: 12,000,000 ETB. Basis is building_area -> 12,000,000 / 120 = 100,000
        $apt = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001002',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'property_type_id' => $aptType->id,
            'title_en' => 'Modern 3-Bedroom Apartment in Kazanchis',
            'building_area_sqm' => 120,
            'status' => PropertyStatus::Available,
        ]);

        $aptListing = $action->execute($user, $apt, [
            'listing_type_id' => $saleListingType->id,
            'price_etb' => 12_000_000,
            'price_period' => 'total',
            'is_negotiable' => false,
        ]);

        $this->assertSame(100000.00, (float) $aptListing->price_per_sqm_etb);
    }

    public function test_strict_area_basis_does_not_silently_substitute_unconfigured_area(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tirunesh Dibaba',
            'phone' => '+251911334455',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail(); // area_basis = land_area

        // Villa has building_area_sqm = 250, but land_area_sqm is NULL
        $villaWithoutLandArea = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001099',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Villa with Missing Land Area',
            'land_area_sqm' => null,
            'building_area_sqm' => 250,
            'status' => PropertyStatus::Available,
        ]);

        $saleListingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $action = app(CreateOrUpdateListingAction::class);

        $listing = $action->execute($user, $villaWithoutLandArea, [
            'listing_type_id' => $saleListingType->id,
            'price_etb' => 15_000_000,
            'price_period' => 'total',
        ]);

        // Per prompt requirement 9.1: Must NOT silently substitute building_area! Price/m² must be null
        $this->assertNull($listing->price_per_sqm_etb);
    }

    public function test_records_immutable_price_history_when_listing_price_is_updated(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Almaz Ayana',
            'phone' => '+251911998877',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001003',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Villa for Sale',
            'land_area_sqm' => 200,
            'status' => PropertyStatus::Available,
        ]);

        $saleListingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $action = app(CreateOrUpdateListingAction::class);

        $listing = $action->execute($user, $property, [
            'listing_type_id' => $saleListingType->id,
            'price_etb' => 15_000_000,
            'price_period' => 'total',
        ]);

        $this->assertCount(0, $listing->priceHistories);

        // Update price to 14,000,000 ETB
        $action->execute($user, $property, [
            'listing_type_id' => $saleListingType->id,
            'price_etb' => 14_000_000,
            'price_period' => 'total',
            'price_change_reason' => 'Negotiated holiday discount',
        ], $listing);

        $refreshed = $listing->fresh();
        $this->assertSame(14000000.0, (float) $refreshed->price_etb);
        $this->assertCount(1, $refreshed->priceHistories);
        $this->assertSame(15000000.0, (float) $refreshed->priceHistories->first()->old_price_etb);
        $this->assertSame(14000000.0, (float) $refreshed->priceHistories->first()->new_price_etb);
        $this->assertSame(-1000000.0, (float) $refreshed->priceHistories->first()->change_amount_etb);
        $this->assertSame(-6.67, (float) $refreshed->priceHistories->first()->change_percentage);
    }

    public function test_prevents_duplicate_active_listings_for_the_same_transaction_mode_on_one_property(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Haile Gebrselassie',
            'phone' => '+251911445566',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001004',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Commercial Compound',
            'land_area_sqm' => 500,
            'status' => PropertyStatus::Available,
        ]);

        $saleListingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $action = app(CreateOrUpdateListingAction::class);

        $listing = $action->execute($user, $property, [
            'listing_type_id' => $saleListingType->id,
            'price_etb' => 30_000_000,
            'price_period' => 'total',
        ]);

        // Publish listing
        $listing->update(['status' => ListingStatus::Published->value]);

        // Attempting to create another FOR_SALE listing on same property must fail
        $this->expectException(ValidationException::class);
        $action->execute($user, $property, [
            'listing_type_id' => $saleListingType->id,
            'price_etb' => 28_000_000,
            'price_period' => 'total',
        ]);
    }

    public function test_strips_exif_metadata_to_webp_and_generates_responsive_variants(): void
    {
        $service = new ImageSanitizerAndHasherService();

        // Create a temporary 400x300 truecolor test image
        $tempSource = tempnam(sys_get_temp_dir(), 'test_img_') . '.png';
        $img = imagecreatetruecolor(400, 300);
        $bgColor = imagecolorallocate($img, 15, 118, 110);
        imagefill($img, 0, 0, $bgColor);
        imagepng($img, $tempSource);
        imagedestroy($img);

        $destWebp = tempnam(sys_get_temp_dir(), 'test_clean_') . '.webp';

        $service->stripExifAndSaveWebp($tempSource, $destWebp);

        $this->assertFileExists($destWebp);
        $this->assertGreaterThan(0, filesize($destWebp));

        $hash = $service->computePerceptualHash($destWebp);
        $this->assertSame(16, strlen($hash));
        $this->assertTrue(ctype_xdigit($hash));

        // Test responsive variant generation (thumb 320w, medium 800w, large 1600w)
        $variantsDir = sys_get_temp_dir() . '/variants_' . Str::random(6);
        $variants = $service->generateVariants($destWebp, $variantsDir, 'test_sample');

        $this->assertFileExists($variants['thumb']);
        $this->assertFileExists($variants['medium']);
        $this->assertFileExists($variants['large']);

        @unlink($tempSource);
        @unlink($destWebp);
        @unlink($variants['thumb']);
        @unlink($variants['medium']);
        @unlink($variants['large']);
        @rmdir($variantsDir);
    }

    public function test_rejects_unallowed_documentable_polymorphic_types(): void
    {
        Storage::fake('s3_private');

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Feyisa Lilesa',
            'phone' => '+251911889900',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');
        $action = app(UploadDocumentVersionAction::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Disallowed documentable type');

        // Attempting to attach to arbitrary unvetted PHP class
        $action->execute(
            uploader: $user,
            documentableType: 'ArbitraryDisallowedClass',
            documentableId: 1,
            documentTypeCode: 'deed',
            title: 'Deed',
            file: $file
        );
    }

    public function test_uploads_versioned_documents_and_issues_5_minute_signed_url_with_audit_log(): void
    {
        Storage::fake('s3_private');

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Derartu Tulu',
            'phone' => '+251911776655',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001005',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Property with Carta Document',
            'status' => PropertyStatus::Available,
        ]);

        $file1 = UploadedFile::fake()->create('carta_deed_v1.pdf', 1024, 'application/pdf');
        $uploadAction = app(UploadDocumentVersionAction::class);

        $doc = $uploadAction->execute(
            uploader: $user,
            documentableType: $property->getMorphClass(),
            documentableId: $property->id,
            documentTypeCode: 'ownership_deed',
            title: 'Title Deed (Carta)',
            file: $file1,
            accessLevel: DocumentAccessLevel::VerificationOnly,
            changeNotes: 'Initial document upload'
        );

        $this->assertSame(1, $doc->current_version_number);
        $this->assertSame(DocumentAccessLevel::VerificationOnly, $doc->access_level);
        $this->assertCount(1, $doc->versions);

        // Upload version 2
        $file2 = UploadedFile::fake()->create('carta_deed_v2.pdf', 1200, 'application/pdf');
        $updatedDoc = $uploadAction->execute(
            uploader: $user,
            documentableType: $property->getMorphClass(),
            documentableId: $property->id,
            documentTypeCode: 'ownership_deed',
            title: 'Title Deed (Carta)',
            file: $file2,
            existingDocument: $doc,
            changeNotes: 'Updated with sub-city seal'
        );

        $this->assertSame(2, $updatedDoc->current_version_number);
        $this->assertCount(2, $updatedDoc->versions);

        // Issue 5-minute signed URL flow
        $urlAction = app(GenerateDocumentSignedUrlAction::class);
        $signedData = $urlAction->execute(
            requester: $user,
            document: $updatedDoc,
            versionNumber: 2,
            ipAddress: '197.156.104.22',
            userAgent: 'Mozilla/5.0 Test Suite'
        );

        $this->assertNotEmpty($signedData['url']);
        $this->assertSame(2, $signedData['version_number']);
        $this->assertTrue($signedData['expires_at']->greaterThan(now()));

        // Audit log must have been created in document_access_logs
        $this->assertDatabaseHas('document_access_logs', [
            'document_id' => $updatedDoc->id,
            'user_id' => $user->id,
            'action' => 'signed_url_issued',
            'ip_address' => '197.156.104.22',
        ]);

        // Unauthorized user access must throw AuthorizationException
        $stranger = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stranger Danger',
            'phone' => '+251911999999',
            'status' => 'active',
        ]);

        $this->expectException(AuthorizationException::class);
        $urlAction->execute(requester: $stranger, document: $updatedDoc);
    }

    public function test_authenticated_user_can_load_5_step_listing_wizard(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Kenenisa Bekele',
            'phone' => '+251911112233',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/listings/create');

        $response->assertOk()
            ->assertSee('Post a Property')
            ->assertSee('Listing Quality Score')
            ->assertSee('Auto-Calculated Price per m²');
    }

    public function test_wizard_submission_persists_property_amenities_features_and_submits_listing_for_moderation(): void
    {
        Storage::fake('public');
        Storage::fake('s3_private');

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Selemon Barega',
            'phone' => '+251911556677',
            'status' => 'active',
        ]);

        $aaRegion = DB::table('locations')->where('code', 'AA-REG')->first();
        $aaCity = DB::table('locations')->where('code', 'AA')->first();
        $boleSubcity = DB::table('locations')->where('code', 'BOLE')->first();

        $response = $this->actingAs($user)->post('/listings', [
            'transaction_mode' => 'BUY',
            'category_code' => 'RES',
            'property_type_code' => 'VILLA',
            'title_en' => 'Splendid 4-Bedroom Villa in Bole',
            'title_am' => 'ዘመናዊ ባለ 4 መኝታ ቪላ በቦሌ',
            'description_en' => 'Magnificent brand new G+2 villa situated in prime Bole with backup generator, water tanker, and modern asphalt road access.',
            'land_area_sqm' => 350,
            'building_area_sqm' => 280,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'floors' => 2,
            'parking_spaces' => 2,
            'latitude' => 8.9894,
            'longitude' => 38.7890,
            'region_id' => $aaRegion?->id,
            'city_id' => $aaCity?->id,
            'subcity_id' => $boleSubcity?->id,
            'price_etb' => 25_000_000,
            'price_period' => 'total',
            'is_negotiable' => 1,
            'amenities' => ['wifi', 'backup-generator', 'water-tank'],
            'features' => [
                'road_access_width_m' => '15',
            ],
            'submit_action' => 'moderation',
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('status');

        $property = Property::query()->where('title_en', 'Splendid 4-Bedroom Villa in Bole')->firstOrFail();
        $this->assertSame($user->id, $property->owner_id);
        $this->assertSame(350.0, (float) $property->land_area_sqm);
        $this->assertCount(3, $property->amenities);

        $listing = Listing::query()->where('property_id', $property->id)->firstOrFail();
        $this->assertSame(ListingStatus::PendingReview, $listing->status);
        $this->assertNotNull($listing->submitted_at);
        $this->assertSame(71428.57, (float) $listing->price_per_sqm_etb); // 25,000,000 / 350 = 71,428.57
        $this->assertGreaterThanOrEqual(60, $listing->quality_score);
    }

    public function test_authorized_user_can_download_document_version_via_route(): void
    {
        Storage::fake('s3_private');

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Kenenisa Bekele',
            'phone' => '+251911991122',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001090',
            'owner_id' => $user->id,
            'category_id' => $category->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Deed Download Property',
            'status' => PropertyStatus::Available,
        ]);

        $file = UploadedFile::fake()->create('carta.pdf', 512, 'application/pdf');
        $action = app(UploadDocumentVersionAction::class);
        $doc = $action->execute(
            uploader: $user,
            documentableType: $property->getMorphClass(),
            documentableId: $property->id,
            documentTypeCode: 'ownership_deed',
            title: 'Ownership Deed',
            file: $file
        );

        $version = $doc->versions()->where('version_number', 1)->firstOrFail();
        $signedUrl = $version->generateTemporarySignedUrl(minutes: 5);

        // An unsigned request MUST be rejected with 403 Forbidden
        $unsignedResponse = $this->actingAs($user)->get("/documents/download/{$doc->uuid}/v/1");
        $unsignedResponse->assertForbidden();

        // A valid cryptographically signed request MUST succeed
        $response = $this->actingAs($user)->get($signedUrl);
        $response->assertOk();

        // Audit log must record 'downloaded' action for actual downloads
        $this->assertDatabaseHas('document_access_logs', [
            'document_id' => $doc->id,
            'user_id' => $user->id,
            'action' => 'downloaded',
        ]);
    }

    public function test_is_negotiable_boolean_normalization_and_validation_suite(): void
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Almaz Ayana',
            'phone' => '+251911882233',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $aaCity = DB::table('locations')->where('code', 'AA')->first();
        $boleSubcity = DB::table('locations')->where('code', 'BOLE')->first();

        $basePayload = [
            'transaction_mode' => 'BUY',
            'category_code' => 'RES',
            'property_type_code' => 'VILLA',
            'description_en' => 'Test property for is_negotiable regression verification suite.',
            'land_area_sqm' => 200,
            'building_area_sqm' => 150,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price_etb' => 15_000_000,
            'price_period' => 'total',
            'city_id' => $aaCity?->id,
            'subcity_id' => $boleSubcity?->id,
            'submit_action' => 'moderation',
        ];

        // 1. Property creation with is_negotiable = true
        $res1 = $this->actingAs($user)->post('/listings', array_merge($basePayload, [
            'title_en' => 'Negotiable Property Test 1',
            'is_negotiable' => true,
        ]));
        $res1->assertRedirect('/dashboard');
        $listing1 = Listing::query()->whereHas('property', fn ($q) => $q->where('title_en', 'Negotiable Property Test 1'))->firstOrFail();
        $this->assertTrue((bool) $listing1->is_negotiable);

        // 2. Property creation with is_negotiable = false
        $res2 = $this->actingAs($user)->post('/listings', array_merge($basePayload, [
            'title_en' => 'Non-Negotiable Property Test 2',
            'is_negotiable' => false,
        ]));
        $res2->assertRedirect('/dashboard');
        $listing2 = Listing::query()->whereHas('property', fn ($q) => $q->where('title_en', 'Non-Negotiable Property Test 2'))->firstOrFail();
        $this->assertFalse((bool) $listing2->is_negotiable);

        // 3. Normal HTML form checkbox behavior (checked submits 'on' or '1')
        $res3 = $this->actingAs($user)->post('/listings', array_merge($basePayload, [
            'title_en' => 'HTML Checkbox Checked Property Test 3',
            'is_negotiable' => 'on',
        ]));
        $res3->assertRedirect('/dashboard');
        $listing3 = Listing::query()->whereHas('property', fn ($q) => $q->where('title_en', 'HTML Checkbox Checked Property Test 3'))->firstOrFail();
        $this->assertTrue((bool) $listing3->is_negotiable);

        // 4. Unchecked checkbox submission (submits '0' from hidden input)
        $res4 = $this->actingAs($user)->post('/listings', array_merge($basePayload, [
            'title_en' => 'HTML Checkbox Unchecked Property Test 4',
            'is_negotiable' => '0',
        ]));
        $res4->assertRedirect('/dashboard');
        $listing4 = Listing::query()->whereHas('property', fn ($q) => $q->where('title_en', 'HTML Checkbox Unchecked Property Test 4'))->firstOrFail();
        $this->assertFalse((bool) $listing4->is_negotiable);

        // 5. JSON boolean submission
        $res5True = $this->actingAs($user)->postJson('/listings', array_merge($basePayload, [
            'title_en' => 'JSON Boolean True Property Test 5A',
            'is_negotiable' => true,
        ]));
        $res5True->assertRedirect('/dashboard');
        $listing5True = Listing::query()->whereHas('property', fn ($q) => $q->where('title_en', 'JSON Boolean True Property Test 5A'))->firstOrFail();
        $this->assertTrue((bool) $listing5True->is_negotiable);

        $res5False = $this->actingAs($user)->postJson('/listings', array_merge($basePayload, [
            'title_en' => 'JSON Boolean False Property Test 5B',
            'is_negotiable' => false,
        ]));
        $res5False->assertRedirect('/dashboard');
        $listing5False = Listing::query()->whereHas('property', fn ($q) => $q->where('title_en', 'JSON Boolean False Property Test 5B'))->firstOrFail();
        $this->assertFalse((bool) $listing5False->is_negotiable);

        // 6. Invalid boolean values MUST be rejected with strict validation error
        $res6 = $this->actingAs($user)->post('/listings', array_merge($basePayload, [
            'title_en' => 'Invalid Boolean Property Test 6',
            'is_negotiable' => 'maybe_or_invalid_string',
        ]));
        $res6->assertSessionHasErrors(['is_negotiable']);
        $this->assertSame(
            'The is negotiable field must be true or false.',
            session('errors')->first('is_negotiable')
        );

        // 7. Property editing and resubmission (updating negotiable state)
        $action = app(CreateOrUpdateListingAction::class);
        $updatedListing = $action->execute(
            actor: $user,
            property: $listing1->property,
            payload: [
                'listing_type_id' => $listing1->listing_type_id,
                'price_etb' => 14_500_000,
                'is_negotiable' => false,
            ],
            existingListing: $listing1
        );
        $this->assertFalse((bool) $updatedListing->is_negotiable);

        // 8. Wizard submission with string '1' / '0' representations
        $res8 = $this->actingAs($user)->post('/listings', array_merge($basePayload, [
            'title_en' => 'Wizard String One Property Test 8',
            'is_negotiable' => '1',
        ]));
        $res8->assertRedirect('/dashboard');
        $listing8 = Listing::query()->whereHas('property', fn ($q) => $q->where('title_en', 'Wizard String One Property Test 8'))->firstOrFail();
        $this->assertTrue((bool) $listing8->is_negotiable);
    }
}
