<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Document\Actions\GenerateDocumentSignedUrlAction;
use App\Domains\Document\Actions\UploadDocumentVersionAction;
use App\Domains\Listing\Actions\CreateOrUpdateListingAction;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Location\Models\Location;
use App\Domains\Moderation\Actions\ModerateListingAction;
use App\Domains\Property\Actions\GeneratePropertyCodeAction;
use App\Domains\Property\Actions\RecordPropertyStatusHistoryAction;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyAmenity;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyFeature;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Property\Services\ImageSanitizerAndHasherService;
use App\Domains\User\Models\User;
use App\Jobs\ProcessPropertyImageJob;
use App\Support\Money\EtbFormatter;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class Phase4Milestone2HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);
        $this->seed(RbacRolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('s3_private');
    }

    private function createUser(string $name = 'Abebe Bikila', ?string $role = null): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'phone' => '+2519' . random_int(10000000, 99999999),
            'status' => 'active',
        ]);

        if ($role) {
            $roleRecord = DB::table('roles')->where('slug', $role)->first();
            if ($roleRecord) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleRecord->id,
                    'assigned_at' => now(),
                ]);
            }
        }

        return $user;
    }

    public function test_backend_strictly_rejects_incompatible_or_missing_property_type_without_defaulting_to_villa(): void
    {
        $user = $this->createUser();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $comCategory = PropertyCategory::query()->where('code', 'COM')->firstOrFail();
        $officeType = PropertyType::query()->where('category_id', $comCategory->id)->firstOrFail();
        $listingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        // 1. Missing property_type_id must throw validation error, NOT silently default to VILLA
        $responseMissing = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'listing_type_id' => $listingType->id,
            'title_en' => 'Test Missing Type',
            'price_etb' => 5_000_000,
        ]);

        $responseMissing->assertSessionHasErrors(['property_type_id']);

        // 2. Incompatible category and property type (Commercial type submitted under Residential category)
        $responseIncompatible = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'property_type_id' => $officeType->id,
            'listing_type_id' => $listingType->id,
            'title_en' => 'Test Incompatible Type',
            'price_etb' => 5_000_000,
        ]);

        $responseIncompatible->assertSessionHasErrors(['property_type_id']);
    }

    public function test_dynamic_location_derived_property_code_generation_across_different_regions(): void
    {
        $generator = app(GeneratePropertyCodeAction::class);

        // Addis Ababa Bole
        $codeAA = $generator->execute(countryCode: 'ETH', cityCode: 'AA', areaCode: 'BOLE', categoryCode: 'RES');
        $this->assertStringStartsWith('ETH-AA-BOLE-RES-', $codeAA);

        // Hawassa
        $codeHawassa = $generator->execute(countryCode: 'ETH', cityCode: 'HAWASSA', areaCode: 'CENTRAL', categoryCode: 'RES');
        $this->assertStringStartsWith('ETH-HAWASSA-CENTRAL-RES-', $codeHawassa);

        // Adama
        $codeAdama = $generator->execute(countryCode: 'ETH', cityCode: 'ADAMA', areaCode: 'BAREDO', categoryCode: 'RES');
        $this->assertStringStartsWith('ETH-ADAMA-BAREDO-RES-', $codeAdama);

        // Atomic sequences are distinct per prefix
        $codeAASecond = $generator->execute(countryCode: 'ETH', cityCode: 'AA', areaCode: 'BOLE', categoryCode: 'RES');
        $this->assertNotEquals($codeAA, $codeAASecond);
    }

    public function test_wizard_submission_clears_draft_on_success_and_preserves_on_validation_failure(): void
    {
        $user = $this->createUser();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $listingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $city = Location::query()->where('code', 'AA')->firstOrFail();
        $subcity = Location::query()->where('code', 'BOLE')->firstOrFail();

        // 1. Validation failure preserves input and does NOT set clear_wizard_draft
        $invalidResponse = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => '', // Required field missing
            'price_etb' => 12_000_000,
        ]);

        $invalidResponse->assertSessionHasErrors(['title_en']);
        $invalidResponse->assertSessionMissing('clear_wizard_draft');

        // 2. Successful creation flashes clear_wizard_draft
        $validResponse = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'city_id' => $city->id,
            'subcity_id' => $subcity->id,
            'title_en' => 'Complete Luxury Villa',
            'listing_type_id' => $listingType->id,
            'price_etb' => 20_000_000,
            'land_area_sqm' => 350,
            'building_area_sqm' => 280,
            'latitude' => 8.9806,
            'longitude' => 38.7578,
        ]);

        $validResponse->assertRedirect(route('dashboard'));
        $validResponse->assertSessionHas('clear_wizard_draft', true);
    }

    public function test_user_cannot_attach_listing_to_another_users_property(): void
    {
        $owner = $this->createUser('True Owner');
        $attacker = $this->createUser('Malicious Actor');

        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $listingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        $ownerProperty = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00009999',
            'owner_id' => $owner->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Owner Private Villa',
            'status' => PropertyStatus::Available,
        ]);

        $response = $this->actingAs($attacker)->post(route('listings.store'), [
            'use_existing_property' => '1',
            'existing_property_id' => $ownerProperty->id,
            'listing_type_id' => $listingType->id,
            'price_etb' => 10_000_000,
        ]);

        $response->assertSessionHasErrors(['existing_property_id']);
    }

    public function test_document_vault_cryptographic_signed_url_security_lifecycle(): void
    {
        $owner = $this->createUser('Deed Holder');
        $stranger = $this->createUser('Unauthorized Bystander');

        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00007777',
            'owner_id' => $owner->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Property with Private Deed',
            'status' => PropertyStatus::Available,
        ]);

        $file = UploadedFile::fake()->create('ownership_carta.pdf', 1024, 'application/pdf');
        $uploadAction = app(UploadDocumentVersionAction::class);
        $document = $uploadAction->execute(
            uploader: $owner,
            documentableType: $property->getMorphClass(),
            documentableId: $property->id,
            documentTypeCode: 'ownership_deed',
            title: 'Sub-city Carta',
            file: $file
        );

        $urlAction = app(GenerateDocumentSignedUrlAction::class);
        $signedData = $urlAction->execute($owner, $document, 1);
        $validSignedUrl = $signedData['url'];

        // 1. Unsigned URL access must be rejected with 403 Forbidden
        $unsignedResponse = $this->actingAs($owner)->get("/documents/download/{$document->uuid}/v/1");
        $unsignedResponse->assertForbidden();

        // 2. Forged / Tampered signature must be rejected with 403 Forbidden
        $tamperedUrl = $validSignedUrl . 'extra_junk';
        $forgedResponse = $this->actingAs($owner)->get($tamperedUrl);
        $forgedResponse->assertForbidden();

        // 3. Unauthorized user (even with signed URL) must be rejected with 403 Forbidden
        $unauthorizedResponse = $this->actingAs($stranger)->get($validSignedUrl);
        $unauthorizedResponse->assertForbidden();

        // 4. Valid signed link within 5-minute lifetime must succeed for authorized owner
        $validResponse = $this->actingAs($owner)->get($validSignedUrl);
        $validResponse->assertOk();

        // 5. Access log must record 'downloaded' action
        $this->assertDatabaseHas('document_access_logs', [
            'document_id' => $document->id,
            'user_id' => $owner->id,
            'action' => 'downloaded',
        ]);

        // 6. Expired link (time traveled 6 minutes forward) must be rejected with 403 Forbidden
        $this->travel(6)->minutes();
        $expiredResponse = $this->actingAs($owner)->get($validSignedUrl);
        $expiredResponse->assertForbidden();
    }

    public function test_image_sanitizer_dimension_validation_and_non_destructive_watermarking(): void
    {
        $service = new ImageSanitizerAndHasherService();

        // Create a temporary valid image
        $tempDir = sys_get_temp_dir() . '/ere_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        $testSource = $tempDir . '/source.png';
        $canvas = imagecreatetruecolor(200, 200);
        $color = imagecolorallocate($canvas, 100, 150, 200);
        imagefilledrectangle($canvas, 0, 0, 200, 200, $color);
        imagepng($canvas, $testSource);
        imagedestroy($canvas);

        $sanitizedDest = $tempDir . '/sanitized.webp';
        $service->stripExifAndSaveWebp($testSource, $sanitizedDest);

        $this->assertFileExists($sanitizedDest);

        // Generate derivatives
        $variants = $service->generateVariants($sanitizedDest, $tempDir, 'variant_test');
        $this->assertFileExists($variants['thumb']);
        $this->assertFileExists($variants['medium']);

        // Apply watermark to public medium variant
        $originalHash = md5_file($sanitizedDest);
        $service->applyWatermark($variants['medium'], 'ERE Verified');

        // Original sanitized image must remain unchanged
        $this->assertSame($originalHash, md5_file($sanitizedDest));

        // Cleanup
        @unlink($testSource);
        @unlink($sanitizedDest);
        @unlink($variants['thumb']);
        @unlink($variants['medium']);
        @unlink($variants['large']);
        @rmdir($tempDir);
    }

    public function test_property_status_history_atomic_recording(): void
    {
        $user = $this->createUser('Agent Tester');
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00003333',
            'owner_id' => $user->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Status History Test Villa',
            'status' => PropertyStatus::Draft,
        ]);

        $action = app(RecordPropertyStatusHistoryAction::class);

        // Transition from draft to available
        $updated = $action->execute($property, PropertyStatus::Available, $user, 'Verification documents approved');

        $this->assertSame(PropertyStatus::Available, $updated->status);
        $this->assertDatabaseHas('property_status_histories', [
            'property_id' => $property->id,
            'from_status' => PropertyStatus::Draft->value,
            'to_status' => PropertyStatus::Available->value,
            'changed_by' => $user->id,
            'reason' => 'Verification documents approved',
        ]);

        // Attempt transition to same status must NOT create duplicate history record
        $action->execute($updated, PropertyStatus::Available, $user, 'Redundant transition');
        $this->assertCount(2, $property->fresh()->statusHistories);
    }

    public function test_listing_moderation_approval_and_rejection_workflow(): void
    {
        $seller = $this->createUser('Seller Abebe');
        $moderator = $this->createUser('Listing Moderator', 'moderator');
        $unauthorizedUser = $this->createUser('Regular Buyer');

        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $listingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00005555',
            'owner_id' => $seller->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Villa for Moderation',
            'status' => PropertyStatus::Available,
        ]);

        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-0000888',
            'slug' => 'villa-for-moderation',
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $listingType->id,
            'transaction_mode' => 'sale',
            'price_etb' => 18_000_000,
            'status' => ListingStatus::PendingReview,
        ]);

        // 1. Unauthorized user cannot approve or reject
        $unauthApprove = $this->actingAs($unauthorizedUser)->postJson("/admin/listings/{$listing->id}/approve");
        $unauthApprove->assertForbidden();

        // 2. Authorized moderator can reject listing with feedback
        $rejectResponse = $this->actingAs($moderator)->postJson("/admin/listings/{$listing->id}/reject", [
            'reason_code' => 'misleading_photos',
            'feedback_to_seller' => 'Please provide clear exterior photos of the compound.',
        ]);

        $rejectResponse->assertOk();
        $this->assertSame(ListingStatus::Rejected, $listing->fresh()->status);
        $this->assertDatabaseHas('moderation_actions', [
            'listing_id' => $listing->id,
            'moderator_id' => $moderator->id,
            'action_type' => 'reject',
            'reason_code' => 'misleading_photos',
        ]);

        // 3. Authorized moderator can approve listing
        $approveResponse = $this->actingAs($moderator)->postJson("/admin/listings/{$listing->id}/approve");
        $approveResponse->assertOk();
        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
        $this->assertNotNull($listing->fresh()->published_at);
        $this->assertDatabaseHas('moderation_actions', [
            'listing_id' => $listing->id,
            'moderator_id' => $moderator->id,
            'action_type' => 'approve',
        ]);
    }

    public function test_property_taxonomy_admin_management_and_safe_deletion(): void
    {
        $admin = $this->createUser('Super Admin', 'super_admin');
        $stranger = $this->createUser('Regular User');

        // 1. Unauthorized user receives 403 Forbidden
        $unauthResponse = $this->actingAs($stranger)->getJson('/admin/taxonomy');
        $unauthResponse->assertForbidden();

        // 2. Authorized admin can load taxonomy
        $authResponse = $this->actingAs($admin)->getJson('/admin/taxonomy');
        $authResponse->assertOk();
        $authResponse->assertJsonStructure(['categories', 'property_types', 'listing_types', 'amenities', 'features']);

        // 3. Toggle category active status
        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $toggleResponse = $this->actingAs($admin)->postJson("/admin/taxonomy/categories/{$category->id}/toggle");
        $toggleResponse->assertOk();
        $this->assertFalse($category->fresh()->is_active);

        // 4. Safe deletion prevents removing category with active types or properties
        $deleteResponse = $this->actingAs($admin)->deleteJson("/admin/taxonomy/categories/{$category->id}");
        $deleteResponse->assertUnprocessable();
    }

    public function test_property_zone_id_and_location_hierarchy_relationships(): void
    {
        $user = $this->createUser('Zone Tester');
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        // Create a zone location
        $region = Location::query()->where('level', 'region')->firstOrFail();
        $zone = Location::query()->create([
            'parent_id' => $region->id,
            'level' => 'zone',
            'code' => 'EAST_SHEWA',
            'slug' => 'east-shewa-zone',
            'ancestry_path' => "/{$region->id}/",
            'name_en' => 'East Shewa Zone',
            'is_active' => true,
        ]);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-OROMIA-ES-RES-00001',
            'owner_id' => $user->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'region_id' => $region->id,
            'zone_id' => $zone->id,
            'title_en' => 'Bishoftu Lakeside Villa',
            'status' => PropertyStatus::Available,
        ]);

        $this->assertInstanceOf(Location::class, $property->zone);
        $this->assertSame('East Shewa Zone', $property->zone->name_en);
        $this->assertSame($zone->id, $property->zone_id);
    }

    public function test_property_model_lifecycle_automatically_records_status_transitions_without_duplicates(): void
    {
        $user = $this->createUser('Lifecycle Owner');
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        // 1. Creation automatically creates initial status history record
        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00004444',
            'owner_id' => $user->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Lifecycle Auto Tracking Villa',
            'status' => PropertyStatus::Draft,
        ]);

        $this->assertCount(1, $property->statusHistories);
        $this->assertSame(PropertyStatus::Draft, $property->statusHistories->first()->to_status);
        $this->assertNull($property->statusHistories->first()->from_status);

        // 2. Updating status via standard Eloquent update records the transition
        $property->status_change_reason = 'Verified by compliance officer';
        $property->update(['status' => PropertyStatus::Available]);

        $this->assertCount(2, $property->fresh()->statusHistories);
        $latest = $property->fresh()->statusHistories->first();
        $this->assertSame(PropertyStatus::Draft, $latest->from_status);
        $this->assertSame(PropertyStatus::Available, $latest->to_status);
        $this->assertSame('Verified by compliance officer', $latest->reason);

        // 3. Updating non-status attributes does NOT create duplicate status history
        $property->update(['title_en' => 'Updated Title Only']);
        $this->assertCount(2, $property->fresh()->statusHistories);

        // 4. Updating status to the same value does NOT create duplicate history
        $property->update(['status' => PropertyStatus::Available]);
        $this->assertCount(2, $property->fresh()->statusHistories);
    }

    public function test_listing_controller_enforces_location_hierarchy_consistency(): void
    {
        $user = $this->createUser();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $listingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        $aaCity = Location::query()->where('code', 'AA')->firstOrFail();
        $oromiaRegion = Location::query()->where('code', 'ORO')->firstOrFail();

        // Addis Ababa does NOT belong to Oromia Region
        $response = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'region_id' => $oromiaRegion->id,
            'city_id' => $aaCity->id,
            'title_en' => 'Mismatched Hierarchy Villa',
            'listing_type_id' => $listingType->id,
            'price_etb' => 8_000_000,
        ]);

        $response->assertSessionHasErrors(['city_id']);
    }

    public function test_property_creation_auto_derives_zone_id_from_location_ancestry(): void
    {
        $user = $this->createUser('Auto Zone User');
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $listingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        $region = Location::query()->where('level', 'region')->firstOrFail();
        $zone = Location::query()->create([
            'parent_id' => $region->id,
            'level' => 'zone',
            'code' => 'TEST_ZONE',
            'slug' => 'test-zone',
            'ancestry_path' => "/{$region->id}/",
            'name_en' => 'Test Zone',
            'is_active' => true,
        ]);

        $city = Location::query()->create([
            'parent_id' => $zone->id,
            'level' => 'city',
            'code' => 'TEST_CITY',
            'slug' => 'test-city',
            'ancestry_path' => "/{$region->id}/{$zone->id}/",
            'name_en' => 'Test City',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'city_id' => $city->id,
            'title_en' => 'Villa with Ancestor Zone',
            'listing_type_id' => $listingType->id,
            'price_etb' => 15_000_000,
        ]);

        $response->assertRedirect(route('dashboard'));

        $createdProperty = Property::query()->where('title_en', 'Villa with Ancestor Zone')->firstOrFail();
        $this->assertSame($zone->id, $createdProperty->zone_id);
    }

    public function test_process_property_image_job_applies_watermark_to_derivatives(): void
    {
        $user = $this->createUser();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00008888',
            'owner_id' => $user->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Watermark Test Property',
            'status' => PropertyStatus::Available,
        ]);

        // Create sample sanitized image on disk
        $dir = storage_path("app/public/properties/{$property->id}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $relPath = "properties/{$property->id}/sample.webp";
        $fullPath = storage_path("app/public/{$relPath}");

        $canvas = imagecreatetruecolor(400, 300);
        imagewebp($canvas, $fullPath);
        imagedestroy($canvas);

        $imageRecord = \App\Domains\Property\Models\PropertyImage::query()->create([
            'property_id' => $property->id,
            'disk' => 'public',
            'original_sanitized_path' => $relPath,
            'webp_medium_path' => $relPath,
            'room_tag' => 'exterior',
            'watermarked' => false,
        ]);

        $job = new \App\Jobs\ProcessPropertyImageJob($imageRecord);
        $job->handle(new ImageSanitizerAndHasherService());

        $this->assertTrue($imageRecord->fresh()->watermarked);
        $this->assertNotNull($imageRecord->fresh()->webp_thumb_path);
        $this->assertNotNull($imageRecord->fresh()->webp_medium_path);
        $this->assertNotNull($imageRecord->fresh()->webp_large_path);

        // Cleanup
        @unlink($fullPath);
        @unlink(storage_path("app/public/{$imageRecord->fresh()->webp_thumb_path}"));
        @unlink(storage_path("app/public/{$imageRecord->fresh()->webp_medium_path}"));
        @unlink(storage_path("app/public/{$imageRecord->fresh()->webp_large_path}"));
        @rmdir($dir);
    }

    public function test_admin_taxonomy_blade_view_renders_for_authorized_admin(): void
    {
        $admin = $this->createUser('Taxonomy Admin', 'super_admin');

        $response = $this->actingAs($admin)->get(route('admin.taxonomy.index'));
        $response->assertOk();
        $response->assertSee('Property Taxonomy &amp; Classification Engine', false);
        $response->assertSee('Residential');
    }

    public function test_etb_minor_unit_financial_rounding_boundaries(): void
    {
        // Standard rounding to 2 decimal places (santims)
        $this->assertSame(1234, EtbFormatter::toMinorUnits(12.34));
        $this->assertSame(1235, EtbFormatter::toMinorUnits(12.345)); // Rounds up at 0.005 boundary
        $this->assertSame(1234, EtbFormatter::toMinorUnits(12.344)); // Rounds down below 0.005
        $this->assertSame(1235, EtbFormatter::toMinorUnits(12.346));

        // fromMinorUnits round trip
        $this->assertSame(12.35, EtbFormatter::fromMinorUnits(1235));
        $this->assertSame(100.00, EtbFormatter::fromMinorUnits(10000));
        $this->assertSame(0.01, EtbFormatter::fromMinorUnits(1));
    }

    public function test_status_history_atomicity_rolls_back_history_on_database_failure(): void
    {
        $user = $this->createUser();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001111',
            'owner_id' => $user->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Atomic History Test Villa',
            'status' => PropertyStatus::Draft,
        ]);

        $this->assertCount(1, $property->statusHistories);

        // Simulate a database failure during transaction
        $action = app(RecordPropertyStatusHistoryAction::class);

        try {
            DB::transaction(function () use ($action, $property, $user) {
                $action->execute($property, PropertyStatus::Available, $user, 'Transition should roll back');
                throw new RuntimeException('Simulated database failure during property update');
            });
        } catch (RuntimeException $e) {
            // Expected simulated exception
        }

        // Status history must NOT contain the failed status update
        $fresh = $property->fresh();
        $this->assertSame(PropertyStatus::Draft, $fresh->status);
        $this->assertCount(1, $fresh->statusHistories);
        $this->assertDatabaseMissing('property_status_histories', [
            'property_id' => $property->id,
            'to_status' => PropertyStatus::Available->value,
            'reason' => 'Transition should roll back',
        ]);
    }

    public function test_query_builder_status_update_does_not_bypass_status_history(): void
    {
        $user = $this->createUser();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00002222',
            'owner_id' => $user->id,
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Query Builder History Tracking Villa',
            'status' => PropertyStatus::Available,
        ]);

        $this->assertCount(1, $property->statusHistories);

        // Update via Eloquent Query Builder directly
        $updatedRows = Property::query()
            ->where('id', $property->id)
            ->update(['status' => PropertyStatus::Sold]);

        $this->assertSame(1, $updatedRows);
        $this->assertSame(PropertyStatus::Sold, $property->fresh()->status);
        $this->assertCount(2, $property->fresh()->statusHistories);

        $latestHistory = $property->fresh()->statusHistories->first();
        $this->assertSame(PropertyStatus::Available, $latestHistory->from_status);
        $this->assertSame(PropertyStatus::Sold, $latestHistory->to_status);
    }

    public function test_listing_creation_failure_rolls_back_database_and_cleans_up_stored_files(): void
    {
        Queue::fake();

        $user = $this->createUser();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $listingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        // Put a pre-existing safe file in storage to confirm ONLY files from failed request are deleted
        $safePreExistingPath = 'properties/pre_existing_safe.webp';
        Storage::disk('public')->put($safePreExistingPath, 'safe-content');
        $this->assertTrue(Storage::disk('public')->exists($safePreExistingPath));

        // Create a dummy image for upload
        $photo = UploadedFile::fake()->image('villa_exterior.jpg', 600, 400);

        // Inject simulated database failure right when the Listing is being created
        Listing::creating(function () {
            throw new RuntimeException('Simulated database failure during listing creation');
        });

        $response = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Failure Injection Villa',
            'listing_type_id' => $listingType->id,
            'price_etb' => 25_000_000,
            'photos' => [$photo],
        ]);

        $response->assertStatus(500);

        // 1. Pre-existing file remains intact
        $this->assertTrue(Storage::disk('public')->exists($safePreExistingPath));

        // 2. Database transactions rolled back completely (no orphaned property or listing)
        $this->assertDatabaseMissing('properties', [
            'title_en' => 'Failure Injection Villa',
        ]);
        $this->assertDatabaseMissing('listings', [
            'price_etb' => 25_000_000,
        ]);

        // 3. Queued jobs were not pushed to queue because transaction rolled back
        Queue::assertNotPushed(ProcessPropertyImageJob::class);

        // Cleanup test file
        Storage::disk('public')->delete($safePreExistingPath);
    }

    public function test_admin_can_toggle_listing_types(): void
    {
        $admin = $this->createUser('Super Admin', 'super_admin');
        $stranger = $this->createUser('Regular User');
        $listingType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        $this->assertTrue($listingType->is_phase1_enabled);

        // 1. Unauthorized user cannot toggle listing type (403)
        $unauth = $this->actingAs($stranger)->postJson("/admin/taxonomy/listing-types/{$listingType->id}/toggle");
        $unauth->assertForbidden();

        // 2. Authorized admin can toggle listing type
        $auth = $this->actingAs($admin)->postJson("/admin/taxonomy/listing-types/{$listingType->id}/toggle");
        $auth->assertOk();
        $this->assertFalse($listingType->fresh()->is_phase1_enabled);

        // Toggle back to active
        $this->actingAs($admin)->postJson("/admin/taxonomy/listing-types/{$listingType->id}/toggle");
        $this->assertTrue($listingType->fresh()->is_phase1_enabled);
    }

    public function test_backend_validation_rejects_inactive_listing_type_and_inactive_amenities(): void
    {
        $user = $this->createUser();
        $resCategory = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $villaType = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $listingType = ListingType::query()->where('code', 'FOR_RENT')->firstOrFail();

        // Deactivate listing type
        $listingType->update(['is_phase1_enabled' => false]);

        // 1. Submitting disabled listing_type_id must be rejected
        $responseInactiveListingType = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Test Inactive Listing Type',
            'listing_type_id' => $listingType->id,
            'price_etb' => 50_000,
        ]);

        $responseInactiveListingType->assertSessionHasErrors(['listing_type_id']);

        // Reactivate listing type
        $listingType->update(['is_phase1_enabled' => true]);

        // Deactivate an amenity
        $amenity = PropertyAmenity::query()->firstOrFail();
        $amenity->update(['is_active' => false]);

        // 2. Submitting inactive amenity must be rejected
        $responseInactiveAmenity = $this->actingAs($user)->post(route('listings.store'), [
            'category_id' => $resCategory->id,
            'property_type_id' => $villaType->id,
            'title_en' => 'Test Inactive Amenity',
            'listing_type_id' => $listingType->id,
            'price_etb' => 50_000,
            'amenities' => [$amenity->slug],
        ]);

        $responseInactiveAmenity->assertSessionHasErrors(['amenities']);
    }
}

