<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Document\Actions\UploadDocumentVersionAction;
use App\Domains\Document\Enums\DocumentAccessLevel;
use App\Domains\Listing\Actions\CreateOrUpdateListingAction;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Actions\GeneratePropertyCodeAction;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Property\Services\ImageSanitizerAndHasherService;
use App\Domains\User\Models\User;
use Database\Seeders\PropertyTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PropertyAndListingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PropertyTaxonomySeeder::class);
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

    public function test_strips_exif_metadata_to_webp_and_computes_16_hex_difference_hash(): void
    {
        $service = new ImageSanitizerAndHasherService();

        // Create a temporary 100x100 truecolor test image
        $tempSource = tempnam(sys_get_temp_dir(), 'test_img_') . '.png';
        $img = imagecreatetruecolor(100, 100);
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

        @unlink($tempSource);
        @unlink($destWebp);
    }

    public function test_uploads_versioned_documents_to_private_vault_with_access_level_verification_only(): void
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

        $action = app(UploadDocumentVersionAction::class);

        $doc = $action->execute(
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
        $this->assertSame(1, $doc->versions->first()->version_number);

        // Upload version 2
        $file2 = UploadedFile::fake()->create('carta_deed_v2.pdf', 1200, 'application/pdf');
        $updatedDoc = $action->execute(
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
}
