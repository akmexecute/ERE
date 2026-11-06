<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Identity\Contracts\FaydaProviderInterface;
use App\Domains\Identity\Exceptions\FaydaServiceUnavailableException;
use App\Domains\Identity\Exceptions\FaydaVerificationRejectedException;
use App\Domains\Identity\Services\FaydaVerificationService;
use App\Domains\Identity\Services\OfficialFaydaApiProvider;
use App\Domains\Identity\Services\SellerIdentitySnapshotService;
use App\Domains\Offer\Actions\RecordNegotiationTurnAction;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Property\Actions\VerifyPropertyOwnershipAndAuthorityAction;
use App\Domains\Property\Models\Property;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Domains\Verification\Actions\ReviewVerificationRequestAction;
use App\Domains\Verification\Enums\VerificationLevel;
use App\Domains\Verification\Models\Verification;
use App\Models\Company;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SellerAccountabilityAndDualFaydaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        Storage::fake('private');
    }

    public function test_fayda_service_reports_unconnected_when_unconfigured(): void
    {
        config(['services.fayda.enabled' => false]);
        config(['services.fayda.endpoint' => null]);

        $service = app(FaydaVerificationService::class);
        $status = $service->getProviderStatus();

        $this->assertFalse($status['is_connected']);
        $this->assertSame('manual_review', $status['active_mode']);
        $this->assertTrue($status['manual_review_operational']);
        $this->assertStringContainsString('Official Fayda API is not connected', $status['status_notice']);
    }

    public function test_automated_api_verification_throws_when_fayda_unconfigured(): void
    {
        config(['services.fayda.enabled' => false]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Almaz Kebede',
            'phone' => '+251911998877',
            'status' => 'active',
        ]);

        $service = app(FaydaVerificationService::class);

        $this->expectException(FaydaServiceUnavailableException::class);
        $this->expectExceptionMessage('Official Fayda API is not connected');

        $service->verifyViaOfficialApi($user, 'FAN-9900-1122-3344');
    }

    public function test_controlled_fayda_provider_handling_and_official_badge(): void
    {
        $mockProvider = new class implements FaydaProviderInterface {
            public function isConfigured(): bool
            {
                return true;
            }

            public function getProviderName(): string
            {
                return 'Mock Fayda Test Adapter';
            }

            public function verifyNationalId(string $nationalId, array $attributes = []): array
            {
                if ($nationalId === 'FAN-INVALID-0000') {
                    throw new FaydaVerificationRejectedException('Identity not found in national registry.', 'NOT_FOUND');
                }

                return [
                    'success' => true,
                    'reference' => 'TEST-FAYDA-TX-99018',
                    'verified_name' => 'Tewodros Kassahun',
                    'masked_id' => 'FAN-****-3344',
                    'timestamp' => now()->toIso8601String(),
                    'provider' => 'Mock Fayda Test Adapter',
                ];
            }
        };

        $service = new FaydaVerificationService(provider: $mockProvider);
        $status = $service->getProviderStatus();
        $this->assertTrue($status['is_connected']);
        $this->assertSame('official_fayda_api', $status['active_mode']);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tewodros Kassahun',
            'phone' => '+251911883344',
            'status' => 'active',
        ]);

        $verification = $service->verifyViaOfficialApi($user, 'FAN-1122-3344-5566');

        $this->assertSame('official_fayda_api', $verification->verification_method);
        $this->assertSame('TEST-FAYDA-TX-99018', $verification->provider_reference);
        $this->assertSame('approved', $verification->status);
        $this->assertSame('Officially verified through Fayda', FaydaVerificationService::formatVerificationBadge('official_fayda_api'));
        $this->assertSame(1, $user->fresh()->verification_level);

        // Test Provider Rejection
        $this->expectException(FaydaVerificationRejectedException::class);
        $service->verifyViaOfficialApi($user, 'FAN-INVALID-0000');
    }

    public function test_manual_identity_verification_and_self_approval_block(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dawit Tsige',
            'phone' => '+251911776655',
            'status' => 'active',
        ]);
        $seller->assignRole('verification_officer'); // Seller also has officer role (e.g. rogue staff member)

        $front = UploadedFile::fake()->create('id_front.jpg', 500, 'image/jpeg');
        $back = UploadedFile::fake()->create('id_back.jpg', 500, 'image/jpeg');

        $service = app(FaydaVerificationService::class);
        $verification = $service->submitForManualReview($seller, 'FAN-7766-5544-3322', 'fayda', $front, $back);

        $this->assertSame('manual_review', $verification->verification_method);
        $this->assertSame('pending', $verification->status);

        // 1. Seller cannot self-approve their own verification
        $reviewAction = app(ReviewVerificationRequestAction::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Self-approval of identity or seller verification is strictly forbidden');

        $reviewAction->execute(
            officer: $seller,
            verificationId: $verification->id,
            decision: 'approved',
            grantedLevel: VerificationLevel::SellerOrCompanyVerified
        );
    }

    public function test_independent_officer_approves_manual_identity_verification(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sara Haile',
            'phone' => '+251911554433',
            'status' => 'active',
        ]);

        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Independent Officer Yohannes',
            'phone' => '+251911002233',
            'status' => 'active',
        ]);
        $officer->assignRole('verification_officer');

        $front = UploadedFile::fake()->create('id_front.jpg', 500, 'image/jpeg');
        $service = app(FaydaVerificationService::class);
        $verification = $service->submitForManualReview($seller, 'FAN-5544-3322-1100', 'fayda', $front);

        $reviewAction = app(ReviewVerificationRequestAction::class);
        $reviewAction->execute(
            officer: $officer,
            verificationId: $verification->id,
            decision: 'approved',
            grantedLevel: VerificationLevel::SellerOrCompanyVerified,
            reason: 'National ID document verified against municipal record'
        );

        $freshVer = $verification->fresh();
        $this->assertSame('approved', $freshVer->status);
        $this->assertSame('manual_review', $freshVer->verification_method);
        $this->assertSame($officer->id, $freshVer->reviewed_by_user_id);
        $this->assertSame('Internally verified by ERE', FaydaVerificationService::formatVerificationBadge($freshVer->verification_method));
        $this->assertGreaterThanOrEqual(1, $seller->fresh()->verification_level);
    }

    public function test_property_ownership_verification_is_separated_from_identity(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Girma',
            'phone' => '+251911112233',
            'verification_level' => 1, // Identity is verified
            'status' => 'active',
        ]);

        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Compliance Reviewer',
            'phone' => '+251911990011',
            'status' => 'active',
        ]);
        $officer->assignRole('verification_officer');

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-AA-KIRKOS-00192',
            'owner_id' => $seller->id,
            'category_id' => 1,
            'property_type_id' => 1,
            'title_en' => 'Kirkos Commercial Villa',
            'status' => \App\Domains\Property\Enums\PropertyStatus::Available,
            'verification_level' => 0,
            'ownership_status' => 'unverified',
        ]);

        // Identity verification does not mean property ownership is verified
        $this->assertSame('unverified', $property->ownership_status);

        // Self-approval of ownership blocked
        $ownershipAction = app(VerifyPropertyOwnershipAndAuthorityAction::class);
        try {
            $ownershipAction->execute(
                officer: $seller,
                property: $property,
                decision: 'approved',
                evidenceType: 'title_deed',
                authorityScope: 'direct_owner'
            );
            $this->fail('Expected self-approval of property ownership to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Self-approval of property ownership', $e->getMessage());
        }

        // Independent officer approves ownership documentation
        $verifiedProperty = $ownershipAction->execute(
            officer: $officer,
            property: $property,
            decision: 'approved',
            evidenceType: 'title_deed',
            authorityScope: 'direct_owner',
            notes: 'Title deed certificate #TD-AA-2021-99 verified with Kirkos subcity land bureau'
        );

        $this->assertSame('authority_verified', $verifiedProperty->ownership_status);
        $this->assertSame('title_deed', $verifiedProperty->ownership_evidence_type);
        $this->assertSame($officer->id, $verifiedProperty->ownership_verified_by_user_id);
        $this->assertStringContainsString('Internal platform document review only', $verifiedProperty->ownership_legal_disclaimer);
    }

    public function test_unverified_seller_or_ownership_cannot_accept_property_offer(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unverified Seller Solomon',
            'phone' => '+251911443322',
            'verification_level' => 0, // Unverified identity
            'status' => 'active',
        ]);

        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Buyer Helen',
            'phone' => '+251911778899',
            'status' => 'active',
        ]);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-AA-YEKA-00109',
            'owner_id' => $seller->id,
            'category_id' => 1,
            'property_type_id' => 1,
            'title_en' => 'Yeka Heights Villa',
            'status' => \App\Domains\Property\Enums\PropertyStatus::Available,
            'verification_level' => 0,
            'ownership_status' => 'unverified',
        ]);

        $listing = \App\Domains\Listing\Models\Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-99012',
            'slug' => 'slug-yeka-villa-' . Str::random(6),
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => 1,
            'transaction_mode' => 'BUY',
            'price_etb' => 20_000_000.00,
            'is_negotiable' => true,
            'status' => \App\Domains\Listing\Enums\ListingStatus::Published,
        ]);

        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-000881',
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'buyer_user_id' => $buyer->id,
            'seller_user_id' => $seller->id,
            'listed_asking_price_etb' => 20_000_000.00,
            'initial_offer_amount_etb' => 18_500_000.00,
            'latest_amount_etb' => 18_500_000.00,
            'status' => 'submitted',
            'awaiting_response_from' => 'seller',
            'expires_at' => now()->addDays(5),
        ]);

        $negotiationAction = app(RecordNegotiationTurnAction::class);

        // 1. Fails because seller identity is unverified
        try {
            $negotiationAction->executeDatabaseTurn($seller, $offer, 'accept');
            $this->fail('Expected unverified seller to be blocked from accepting offer.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Seller identity must be verified', $e->getMessage());
        }

        // 2. Verify seller identity, but leave property ownership unverified
        $seller->update([
            'verification_level' => 1,
            'identity_verified_at' => now(),
            'national_id_number' => 'FAN-1122-3344-5566',
        ]);

        try {
            $negotiationAction->executeDatabaseTurn($seller, $offer->fresh(), 'accept');
            $this->fail('Expected unverified property ownership to be blocked from accepting offer.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Property ownership or selling authority documentation must be reviewed', $e->getMessage());
        }
    }

    public function test_accepted_property_offer_captures_immutable_unified_seller_snapshot(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Verified Owner Aster',
            'phone' => '+251911332211',
            'verification_level' => 2,
            'identity_verified_at' => now(),
            'national_id_number' => 'FAN-9988-7766-5544',
            'status' => 'active',
        ]);

        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Buyer Melaku',
            'phone' => '+251911998811',
            'status' => 'active',
        ]);

        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Officer Beth',
            'phone' => '+251911009988',
            'status' => 'active',
        ]);
        $officer->assignRole('verification_officer');

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-AA-BOLE-00991',
            'owner_id' => $seller->id,
            'category_id' => 1,
            'property_type_id' => 1,
            'title_en' => 'Bole Medhanealem Penthouse',
            'status' => \App\Domains\Property\Enums\PropertyStatus::Available,
            'verification_level' => 3,
            'ownership_status' => 'authority_verified',
            'ownership_evidence_type' => 'title_deed',
            'ownership_verified_at' => now(),
            'ownership_verified_by_user_id' => $officer->id,
            'ownership_legal_disclaimer' => 'Internal platform document review only; not official government land registry certification.',
        ]);

        $listing = \App\Domains\Listing\Models\Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-99019',
            'slug' => 'slug-bole-' . Str::random(6),
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => 1,
            'transaction_mode' => 'BUY',
            'price_etb' => 25_000_000.00,
            'is_negotiable' => true,
            'status' => \App\Domains\Listing\Enums\ListingStatus::Published,
        ]);

        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-000992',
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'buyer_user_id' => $buyer->id,
            'seller_user_id' => $seller->id,
            'listed_asking_price_etb' => 25_000_000.00,
            'initial_offer_amount_etb' => 23_500_000.00,
            'latest_amount_etb' => 23_500_000.00,
            'status' => 'submitted',
            'awaiting_response_from' => 'seller',
            'expires_at' => now()->addDays(5),
        ]);

        $negotiationAction = app(RecordNegotiationTurnAction::class);
        $finalizedOffer = $negotiationAction->executeDatabaseTurn($seller, $offer, 'accept');

        $this->assertSame('accepted', $finalizedOffer->status);
        $snapshot = $finalizedOffer->seller_identity_snapshot_json;
        $this->assertIsArray($snapshot);
        $this->assertSame('Verified Owner Aster', $snapshot['seller_name']);
        $this->assertSame('FAN-****-5544', $snapshot['masked_national_id']);
        $this->assertSame('individual_owner', $snapshot['seller_type']);
        $this->assertSame('authority_verified', $snapshot['property_ownership']['ownership_status']);
        $this->assertSame('title_deed', $snapshot['property_ownership']['ownership_evidence_type']);
        $this->assertStringContainsString('Internal platform document review only', $snapshot['property_ownership']['ownership_legal_disclaimer']);
        $this->assertSame(23_500_000.0, (float) $snapshot['transaction']['agreed_price_etb']);

        // Profile changes after the sale do not mutate the immutable snapshot
        $seller->update([
            'name' => 'Aster Changed Identity Profile',
            'phone' => '+251999999999',
            'national_id_number' => 'FAN-0000-0000-0000',
        ]);

        $reloadedOffer = PropertyOffer::find($finalizedOffer->id);
        $this->assertSame('Verified Owner Aster', $reloadedOffer->seller_identity_snapshot_json['seller_name']);
        $this->assertSame('FAN-****-5544', $reloadedOffer->seller_identity_snapshot_json['masked_national_id']);
    }

    public function test_buyer_can_access_seller_identity_record_and_file_dispute_for_offer(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Verified Seller Kidist',
            'phone' => '+251911223344',
            'verification_level' => 2,
            'identity_verified_at' => now(),
            'national_id_number' => 'FAN-4433-2211-9900',
            'status' => 'active',
        ]);

        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Buyer Binyam',
            'phone' => '+251911556677',
            'status' => 'active',
        ]);

        $unrelatedBuyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unrelated Buyer Natnael',
            'phone' => '+251911888999',
            'status' => 'active',
        ]);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-AA-ARADA-00122',
            'owner_id' => $seller->id,
            'category_id' => 1,
            'property_type_id' => 1,
            'title_en' => 'Piazza Heritage Apartment',
            'status' => \App\Domains\Property\Enums\PropertyStatus::Available,
            'verification_level' => 3,
            'ownership_status' => 'authority_verified',
            'ownership_evidence_type' => 'leasehold_certificate',
            'ownership_verified_at' => now(),
            'ownership_legal_disclaimer' => 'Internal platform document review only; not official government land registry certification.',
        ]);

        $listing = \App\Domains\Listing\Models\Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-99025',
            'slug' => 'slug-arada-' . Str::random(6),
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => 1,
            'transaction_mode' => 'BUY',
            'price_etb' => 15_000_000.00,
            'is_negotiable' => true,
            'status' => \App\Domains\Listing\Enums\ListingStatus::Published,
        ]);

        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-000773',
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'buyer_user_id' => $buyer->id,
            'seller_user_id' => $seller->id,
            'listed_asking_price_etb' => 15_000_000.00,
            'initial_offer_amount_etb' => 14_000_000.00,
            'latest_amount_etb' => 14_000_000.00,
            'status' => 'accepted',
            'accepted_at' => now(),
            'expires_at' => now()->addDays(5),
        ]);

        // 1. Authenticated buyer views seller identity record
        $response = $this->actingAs($buyer)->get("/buyer/contracts/{$offer->offer_code}");
        $response->assertStatus(200);
        $response->assertSee('FAN-****-9900');
        $response->assertSee('Verified Seller &amp; Developer Identity Record', false);
        $response->assertSee('Piazza Heritage Apartment');

        // Full National ID is never displayed
        $response->assertDontSee('FAN-4433-2211-9900');

        // 2. Unrelated buyer is strictly denied (403)
        $unrelatedResponse = $this->actingAs($unrelatedBuyer)->get("/buyer/contracts/{$offer->offer_code}");
        $unrelatedResponse->assertStatus(403);

        // 3. Buyer files transaction dispute
        $complaintResponse = $this->actingAs($buyer)->post("/buyer/contracts/{$offer->offer_code}/complaints", [
            'subject' => 'Seller refusal to transfer title deed after accepted deal',
            'category' => 'contract_breach',
            'priority' => 'urgent',
            'description' => 'The seller agreed to the 14M ETB purchase price but is now refusing to proceed to the subcity land registry transfer.',
        ]);

        $complaintResponse->assertRedirect("/buyer/contracts/{$offer->offer_code}");
        $complaintResponse->assertSessionHas('status');

        // Verify SupportTicket in database
        $ticket = SupportTicket::query()->where('offer_id', $offer->id)->first();
        $this->assertNotNull($ticket);
        $this->assertSame('contract_breach', $ticket->category);
        $this->assertSame($seller->id, $ticket->against_user_id);
        $this->assertSame($offer->offer_code, $ticket->transaction_reference);
        $this->assertIsArray($ticket->seller_snapshot_json);
        $this->assertSame('FAN-****-9900', $ticket->seller_snapshot_json['masked_national_id']);

        // 4. Unrelated buyer cannot file dispute against this transaction
        $unrelatedComplaintResponse = $this->actingAs($unrelatedBuyer)->post("/buyer/contracts/{$offer->offer_code}/complaints", [
            'subject' => 'Unauthorized dispute attempt',
            'description' => 'This should be blocked immediately.',
        ]);
        $unrelatedComplaintResponse->assertStatus(403);
    }

    public function test_seller_identity_snapshot_cannot_be_silently_overwritten_on_accepted_offer(): void
    {
        $fixture = $this->createBaseOfferFixture();
        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-000889',
            'listing_id' => $fixture['listing']->id,
            'property_id' => $fixture['property']->id,
            'buyer_user_id' => $fixture['buyer']->id,
            'seller_user_id' => $fixture['seller']->id,
            'listed_asking_price_etb' => 10_000_000.00,
            'initial_offer_amount_etb' => 9_500_000.00,
            'latest_amount_etb' => 9_500_000.00,
            'status' => 'accepted',
            'expires_at' => now()->addDays(5),
            'seller_identity_snapshot_json' => [
                'snapshot_version' => 'v1.0-unified',
                'seller_name' => 'Original Honest Seller',
                'masked_national_id' => 'FAN-****-1234',
            ],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Immutable transaction evidence: historical seller identity snapshot cannot be silently modified or overwritten.');

        // Attempt silent overwrite
        $offer->update([
            'seller_identity_snapshot_json' => [
                'snapshot_version' => 'v1.0-tampered',
                'seller_name' => 'Fraudulent Replacement Seller',
            ],
        ]);
    }

    public function test_authorized_snapshot_correction_preserves_immutable_audit_history(): void
    {
        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Officer Solomon',
            'phone' => '+251911333444',
            'status' => 'active',
        ]);

        $fixture = $this->createBaseOfferFixture();
        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-000890',
            'listing_id' => $fixture['listing']->id,
            'property_id' => $fixture['property']->id,
            'buyer_user_id' => $fixture['buyer']->id,
            'seller_user_id' => $fixture['seller']->id,
            'listed_asking_price_etb' => 10_000_000.00,
            'initial_offer_amount_etb' => 9_500_000.00,
            'latest_amount_etb' => 9_500_000.00,
            'status' => 'accepted',
            'expires_at' => now()->addDays(5),
            'seller_identity_snapshot_json' => [
                'snapshot_version' => 'v1.0-unified',
                'seller_name' => 'Original Seller Abebe',
                'masked_national_id' => 'FAN-****-1234',
            ],
        ]);

        // Legitimate audited correction
        $offer->applyAuditedSnapshotCorrection(
            updates: ['seller_name' => 'Abebe Kebede (Corrected Surname per Subcity Record)'],
            officerUserId: $officer->id,
            reason: 'Seller surname updated per official subcity land registry correction order'
        );

        $offer->refresh();
        $snapshot = $offer->seller_identity_snapshot_json;

        $this->assertSame('Abebe Kebede (Corrected Surname per Subcity Record)', $snapshot['seller_name']);
        $this->assertArrayHasKey('snapshot_correction', $snapshot);
        $this->assertSame($officer->id, $snapshot['snapshot_correction']['corrected_by_user_id']);
        $this->assertSame('Seller surname updated per official subcity land registry correction order', $snapshot['snapshot_correction']['reason']);
        $this->assertCount(1, $snapshot['snapshot_correction']['history']);
        $this->assertSame('Original Seller Abebe', $snapshot['snapshot_correction']['history'][0]['state']['seller_name']);
    }

    private function createBaseOfferFixture(): array
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Test Base',
            'phone' => '+251911' . random_int(100000, 999999),
            'status' => 'active',
        ]);

        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Buyer Test Base',
            'phone' => '+251911' . random_int(100000, 999999),
            'status' => 'active',
        ]);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-TEST-' . Str::random(8),
            'owner_id' => $seller->id,
            'category_id' => 1,
            'property_type_id' => 1,
            'title_en' => 'Test Base Property',
            'status' => \App\Domains\Property\Enums\PropertyStatus::Available,
        ]);

        $listing = \App\Domains\Listing\Models\Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-TEST-' . Str::random(8),
            'slug' => 'test-base-' . Str::random(6),
            'property_id' => $property->id,
            'published_by_user_id' => $seller->id,
            'listing_type_id' => 1,
            'transaction_mode' => 'BUY',
            'price_etb' => 10_000_000.00,
            'status' => \App\Domains\Listing\Enums\ListingStatus::Published,
        ]);

        return compact('seller', 'buyer', 'property', 'listing');
    }
}
