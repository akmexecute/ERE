<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\Host\Models\Host;
use App\Domains\Host\Services\HostDashboardKpiCalculator;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Review\Actions\ModerateStayReviewAction;
use App\Domains\Review\Actions\SubmitStayReviewAction;
use App\Domains\Review\Models\StayReview;
use App\Domains\Stay\Actions\ProcessStayCheckInAction;
use App\Domains\Stay\Actions\ProcessStayCheckOutAction;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\AccommodationUnitType;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Phase2Milestone2ReviewsAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $hostUser;
    private User $guestUser;
    private User $staffUser;
    private Host $host;
    private Property $property;
    private Listing $listing;
    private StayListingProfile $profile;
    private AccommodationUnit $unit;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);

        $this->hostUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Host Tigist',
            'phone' => '+251911445566',
            'status' => 'active',
            'phone_verified_at' => now(),
            'verification_level' => 3,
            'national_id_number' => 'FAN-9988-7766-5544',
        ]);
        $this->hostUser->assignRole('host');

        $this->guestUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Guest Dawit',
            'phone' => '+251922556677',
            'status' => 'active',
            'phone_verified_at' => now(),
            'verification_level' => 1,
            'national_id_number' => 'FAN-1122-3344-5566',
        ]);

        $this->staffUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Moderator Staff',
            'phone' => '+251933778899',
            'status' => 'active',
            'phone_verified_at' => now(),
            'verification_level' => 5,
        ]);
        $this->staffUser->assignRole('moderator');

        $this->host = Host::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->hostUser->id,
            'host_type' => 'individual_owner',
            'display_name' => 'Tigist Bole Residence',
            'average_rating' => 0.00,
            'reviews_count' => 0,
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00007711',
            'owner_id' => $this->hostUser->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Tigist Luxury Garden Villa',
            'status' => 'available',
            'verification_level' => 3,
        ]);

        $listingType = ListingType::query()->firstOrCreate(
            ['code' => 'SHORT_STAY_ENTIRE'],
            ['slug' => 'short-stay-entire', 'name_en' => 'Short Stay', 'transaction_mode' => 'STAY', 'is_active' => true]
        );

        $this->listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-STAY-7711',
            'slug' => 'tigist-luxury-villa',
            'property_id' => $this->property->id,
            'published_by_user_id' => $this->hostUser->id,
            'listing_type_id' => $listingType->id,
            'transaction_mode' => 'STAY',
            'price_etb' => 8000.00,
            'currency' => 'ETB',
            'status' => ListingStatus::Published,
            'published_at' => now(),
        ]);

        $this->profile = StayListingProfile::query()->create([
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'host_id' => $this->host->id,
            'accommodation_model' => 'entire_place',
            'base_nightly_rate_etb' => 8000.00,
        ]);

        $unitType = AccommodationUnitType::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'property_id' => $this->property->id,
            'name_en' => 'Entire Villa',
            'max_guests' => 6,
            'base_nightly_rate_etb' => 8000.00,
        ]);

        $this->unit = AccommodationUnit::query()->create([
            'unit_type_id' => $unitType->id,
            'property_id' => $this->property->id,
            'unit_number_or_code' => 'VILLA-7',
            'status' => 'ready',
        ]);

        $this->booking = Booking::query()->create([
            'uuid' => (string) Str::uuid(),
            'booking_code' => 'BKG-2026-000771',
            'idempotency_key' => 'IDEM-TEST-7711',
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'stay_listing_profile_id' => $this->profile->id,
            'accommodation_unit_id' => $this->unit->id,
            'host_id' => $this->host->id,
            'guest_user_id' => $this->guestUser->id,
            'check_in_date' => CarbonImmutable::now()->subDays(3)->toDateString(),
            'check_out_date' => CarbonImmutable::now()->subDays(1)->toDateString(),
            'nights_count' => 2,
            'adults_count' => 2,
            'children_count' => 0,
            'infants_count' => 0,
            'pets_count' => 0,
            'accommodation_raw_subtotal_etb' => 16000.00,
            'discount_amount_etb' => 0.00,
            'accommodation_net_subtotal_etb' => 16000.00,
            'cleaning_fee_etb' => 1000.00,
            'platform_fee_etb' => 500.00,
            'tax_amount_etb' => 2400.00,
            'security_deposit_etb' => 5000.00,
            'total_amount_etb' => 19900.00,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now()->subDays(4),
        ]);

        // Complete check-in and checkout so review is eligible
        app(ProcessStayCheckInAction::class)->execute($this->booking, $this->guestUser);
        app(ProcessStayCheckOutAction::class)->execute($this->booking, $this->guestUser);
    }

    public function test_two_way_review_double_blind_publication_and_reputation_update(): void
    {
        $submitAction = app(SubmitStayReviewAction::class);

        // 1. Guest submits review
        $guestReview = $submitAction->execute(
            booking: $this->booking,
            reviewer: $this->guestUser,
            direction: 'guest_to_host',
            scores: [
                'cleanliness' => 5,
                'accuracy' => 5,
                'communication' => 5,
                'location' => 5,
                'value' => 5,
            ],
            publicComment: 'Flawless stay in Bole with amazing generator backup!',
            privateFeedbackNote: 'Thanks for the coffee kit!'
        );

        $this->assertInstanceOf(StayReview::class, $guestReview);
        // Under double-blind rule, review remains unpublished until counterparty submits
        $this->assertFalse($guestReview->is_published);
        $this->assertNull($guestReview->published_at);

        // 2. Host submits counterparty review
        $hostReview = $submitAction->execute(
            booking: $this->booking,
            reviewer: $this->hostUser,
            direction: 'host_to_guest',
            scores: [
                'communication' => 5,
                'property_care' => 5,
                'rule_compliance' => 5,
            ],
            publicComment: 'Dawit was a polite, respectful guest who left the place spotless.'
        );

        // Both reviews are now published simultaneously!
        $this->assertTrue($hostReview->fresh()->is_published);
        $this->assertTrue($guestReview->fresh()->is_published);
        $this->assertNotNull($guestReview->fresh()->published_at);

        // 3. Reputation metrics updated
        $this->assertEquals(5.00, (float) $this->host->fresh()->average_rating);
        $this->assertSame(1, $this->host->fresh()->reviews_count);
    }

    public function test_anti_spam_moderation_flags_external_contact_and_allows_staff_moderation(): void
    {
        $submitAction = app(SubmitStayReviewAction::class);

        // Spam review containing telegram link
        $spamReview = $submitAction->execute(
            booking: $this->booking,
            reviewer: $this->guestUser,
            direction: 'guest_to_host',
            scores: [
                'cleanliness' => 5,
                'accuracy' => 4,
                'communication' => 5,
                'location' => 5,
                'value' => 4,
            ],
            publicComment: 'Great house, contact me on telegram t.me/discount_booking for cheaper stays!'
        );

        $this->assertSame('flagged_spam', $spamReview->moderation_status);
        $this->assertFalse($spamReview->is_published);

        // Staff moderates review: investigates and removes
        $moderateAction = app(ModerateStayReviewAction::class);
        $moderated = $moderateAction->execute(
            review: $spamReview,
            staffUser: $this->staffUser,
            action: 'remove',
            reason: 'Solicitation of off-platform external contact.'
        );

        $this->assertSame('removed', $moderated->moderation_status);
        $this->assertFalse($moderated->is_published);
        $this->assertDatabaseHas('review_moderation_logs', [
            'stay_review_id' => $spamReview->id,
            'admin_user_id' => $this->staffUser->id,
            'action' => 'remove',
        ]);
    }

    public function test_real_database_kpi_computation_accurately_scopes_metrics(): void
    {
        // Add a security deposit in held state
        SecurityDeposit::query()->create([
            'uuid' => (string) Str::uuid(),
            'booking_id' => $this->booking->id,
            'host_id' => $this->host->id,
            'guest_user_id' => $this->guestUser->id,
            'held_amount_etb' => 5000.00,
            'deducted_amount_etb' => 0.00,
            'released_to_guest_etb' => 0.00,
            'status' => 'held',
        ]);

        // Add a cleaning task
        CleaningTask::query()->create([
            'uuid' => (string) Str::uuid(),
            'task_code' => 'CLN-TEST-001',
            'booking_id' => $this->booking->id,
            'property_id' => $this->property->id,
            'accommodation_unit_id' => $this->unit->id,
            'host_id' => $this->host->id,
            'scheduled_date' => now()->toDateString(),
            'status' => CleaningTaskStatus::Inspection,
        ]);

        $kpis = HostDashboardKpiCalculator::computeFromDatabase($this->host);

        $this->assertSame(1, $kpis['properties_count']);
        $this->assertSame(1, $kpis['bookable_units_count']);
        $this->assertEquals(16000.00, $kpis['accommodation_revenue_etb']);
        $this->assertEquals(1000.00, $kpis['cleaning_fees_etb']);
        $this->assertEquals(5000.00, $kpis['deposits_held_etb']);
        $this->assertSame(1, $kpis['tasks_inspection']);
        $this->assertGreaterThan(0, $kpis['adr_etb']);
    }

    public function test_host_controlled_kpi_visibility_preferences_persistence_and_reset(): void
    {
        // 1. Initial defaults have all cards visible
        $defaults = HostDashboardKpiCalculator::getPreferences($this->host);
        $this->assertTrue($defaults['kpi_occupancy']);
        $this->assertTrue($defaults['kpi_revenue']);

        // 2. Host hides occupancy and ADR
        $saved = HostDashboardKpiCalculator::savePreferences($this->host, [
            'kpi_occupancy' => false,
            'kpi_adr' => false,
            'kpi_revenue' => true,
            'kpi_housekeeping' => true,
        ]);

        $this->assertFalse($saved['kpi_occupancy']);
        $this->assertFalse($saved['kpi_adr']);
        $this->assertTrue($saved['kpi_revenue']);

        $refreshed = HostDashboardKpiCalculator::getPreferences($this->host->fresh());
        $this->assertFalse($refreshed['kpi_occupancy']);
        $this->assertFalse($refreshed['kpi_adr']);

        // 3. Reset to default restores all cards
        $reset = HostDashboardKpiCalculator::resetPreferences($this->host->fresh());
        $this->assertTrue($reset['kpi_occupancy']);
        $this->assertTrue($reset['kpi_adr']);
    }

    public function test_host_dashboard_route_persists_preferences_via_post_request(): void
    {
        $response = $this->actingAs($this->hostUser)->post('/host/dashboard/preferences', [
            'cards' => [
                'kpi_occupancy' => 0,
                'kpi_revenue' => 1,
                'kpi_housekeeping' => 1,
            ],
        ]);

        $response->assertRedirect();
        $this->assertFalse((bool) ($this->host->fresh()->kpi_card_preferences['kpi_occupancy'] ?? true));
    }

    public function test_spam_review_by_one_party_does_not_suppress_counterparty_eligible_review(): void
    {
        $submitAction = app(SubmitStayReviewAction::class);

        // 1. Guest submits legitimate approved review with private feedback note
        $guestReview = $submitAction->execute(
            booking: $this->booking,
            reviewer: $this->guestUser,
            direction: 'guest_to_host',
            scores: [
                'cleanliness' => 5,
                'accuracy' => 5,
                'communication' => 5,
                'location' => 5,
                'value' => 5,
            ],
            publicComment: 'The stay was wonderful, very clean and quiet neighborhood.',
            privateFeedbackNote: 'The air conditioner remote battery was slightly weak.'
        );

        $this->assertFalse($guestReview->is_published);
        $this->assertSame('approved', $guestReview->moderation_status);

        // Verify private feedback note is hidden from serialization
        $serialized = $guestReview->toArray();
        $this->assertArrayNotHasKey('private_feedback_note', $serialized);

        // 2. Host submits spam review containing external link
        $hostReview = $submitAction->execute(
            booking: $this->booking,
            reviewer: $this->hostUser,
            direction: 'host_to_guest',
            scores: [
                'communication' => 4,
                'property_care' => 4,
                'rule_compliance' => 4,
            ],
            publicComment: 'Decent guest. Contact us on telegram https://t.me/ethio_stays for future bookings.'
        );

        // Host spam review is flagged and NOT published
        $this->assertSame('flagged_spam', $hostReview->fresh()->moderation_status);
        $this->assertFalse($hostReview->fresh()->is_published);

        // Crucial: Guest approved review IS published (not indefinitely suppressed!)
        $this->assertTrue($guestReview->fresh()->is_published);
        $this->assertNotNull($guestReview->fresh()->published_at);
    }
}
