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
            ->assertSee('ERE verification is a platform verification process')
            ->assertSee('not official government certification of land ownership')
            ->assertSee('Documents Submitted (Level 3)')
            ->assertSee('450 m²');
    }

    public function test_fraud_signals_repeated_execution_is_idempotent_and_updates_open_signal(): void
    {
        $owner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Dupont',
            'phone' => '+251911999001',
            'status' => 'active',
        ]);
        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $propertyA = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Duplicate Test Property A',
            'property_code' => 'ETH-TEST-A-01',
            'land_area_sqm' => 500,
            'latitude' => 9.0200,
            'longitude' => 38.7500,
            'status' => PropertyStatus::Available,
            'verification_level' => 0,
        ]);
        $propertyB = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Duplicate Test Property B',
            'property_code' => 'ETH-TEST-B-02',
            'land_area_sqm' => 510,
            'latitude' => 9.0201, // ~15m away
            'longitude' => 38.7501,
            'status' => PropertyStatus::Available,
            'verification_level' => 0,
        ]);

        $saleTypeId = (int) (DB::table('listing_types')->value('id') ?? 1);

        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-TEST-001',
            'listing_type_id' => $saleTypeId,
            'property_id' => $propertyA->id,
            'published_by_user_id' => $owner->id,
            'title' => 'Listing A',
            'slug' => 'listing-a-dup-test',
            'transaction_mode' => 'BUY',
            'price_etb' => 20_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::PendingReview->value,
        ]);

        $evaluator = new EvaluateListingFraudSignalsAction();

        // 1. First execution
        $signals1 = $evaluator->execute($listing, $propertyA);
        $this->assertNotEmpty($signals1);
        $signalCount1 = DB::table('fraud_signals')->where('listing_id', $listing->id)->count();
        $this->assertSame(1, $signalCount1);

        // 2. Immediate second sequential execution
        $signals2 = $evaluator->execute($listing, $propertyA);
        $signalCount2 = DB::table('fraud_signals')->where('listing_id', $listing->id)->count();
        $this->assertSame(1, $signalCount2, 'Repeated execution must not duplicate active fraud signals.');
    }

    public function test_fraud_signal_recurrence_is_permitted_after_prior_signal_is_resolved(): void
    {
        $owner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Recurrence',
            'phone' => '+251911999002',
            'status' => 'active',
        ]);
        $moderator = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Moderator Robel',
            'phone' => '+251911999003',
            'status' => 'active',
        ]);
        $moderator->assignRoleBySlug('moderator');

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $saleTypeId = (int) (DB::table('listing_types')->value('id') ?? 1);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Recurrence Test Property',
            'property_code' => 'ETH-TEST-REC-01',
            'status' => PropertyStatus::Available,
            'verification_level' => 0,
        ]);
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-TEST-002',
            'listing_type_id' => $saleTypeId,
            'property_id' => $property->id,
            'published_by_user_id' => $owner->id,
            'title' => 'Recurrence Test Listing',
            'slug' => 'listing-rec-test',
            'transaction_mode' => 'BUY',
            'price_etb' => 100_000,
            'currency' => 'ETB',
            'status' => ListingStatus::PendingReview->value,
        ]);

        // Manually insert an initial open signal
        $signalId = DB::table('fraud_signals')->insertGetId([
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'signal_type' => 'price_anomaly',
            'severity' => 'high',
            'matched_property_id' => null,
            'confidence_score' => 80,
            'evidence_json' => json_encode(['test' => 1]),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Moderator resolves it as dismissed
        $moderateAction = new \App\Domains\Moderation\Actions\ModerateListingAction();
        $signalModel = \App\Domains\Moderation\Models\FraudSignal::query()->findOrFail($signalId);
        $moderateAction->resolveFraudSignal($moderator, $signalModel, 'dismissed', 'Verified with owner directly');

        $this->assertSame('dismissed', $signalModel->fresh()->status);

        // Subsequent scan occurs: creating a new open signal must succeed without violating unique constraint
        $newSignalId = DB::table('fraud_signals')->insertGetId([
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'signal_type' => 'price_anomaly',
            'severity' => 'critical',
            'matched_property_id' => null,
            'confidence_score' => 95,
            'evidence_json' => json_encode(['test' => 2]),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotSame($signalId, $newSignalId);
        $allSignals = DB::table('fraud_signals')->where('listing_id', $listing->id)->get();
        $this->assertCount(2, $allSignals);
        $this->assertSame(1, $allSignals->where('status', 'dismissed')->count());
        $this->assertSame(1, $allSignals->where('status', 'open')->count());
    }

    public function test_fraud_signals_null_matched_property_unique_constraint_is_enforced(): void
    {
        $owner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Null Match',
            'phone' => '+251911999004',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $saleTypeId = (int) (DB::table('listing_types')->value('id') ?? 1);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Null Match Constraint Test',
            'property_code' => 'ETH-TEST-NULL-01',
            'status' => PropertyStatus::Available,
        ]);
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-TEST-003',
            'listing_type_id' => $saleTypeId,
            'property_id' => $property->id,
            'published_by_user_id' => $owner->id,
            'title' => 'Null Match Listing',
            'slug' => 'listing-null-match-test',
            'transaction_mode' => 'BUY',
            'price_etb' => 5_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::PendingReview->value,
        ]);

        // First insert with null matched_property_id
        DB::table('fraud_signals')->insert([
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'signal_type' => 'price_anomaly',
            'severity' => 'high',
            'matched_property_id' => null,
            'confidence_score' => 75,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Direct concurrent second insert with null matched_property_id must throw QueryException
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('fraud_signals')->insert([
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'signal_type' => 'price_anomaly',
            'severity' => 'critical',
            'matched_property_id' => null,
            'confidence_score' => 90,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_fraud_signals_concurrent_worker_race_condition_is_reconciled(): void
    {
        $owner = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Race',
            'phone' => '+251911999005',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $saleTypeId = (int) (DB::table('listing_types')->value('id') ?? 1);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Race Condition Test Property',
            'property_code' => 'ETH-TEST-RACE-01',
            'status' => PropertyStatus::Available,
        ]);
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-TEST-004',
            'listing_type_id' => $saleTypeId,
            'property_id' => $property->id,
            'published_by_user_id' => $owner->id,
            'title' => 'Race Condition Listing',
            'slug' => 'listing-race-test',
            'transaction_mode' => 'BUY',
            'price_etb' => 12_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::PendingReview->value,
        ]);

        $evaluator = new EvaluateListingFraudSignalsAction();
        $refMethod = new \ReflectionMethod($evaluator, 'recordSignal');

        // Worker A inserts first open signal
        $signalA = $refMethod->invoke(
            $evaluator,
            $listing->id,
            $property->id,
            'price_anomaly',
            'medium',
            null,
            60,
            ['worker' => 'WorkerA', 'detail' => 'initial scan']
        );

        $this->assertSame('open', $signalA['status']);

        // Worker B simulates concurrent execution: tries to record the same signal identity with new evidence
        $signalB = $refMethod->invoke(
            $evaluator,
            $listing->id,
            $property->id,
            'price_anomaly',
            'high',
            null,
            85,
            ['worker' => 'WorkerB', 'detail' => 'concurrent scan']
        );

        // Same ID returned, evidence merged, no duplicates created
        $this->assertSame($signalA['id'], $signalB['id']);
        $this->assertSame(1, DB::table('fraud_signals')->where('listing_id', $listing->id)->count());
        $this->assertSame(85, (int) $signalB['confidence_score']);
        $this->assertArrayHasKey('worker', $signalB['evidence']);
    }

    public function test_moderator_can_view_listing_moderation_workbench_index_and_detail(): void
    {
        $admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Moderator Admin',
            'phone' => '+251911999999',
            'status' => 'active',
        ]);
        $admin->assignRoleBySlug('super_admin');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Abebe',
            'phone' => '+251911888888',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();
        $saleTypeId = (int) (DB::table('listing_types')->value('id') ?? 1);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Workbench Test Property',
            'property_code' => 'ETH-TEST-WB-01',
            'status' => PropertyStatus::Available,
        ]);

        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-TEST-WB-01',
            'listing_type_id' => $saleTypeId,
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'slug' => 'listing-wb-test',
            'transaction_mode' => 'BUY',
            'price_etb' => 7_500_000,
            'currency' => 'ETB',
            'status' => ListingStatus::PendingReview->value,
        ]);

        DB::table('fraud_signals')->insert([
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'signal_type' => 'price_anomaly',
            'severity' => 'high',
            'matched_property_id' => null,
            'confidence_score' => 75,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Visit index route: verifies ->withCount(['fraudSignals as open_signals_count'])
        $response = $this->actingAs($admin)->get(route('admin.moderation.index'));
        $response->assertOk();
        $response->assertSee('Workbench Test Property');
        $response->assertSee('1 signal(s)');

        // 2. Visit detail route: verifies $listing->load(['fraudSignals.matchedProperty', 'moderationActions.moderator'])
        $detailResponse = $this->actingAs($admin)->get(route('admin.moderation.show', $listing));
        $detailResponse->assertOk();
        $detailResponse->assertSee('Workbench Test Property');
        $detailResponse->assertSee('price anomaly');
    }
}
