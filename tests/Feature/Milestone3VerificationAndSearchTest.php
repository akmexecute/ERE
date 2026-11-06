<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentVersion;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Location\Models\Location;
use App\Domains\Marketplace\Actions\ParseNaturalPropertyQueryAction;
use App\Domains\Marketplace\Queries\SearchMarketplaceListingsQuery;
use App\Domains\Moderation\Actions\EvaluateListingFraudSignalsAction;
use App\Domains\Moderation\Actions\ModerateListingAction;
use App\Domains\Moderation\Actions\ReportPropertyAction;
use App\Domains\Moderation\Models\FraudSignal;
use App\Domains\Moderation\Models\PropertyReport;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyImage;
use App\Domains\Property\Models\PropertyType;
use App\Domains\User\Models\User;
use App\Domains\Verification\Actions\RequestVerificationAction;
use App\Domains\Verification\Actions\ReviewVerificationRequestAction;
use App\Domains\Verification\Enums\VerificationLevel;
use App\Domains\Verification\Models\Verification;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class Milestone3VerificationAndSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
    }

    public function test_submit_verification_request_with_authorized_target_and_documents(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Abebe',
            'phone' => '+251911123456',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00003001',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Test Villa For Verification',
            'status' => PropertyStatus::Available,
        ]);

        $document = Document::query()->create([
            'uuid' => (string) Str::uuid(),
            'documentable_type' => Property::class,
            'documentable_id' => $property->id,
            'uploaded_by' => $seller->id,
            'document_type_code' => 'carta',
            'title' => 'Title Deed Carta',
            'access_level' => \App\Domains\Document\Enums\DocumentAccessLevel::Private,
        ]);

        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'storage_disk' => 'local',
            'storage_path' => 'documents/test.pdf',
            'original_filename' => 'carta.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'sha256_checksum' => hash('sha256', 'dummy'),
            'uploaded_by' => $seller->id,
        ]);

        $action = new RequestVerificationAction();
        $verification = $action->execute(
            requester: $seller,
            target: $property,
            requestedLevel: 3,
            documentVersionIds: [$version->id]
        );

        $this->assertSame('pending', $verification->status);
        $this->assertSame(3, $verification->requested_level);
        $this->assertSame($seller->id, $verification->requested_by_user_id);
        $this->assertDatabaseHas('verification_documents', [
            'verification_id' => $verification->id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
        ]);
    }

    public function test_review_verification_rejects_unsupported_target_model(): void
    {
        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Officer Helen',
            'phone' => '+251911987654',
            'status' => 'active',
        ]);
        $officer->assignRoleBySlug('verification_officer');

        $action = new ReviewVerificationRequestAction();

        // Pass invalid model target
        $fakeVerification = Verification::query()->create([
            'uuid' => (string) Str::uuid(),
            'verifiable_type' => Location::class, // Location is NOT an allowed target
            'verifiable_id' => 1,
            'requested_by_user_id' => $officer->id,
            'requested_level' => 1,
            'approved_level' => 0,
            'status' => 'pending',
        ]);

        $this->expectException(ValidationException::class);
        $action->execute(
            officer: $officer,
            verificationId: $fakeVerification->id,
            decision: 'approved',
            grantedLevel: VerificationLevel::IdentityVerified
        );
    }

    public function test_review_revocation_recalculates_authoritative_verification_level(): void
    {
        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Senior Officer',
            'phone' => '+251911223344',
            'status' => 'active',
        ]);
        $officer->assignRoleBySlug('verification_officer');

        // Grant explicit permissions
        $permReviewId = DB::table('permissions')->where('slug', 'verification.review')->value('id');
        $permApproveId = DB::table('permissions')->where('slug', 'verification.approve')->value('id');
        DB::table('user_permissions')->insert([
            ['user_id' => $officer->id, 'permission_id' => $permReviewId],
            ['user_id' => $officer->id, 'permission_id' => $permApproveId],
        ]);

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Kebede',
            'phone' => '+251911554433',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00003002',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Villa with Approved Verification',
            'verification_level' => 3,
            'status' => PropertyStatus::Available,
        ]);

        // Existing approved verification
        $verification = Verification::query()->create([
            'uuid' => (string) Str::uuid(),
            'verifiable_type' => Property::class,
            'verifiable_id' => $property->id,
            'requested_by_user_id' => $seller->id,
            'requested_level' => 3,
            'approved_level' => 3,
            'status' => 'approved',
        ]);

        // Revoke / reject the verification
        $action = new ReviewVerificationRequestAction();
        $action->execute(
            officer: $officer,
            verificationId: $verification->id,
            decision: 'rejected',
            reason: 'Forged title document discovered during forensic audit'
        );

        $this->assertSame('rejected', $verification->fresh()->status);
        // Authoritative level dropped back to 0 because no active approved requests remain
        $this->assertSame(0, (int) $property->fresh()->verification_level);
    }

    public function test_evaluates_price_anomaly_using_true_median_and_minimum_comparables(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Dawit',
            'phone' => '+251911776655',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        // City location for Addis Ababa
        $city = Location::query()->where('level', 'city')->first() ?? Location::query()->create([
            'level' => 'city',
            'code' => 'AA',
            'slug' => 'addis-ababa-test',
            'name_en' => 'Addis Ababa',
            'is_active' => true,
        ]);

        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        // Create 3 comparable listings in same city/property_type to establish true median
        // Prices per sqm: 50,000, 60,000, 70,000 -> Median = 60,000
        foreach ([50000, 60000, 70000] as $idx => $sqmPrice) {
            $p = Property::query()->create([
                'uuid' => (string) Str::uuid(),
                'property_code' => "ETH-AA-COMP-0000{$idx}",
                'owner_id' => $seller->id,
                'category_id' => $category->id,
                'property_type_id' => $type->id,
                'city_id' => $city->id,
                'title_en' => "Comparable Villa {$idx}",
                'land_area_sqm' => 200,
                'status' => PropertyStatus::Available,
            ]);

            Listing::query()->create([
                'uuid' => (string) Str::uuid(),
                'listing_code' => "LST-COMP-00{$idx}",
                'slug' => "comparable-villa-{$idx}",
                'property_id' => $p->id,
                'published_by_user_id' => $seller->id,
                'listing_type_id' => $saleType->id,
                'transaction_mode' => 'BUY',
                'price_etb' => $sqmPrice * 200,
                'price_per_sqm_etb' => $sqmPrice,
                'currency' => 'ETB',
                'status' => ListingStatus::Published->value,
            ]);
        }

        // New listing with suspiciously low price (10,000/sqm which is 16.6% of median 60,000 < 25%)
        $susProp = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-SUS-00009999',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'city_id' => $city->id,
            'title_en' => 'Suspicious Cheap Villa',
            'land_area_sqm' => 200,
            'status' => PropertyStatus::Available,
        ]);

        $susListing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-SUS-0099',
            'slug' => 'suspicious-cheap-villa-99',
            'property_id' => $susProp->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 2_000_000,
            'price_per_sqm_etb' => 10000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
        ]);

        $action = new EvaluateListingFraudSignalsAction();
        $signals = $action->execute($susListing, $susProp);

        $priceSignal = collect($signals)->firstWhere('signal_type', 'price_anomaly');
        $this->assertNotNull($priceSignal);
        $this->assertSame('high', $priceSignal['severity']);
        $this->assertEquals(60000, $priceSignal['evidence']['local_median_price_per_sqm']);
        $this->assertSame(3, $priceSignal['evidence']['sample_size']);
    }

    public function test_fraud_signal_idempotency_prevents_duplicate_open_signals(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller One',
            'phone' => '+251911991122',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $prop1 = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00004001',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Original Villa',
            'status' => PropertyStatus::Available,
        ]);

        PropertyImage::query()->create([
            'property_id' => $prop1->id,
            'disk' => 'public',
            'original_sanitized_path' => 'properties/photo1.webp',
            'room_tag' => 'exterior',
            'perceptual_hash' => '1122334455667788',
            'exif_stripped' => true,
        ]);

        $prop2 = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00004002',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Duplicate Villa',
            'status' => PropertyStatus::Available,
        ]);

        PropertyImage::query()->create([
            'property_id' => $prop2->id,
            'disk' => 'public',
            'original_sanitized_path' => 'properties/photo2.webp',
            'room_tag' => 'exterior',
            'perceptual_hash' => '1122334455667789', // Hamming distance 1
            'exif_stripped' => true,
        ]);

        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-IDEM-001',
            'slug' => 'duplicate-villa-idem',
            'property_id' => $prop2->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 20_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
        ]);

        $evaluator = new EvaluateListingFraudSignalsAction();

        // 1st Evaluation
        $evaluator->execute($listing, $prop2);
        $countAfterFirst = FraudSignal::query()->where('listing_id', $listing->id)->count();

        // 2nd Evaluation
        $evaluator->execute($listing, $prop2);
        $countAfterSecond = FraudSignal::query()->where('listing_id', $listing->id)->count();

        // Must remain exactly 1 open signal (idempotent upsert)
        $this->assertSame(1, $countAfterFirst);
        $this->assertSame(1, $countAfterSecond);
    }

    public function test_moderator_can_suspend_request_corrections_and_resolve_fraud_signal(): void
    {
        $moderator = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Moderator Solomon',
            'phone' => '+251911445566',
            'status' => 'active',
        ]);
        $moderator->assignRoleBySlug('moderator');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Test',
            'phone' => '+251911778899',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00004003',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Test Mod Property',
            'status' => PropertyStatus::Available,
        ]);

        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-MOD-001',
            'slug' => 'test-mod-listing',
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 25_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::PendingReview->value,
        ]);

        $action = new ModerateListingAction();

        // 1. Request corrections
        $action->execute(
            moderator: $moderator,
            listing: $listing,
            decision: 'request_correction',
            feedbackToSeller: 'Please provide clear photos of the compound and road access.'
        );

        $this->assertSame(ListingStatus::Draft->value, $listing->fresh()->status->value);
        $this->assertDatabaseHas('moderation_actions', [
            'listing_id' => $listing->id,
            'action_type' => 'request_correction',
            'to_status' => ListingStatus::Draft->value,
        ]);

        // 2. Suspend listing
        $action->execute(
            moderator: $moderator,
            listing: $listing,
            decision: 'suspend',
            reasonCode: 'ownership_dispute',
            feedbackToSeller: 'Suspended pending resolution of co-ownership dispute.'
        );

        $this->assertSame(ListingStatus::Suspended->value, $listing->fresh()->status->value);

        // 3. Resolve fraud signal
        $signal = FraudSignal::query()->create([
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'signal_type' => 'text_duplicate',
            'severity' => 'low',
            'confidence_score' => 40,
            'status' => 'open',
        ]);

        $action->resolveFraudSignal(
            moderator: $moderator,
            signal: $signal,
            resolution: 'dismissed',
            notes: 'Standard broker disclaimer template used, not fraudulent copy.'
        );

        $refreshedSignal = $signal->fresh();
        $this->assertSame('dismissed', $refreshedSignal->status);
        $this->assertSame($moderator->id, $refreshedSignal->resolved_by);
        $this->assertStringContainsString('Standard broker disclaimer', $refreshedSignal->evidence_json['resolution_notes']);
    }

    public function test_submit_public_property_report_with_validation_and_rate_limiting(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Test',
            'phone' => '+251911000111',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00004004',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Reportable Property',
            'status' => PropertyStatus::Available,
        ]);

        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-REP-001',
            'slug' => 'reportable-property-1',
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 20_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
        ]);

        $action = new ReportPropertyAction();

        // 1. Valid submission
        $report = $action->execute(
            listing: $listing,
            property: $property,
            reasonCode: 'fake_property',
            description: 'This villa was demolished two months ago.',
            ipAddress: '196.189.1.50'
        );

        $this->assertInstanceOf(PropertyReport::class, $report);
        $this->assertSame('fake_property', $report->reason_code);
        $this->assertSame('open', $report->status);

        // 2. Invalid reason code is rejected
        $this->expectException(ValidationException::class);
        $action->execute(
            listing: $listing,
            property: $property,
            reasonCode: 'arbitrary_fake_reason',
            ipAddress: '196.189.1.51'
        );
    }

    public function test_natural_language_parser_extracts_min_price_and_amharic_terms(): void
    {
        $parser = new ParseNaturalPropertyQueryAction();

        // 1. Min price "above 10m"
        $parsed1 = $parser->execute('Apartment in Bole above 10m');
        $this->assertSame('apartment', $parsed1['property_type_slug']);
        $this->assertSame('bole', $parsed1['location_keyword']);
        $this->assertSame(10_000_000.0, $parsed1['min_price_etb']);

        // 2. Commercial office for rent with generator
        $parsed2 = $parser->execute('Commercial office for rent in Kazanchis under 150k with generator');
        $this->assertSame('office', $parsed2['property_type_slug']);
        $this->assertSame('kazanchis', $parsed2['location_keyword']);
        $this->assertSame('RENT', $parsed2['transaction_mode']);
        $this->assertSame(150_000.0, $parsed2['max_price_etb']);
        $this->assertContains('backup-generator', $parsed2['amenities']);

        // 3. Amharic search terms: "ቪላ ለሽያጭ በቦሌ"
        $parsedAm = $parser->execute('ቪላ ለሽያጭ በቦሌ');
        $this->assertSame('villa', $parsedAm['property_type_slug']);
        $this->assertSame('BUY', $parsedAm['transaction_mode']);
        $this->assertSame('bole', $parsedAm['location_keyword']);
    }

    public function test_search_marketplace_query_applies_verified_only_and_location_filters(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller One',
            'phone' => '+251911009988',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        // Property 1: Unverified (level 0)
        $propUnverified = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00005001',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Unverified Villa',
            'verification_level' => 0,
            'status' => PropertyStatus::Available,
        ]);

        // Property 2: Verified (level 3)
        $propVerified = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00005002',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Verified Villa',
            'verification_level' => 3,
            'status' => PropertyStatus::Available,
        ]);

        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();

        $listing1 = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-VER-001',
            'slug' => 'unverified-villa',
            'property_id' => $propUnverified->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 15_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);

        $listing2 = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-VER-002',
            'slug' => 'verified-villa',
            'property_id' => $propVerified->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 25_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);

        $query = new SearchMarketplaceListingsQuery();

        // All listings
        $all = $query->execute([]);
        $this->assertSame(2, $all->total());

        // Verified only (min_verification_level >= 1)
        $verifiedOnly = $query->execute(['min_verification_level' => 1]);
        $this->assertSame(1, $verifiedOnly->total());
        $this->assertSame('verified-villa', $verifiedOnly->items()[0]->slug);
    }

    public function test_property_detail_view_renders_seo_json_ld_and_proclamation_disclaimer(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Almaz Hailu',
            'phone' => '+251911443322',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00006001',
            'owner_id' => $seller->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Luxury Bole Medhanialem Villa',
            'description_en' => 'Stunning 4-bedroom villa with backup generator and security.',
            'land_area_sqm' => 400,
            'bedrooms' => 4,
            'bathrooms' => 3,
            'verification_level' => 4,
            'latitude' => 8.9980,
            'longitude' => 38.7890,
            'status' => PropertyStatus::Available,
        ]);

        $saleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-SEO-001',
            'slug' => 'luxury-bole-medhanialem-villa-01',
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => $saleType->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 35_000_000,
            'currency' => 'ETB',
            'price_per_sqm_etb' => 87500.00,
            'status' => ListingStatus::Published->value,
            'published_at' => now(),
        ]);

        $response = $this->get("/listings/{$listing->slug}");

        $response->assertOk()
            ->assertSee('Luxury Bole Medhanialem Villa')
            ->assertSee('ERE verification is a platform verification process')
            ->assertSee('not official government certification of land ownership')
            ->assertSee('Professionally Reviewed (Level 4)')
            ->assertSee('schema.org', false)
            ->assertSee('RealEstateListing', false)
            ->assertSee('canonical', false)
            ->assertSee('og:title', false)
            ->assertSee('Report this Listing');
    }
}
