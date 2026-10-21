<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Moderation\Actions\EvaluateListingFraudSignalsAction;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyImage;
use App\Domains\Property\Models\PropertyType;
use App\Domains\User\Models\User;
use App\Domains\Verification\Actions\ReviewVerificationRequestAction;
use App\Domains\Verification\Enums\VerificationLevel;
use App\Domains\Verification\Models\Verification;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class VerificationAndModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
    }

    public function test_officer_approves_verification_request_and_propagates_level_to_property(): void
    {
        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Officer Almaz',
            'phone' => '+251911001122',
            'status' => 'active',
        ]);
        $officer->assignRoleBySlug('verification_officer');

        // Grant explicit permissions to officer
        $permReviewId = DB::table('permissions')->where('slug', 'verification.review')->value('id');
        $permApproveId = DB::table('permissions')->where('slug', 'verification.approve')->value('id');
        DB::table('user_permissions')->insert([
            ['user_id' => $officer->id, 'permission_id' => $permReviewId],
            ['user_id' => $officer->id, 'permission_id' => $permApproveId],
        ]);

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Girma',
            'phone' => '+251911334455',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00002001',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Villa for Verification',
            'verification_level' => 0,
            'status' => PropertyStatus::Available,
        ]);

        $verification = Verification::query()->create([
            'uuid' => (string) Str::uuid(),
            'verifiable_type' => Property::class,
            'verifiable_id' => $property->id,
            'requested_by_user_id' => $seller->id,
            'requested_level' => 3,
            'approved_level' => 0,
            'status' => 'pending',
        ]);

        $action = new ReviewVerificationRequestAction();
        $action->execute(
            officer: $officer,
            verificationId: $verification->id,
            decision: 'approved',
            grantedLevel: VerificationLevel::DocumentsSubmitted,
            reason: 'Carta and site plan verified via sub-city archives',
            badgeCode: 'documents_submitted'
        );

        $refreshed = $verification->fresh();
        $this->assertSame('approved', $refreshed->status);
        $this->assertSame(VerificationLevel::DocumentsSubmitted, $refreshed->approved_level);
        $this->assertSame('documents_submitted', $refreshed->badge_code);
        $this->assertSame($officer->id, $refreshed->reviewed_by_user_id);

        // Verification review history recorded
        $this->assertDatabaseHas('verification_reviews', [
            'verification_id' => $verification->id,
            'officer_id' => $officer->id,
            'from_status' => 'pending',
            'to_status' => 'approved',
            'granted_level' => 3,
        ]);

        // Propagated to property
        $this->assertSame(3, (int) $property->fresh()->verification_level);
    }

    public function test_unauthorized_user_cannot_review_verification_request(): void
    {
        $regularUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular User',
            'phone' => '+251911999999',
            'status' => 'active',
        ]);

        $verification = Verification::query()->create([
            'uuid' => (string) Str::uuid(),
            'verifiable_type' => Property::class,
            'verifiable_id' => 1,
            'requested_by_user_id' => $regularUser->id,
            'requested_level' => 2,
            'approved_level' => 0,
            'status' => 'pending',
        ]);

        $action = new ReviewVerificationRequestAction();

        $this->expectException(ValidationException::class);
        $action->execute(
            officer: $regularUser,
            verificationId: $verification->id,
            decision: 'approved',
            grantedLevel: VerificationLevel::SellerOrCompanyVerified,
            reason: 'Unauthorized bypass attempt'
        );
    }

    public function test_detects_duplicate_image_hash_fraud_signal(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller One',
            'phone' => '+251911556677',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        // 1. Existing Property with hash
        $prop1 = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00002002',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Original Villa',
            'status' => PropertyStatus::Available,
        ]);

        PropertyImage::query()->create([
            'property_id' => $prop1->id,
            'disk' => 'public',
            'original_sanitized_path' => 'properties/1/photo1.webp',
            'room_tag' => 'exterior',
            'perceptual_hash' => 'a1b2c3d4e5f60718',
            'exif_stripped' => true,
        ]);

        // 2. New suspicious listing copying the same photo hash (1 bit diff)
        $prop2 = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00002003',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Duplicate Villa Post',
            'status' => PropertyStatus::Available,
        ]);

        PropertyImage::query()->create([
            'property_id' => $prop2->id,
            'disk' => 'public',
            'original_sanitized_path' => 'properties/2/photo1.webp',
            'room_tag' => 'exterior',
            'perceptual_hash' => 'a1b2c3d4e5f60719', // Hamming distance 1
            'exif_stripped' => true,
        ]);

        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-00000099',
            'slug' => 'duplicate-villa-post-99',
            'property_id' => $prop2->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 19_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
        ]);

        $detector = new EvaluateListingFraudSignalsAction();
        $signals = $detector->execute($listing, $prop2);

        $this->assertNotEmpty($signals);
        $this->assertSame('image_hash_match', $signals[0]['signal_type']);
        $this->assertSame('critical', $signals[0]['severity']);
        $this->assertSame($prop1->id, $signals[0]['matched_property_id']);

        $this->assertDatabaseHas('fraud_signals', [
            'listing_id' => $listing->id,
            'property_id' => $prop2->id,
            'signal_type' => 'image_hash_match',
            'status' => 'open',
        ]);
    }

    public function test_marketplace_search_and_property_detail_pages_load(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Birhanu Nega',
            'phone' => '+251911889900',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00002004',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Grand Bole Ambassador Villa',
            'land_area_sqm' => 450,
            'building_area_sqm' => 380,
            'bedrooms' => 5,
            'bathrooms' => 4,
            'verification_level' => 3,
            'latitude' => 8.9950,
            'longitude' => 38.7880,
            'status' => PropertyStatus::Available,
        ]);

        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-00000100',
            'slug' => 'grand-bole-ambassador-villa-100',
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 45_000_000,
            'currency' => 'ETB',
            'price_per_sqm_etb' => 100000.00,
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);

        // 1. Search page with natural query
        $searchResponse = $this->get('/search?q=5-bedroom+villa+in+Bole');
        $searchResponse->assertOk()
            ->assertSee('Grand Bole Ambassador Villa')
            ->assertSee('ETB 45,000,000')
            ->assertSee('ETH-AA-BOLE-RES-00002004');

        // 2. Property detail page
        $detailResponse = $this->get("/listings/{$listing->slug}");
        $detailResponse->assertOk()
            ->assertSee('Grand Bole Ambassador Villa')
            ->assertSee('ETB 45,000,000')
            ->assertSee('Proclamation No. 1357/2024')
            ->assertSee('Documents Submitted (Level 3)')
            ->assertSee('450 m²');
    }
}
