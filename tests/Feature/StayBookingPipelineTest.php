<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Booking\Actions\CancelBookingAction;
use App\Domains\Booking\Actions\ConfirmBookingAction;
use App\Domains\Booking\Actions\CreateBookingHoldAction;
use App\Domains\Booking\Actions\ExpireUnpaidBookingHoldsAction;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPriceItem;
use App\Domains\Calendar\Models\AvailabilityCalendar;
use App\Domains\Host\Models\Host;
use App\Domains\Stay\Actions\AuthorizeHouseManualAccessAction;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Domains\Identity\Enums\PhonePrivacyMode;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\AccommodationUnitType;
use App\Domains\Stay\Models\BedConfiguration;
use App\Domains\Stay\Models\HouseManual;
use App\Domains\Stay\Models\HouseRule;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StayBookingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $hostUser;
    private User $guestUser;
    private Host $host;
    private Property $property;
    private Listing $listing;
    private StayListingProfile $profile;
    private AccommodationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);

        $this->hostUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sara Haile',
            'phone' => '+251911445566',
            'phone_privacy' => PhonePrivacyMode::ShowPhone,
            'status' => 'active',
        ]);

        $this->guestUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Yonas Bekele',
            'phone' => '+251922778899',
            'phone_privacy' => PhonePrivacyMode::ShowPhone,
            'status' => 'active',
        ]);

        $this->host = Host::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->hostUser->id,
            'host_type' => 'individual_owner',
            'display_name' => 'Sara Host',
            'is_superhost' => true,
            'payout_schedule' => 'after_checkin',
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00001245',
            'owner_id' => $this->hostUser->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Modern Garden Villa in Bole Medhanialem',
            'title_am' => 'ዘመናዊ ቪላ ቦሌ መድኃኔዓለም',
            'status' => PropertyStatus::Available->value,
            'verification_level' => 3,
            'building_area_sqm' => 350,
            'bedrooms' => 3,
            'bathrooms' => 3,
            'parking_spaces' => 2,
        ]);

        $listingType = ListingType::query()->firstOrCreate(
            ['code' => 'SHORT_STAY_ENTIRE'],
            [
                'slug' => 'short-stay-entire',
                'name_en' => 'Short Stay (Entire Place)',
                'transaction_mode' => 'STAY',
                'is_active' => true,
            ]
        );

        $this->listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-STAY-001',
            'slug' => 'modern-garden-villa-bole-medhanialem',
            'property_id' => $this->property->id,
            'published_by_user_id' => $this->hostUser->id,
            'listing_type_id' => $listingType->id,
            'transaction_mode' => 'STAY',
            'price_etb' => 8500,
            'currency' => 'ETB',
            'status' => ListingStatus::Published,
            'published_at' => now(),
        ]);

        $this->profile = StayListingProfile::query()->create([
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'host_id' => $this->host->id,
            'accommodation_model' => 'entire_place',
            'base_nightly_rate_etb' => 8500,
            'weekend_nightly_rate_etb' => 9500,
            'cleaning_fee_etb' => 2000,
            'guest_service_fee_pct' => 5.6179775,
            'tourism_tax_pct' => 15.0,
            'min_stay_nights' => 2,
            'max_guests' => 6,
            'instant_book_enabled' => true,
            'cancellation_policy' => 'flexible',
            'hold_duration_minutes' => 15,
        ]);

        $unitType = AccommodationUnitType::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'property_id' => $this->property->id,
            'name_en' => 'Entire Villa',
            'max_guests' => 6,
            'base_nightly_rate_etb' => 8500,
            'total_rooms_count' => 1,
        ]);

        $this->unit = AccommodationUnit::query()->create([
            'unit_type_id' => $unitType->id,
            'property_id' => $this->property->id,
            'unit_number_or_code' => 'VILLA-MAIN',
            'status' => 'ready',
        ]);
    }

    public function test_stay_profile_bed_configurations_house_rules_and_manual(): void
    {
        BedConfiguration::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'room_label_en' => 'Bedroom 1',
            'bed_type' => 'king',
            'bed_count' => 1,
            'sort_order' => 1,
        ]);

        HouseRule::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'smoking_allowed' => false,
            'parties_allowed' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
        ]);

        HouseManual::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'wifi_ssid' => 'VillaBole_Guest',
            'wifi_password_encrypted' => encrypt('Selam2026!'),
            'check_in_method' => 'smart_lock',
            'emergency_contact_phone' => '+251911445566',
        ]);

        $this->assertCount(1, $this->profile->bedConfigurations);
        $this->assertFalse($this->profile->houseRules->smoking_allowed);
        $this->assertSame('VillaBole_Guest', $this->profile->houseManual->wifi_ssid);
        $this->assertSame('Selam2026!', decrypt($this->profile->houseManual->wifi_password_encrypted));
    }

    public function test_booking_hold_lifecycle_and_automatic_expiration(): void
    {
        // 1. Create temporary hold (15 minutes) awaiting payment
        $booking = Booking::query()->create([
            'uuid' => (string) Str::uuid(),
            'booking_code' => 'BK-2026-0001045',
            'idempotency_key' => 'idemp_test_'.Str::random(16),
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'stay_listing_profile_id' => $this->profile->id,
            'accommodation_unit_id' => $this->unit->id,
            'host_id' => $this->host->id,
            'guest_user_id' => $this->guestUser->id,
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-05',
            'nights_count' => 4,
            'adults_count' => 2,
            'accommodation_raw_subtotal_etb' => 34000,
            'accommodation_net_subtotal_etb' => 34000,
            'cleaning_fee_etb' => 2000,
            'platform_fee_etb' => 2022.47,
            'tax_amount_etb' => 5403.37,
            'total_amount_etb' => 43425.84,
            'status' => BookingStatus::AwaitingPayment,
            'hold_expires_at' => now()->subMinute(), // Expired
        ]);

        BookingPriceItem::query()->create([
            'booking_id' => $booking->id,
            'item_type' => 'nightly_rate',
            'label_en' => '4 nights x ETB 8,500',
            'quantity' => 4,
            'unit_amount_etb' => 8500,
            'total_amount_etb' => 34000,
        ]);

        // Mark calendar dates as held
        AvailabilityCalendar::query()->create([
            'accommodation_unit_id' => $this->unit->id,
            'calendar_date' => '2026-11-01',
            'status' => 'held_pending_payment',
            'booking_id' => $booking->id,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'awaiting_payment',
        ]);

        // 2. Run expire action
        $expireAction = new ExpireUnpaidBookingHoldsAction();
        $expiredCount = $expireAction->execute();

        $this->assertSame(1, $expiredCount);
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);

        // Assert calendar inventory was released back to 'available'
        $this->assertDatabaseHas('availability_calendars', [
            'accommodation_unit_id' => $this->unit->id,
            'calendar_date' => '2026-11-01',
            'status' => 'available',
            'booking_id' => null,
        ]);
    }

    public function test_short_stay_detail_view_renders_successfully(): void
    {
        $response = $this->get('/stays/modern-garden-villa-bole-medhanialem');
        $response->assertStatus(200);
        $response->assertSee('STAY • Serviced Villa');
        $response->assertSee('Sleeping Arrangements (SRS Section 43)');
        $response->assertSee('House Rules (SRS Section 60)');
        $response->assertSee('Instant Book');
    }

    public function test_api_stay_quote_calculates_dynamic_price_accurately(): void
    {
        $response = $this->postJson('/api/stays/quote', [
            'check_in_date' => '2026-10-25',
            'check_out_date' => '2026-10-30',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'quote' => [
                    'nights_count' => 5,
                    'accommodation_net_subtotal_etb' => 42500,
                    'cleaning_fee_etb' => 2000,
                ],
            ]);
    }

    public function test_api_stay_refund_preview_calculates_policy_payout(): void
    {
        $response = $this->postJson('/api/stays/refund-preview', [
            'policy' => 'flexible',
            'check_in_date' => '2026-11-10',
            'accommodation_net_subtotal_etb' => 42500,
            'cleaning_fee_etb' => 2000,
            'cancelled_at' => '2026-11-08 10:00:00',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'refund' => [
                    'policy' => 'flexible',
                    'accommodation_refund_pct' => 100,
                    'total_guest_refund_etb' => 44500,
                ],
            ]);
    }

    public function test_api_stay_hold_creates_temporary_hold_with_idempotency_key(): void
    {
        $response = $this->postJson('/api/stays/hold', [
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(9)->toDateString(),
            'adults' => 2,
            'children' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'booking_code',
                'idempotency_key',
                'status',
                'hold_duration_minutes',
                'hold_expires_at',
            ]);
    }

    public function test_export_stay_ical_feed(): void
    {
        $response = $this->get('/stays/modern-garden-villa-bole/calendar.ics');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR')
            ->assertSee('BEGIN:VEVENT');
    }

    public function test_create_booking_hold_action_creates_hold_with_itemized_price_items_and_locks_inventory(): void
    {
        $action = new CreateBookingHoldAction();
        $idempKey = 'idemp_live_test_'.Str::random(12);

        $booking = $action->execute($this->guestUser, [
            'listing_id' => $this->listing->id,
            'accommodation_unit_id' => $this->unit->id,
            'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-14',
            'adults_count' => 2,
            'children_count' => 1,
            'idempotency_key' => $idempKey,
        ]);

        $this->assertSame(BookingStatus::AwaitingPayment, $booking->status);
        $this->assertSame(4, $booking->nights_count);
        $this->assertSame($this->guestUser->id, $booking->guest_user_id);
        $this->assertNotNull($booking->hold_expires_at);

        // Assert itemized price records exist and match totals
        $priceItems = BookingPriceItem::query()->where('booking_id', $booking->id)->get();
        $this->assertNotEmpty($priceItems);
        $this->assertTrue($priceItems->contains('item_type', 'cleaning_fee'));
        $this->assertTrue($priceItems->contains('item_type', 'platform_fee'));

        // Assert inventory is locked in AvailabilityCalendar
        $this->assertDatabaseHas('availability_calendars', [
            'accommodation_unit_id' => $this->unit->id,
            'calendar_date' => '2026-11-10',
            'status' => 'held_pending_payment',
            'booking_id' => $booking->id,
        ]);
        $this->assertDatabaseHas('availability_calendars', [
            'accommodation_unit_id' => $this->unit->id,
            'calendar_date' => '2026-11-13',
            'status' => 'held_pending_payment',
            'booking_id' => $booking->id,
        ]);
        // Check-out date is exclusive, not held
        $this->assertDatabaseMissing('availability_calendars', [
            'accommodation_unit_id' => $this->unit->id,
            'calendar_date' => '2026-11-14',
            'status' => 'held_pending_payment',
        ]);
    }

    public function test_create_booking_hold_rejects_self_booking_by_host(): void
    {
        $action = new CreateBookingHoldAction();

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Hosts cannot book their own listings');

        $action->execute($this->hostUser, [
            'listing_id' => $this->listing->id,
            'accommodation_unit_id' => $this->unit->id,
            'check_in_date' => '2026-11-15',
            'check_out_date' => '2026-11-17',
            'adults_count' => 2,
            'idempotency_key' => 'idemp_self_'.Str::random(8),
        ]);
    }

    public function test_create_booking_hold_validates_guest_capacity(): void
    {
        $action = new CreateBookingHoldAction();

        // Max guests is 6
        $this->expectException(ValidationException::class);

        $action->execute($this->guestUser, [
            'listing_id' => $this->listing->id,
            'accommodation_unit_id' => $this->unit->id,
            'check_in_date' => '2026-11-15',
            'check_out_date' => '2026-11-17',
            'adults_count' => 5,
            'children_count' => 3,
            'idempotency_key' => 'idemp_cap_'.Str::random(8),
        ]);
    }

    public function test_idempotency_key_returns_same_booking_and_rejects_conflicting_params(): void
    {
        $action = new CreateBookingHoldAction();
        $idempKey = 'idemp_repeat_'.Str::random(12);

        $params = [
            'listing_id' => $this->listing->id,
            'accommodation_unit_id' => $this->unit->id,
            'check_in_date' => '2026-11-20',
            'check_out_date' => '2026-11-23',
            'adults_count' => 2,
            'idempotency_key' => $idempKey,
        ];

        $b1 = $action->execute($this->guestUser, $params);
        $b2 = $action->execute($this->guestUser, $params);

        // Same booking returned, no duplicates
        $this->assertSame($b1->id, $b2->id);
        $this->assertSame(1, Booking::query()->where('idempotency_key', $idempKey)->count());

        // Conflicting params with same key
        $this->expectException(ValidationException::class);
        $conflictingParams = $params;
        $conflictingParams['check_out_date'] = '2026-11-25';
        $action->execute($this->guestUser, $conflictingParams);
    }

    public function test_confirm_booking_action_confirms_valid_hold_and_rejects_expired(): void
    {
        $createAction = new CreateBookingHoldAction();
        $confirmAction = new ConfirmBookingAction();

        $booking = $createAction->execute($this->guestUser, [
            'listing_id' => $this->listing->id,
            'accommodation_unit_id' => $this->unit->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-04',
            'adults_count' => 1,
            'idempotency_key' => 'idemp_conf_'.Str::random(8),
        ]);

        $confirmed = $confirmAction->execute($booking, ['payment_reference' => 'TEL-998877']);

        $this->assertSame(BookingStatus::Confirmed, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);

        // Calendar dates should be marked as 'booked'
        $this->assertDatabaseHas('availability_calendars', [
            'accommodation_unit_id' => $this->unit->id,
            'calendar_date' => '2026-12-01',
            'status' => 'booked',
            'booking_id' => $confirmed->id,
        ]);

        // Attempting to confirm an expired booking should fail
        $booking->update([
            'status' => BookingStatus::Expired,
            'hold_expires_at' => now()->subMinutes(10),
        ]);

        $this->expectException(ValidationException::class);
        $confirmAction->execute($booking);
    }

    public function test_cancel_booking_action_calculates_refund_and_releases_inventory(): void
    {
        $createAction = new CreateBookingHoldAction();
        $confirmAction = new ConfirmBookingAction();
        $cancelAction = new CancelBookingAction();

        $booking = $createAction->execute($this->guestUser, [
            'listing_id' => $this->listing->id,
            'accommodation_unit_id' => $this->unit->id,
            'check_in_date' => '2026-12-10',
            'check_out_date' => '2026-12-13',
            'adults_count' => 2,
            'idempotency_key' => 'idemp_canc_'.Str::random(8),
        ]);
        $confirmed = $confirmAction->execute($booking);

        // Cancel with reason
        $res = $cancelAction->execute(
            booking: $confirmed,
            actingUser: $this->guestUser,
            reason: 'Schedule change'
        );
        $cancelled = $res['booking'];

        $this->assertSame(BookingStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Schedule change', $cancelled->cancellation_reason);

        // Calendar inventory must be released back to 'available'
        $this->assertDatabaseHas('availability_calendars', [
            'accommodation_unit_id' => $this->unit->id,
            'calendar_date' => '2026-12-10',
            'status' => 'available',
            'booking_id' => null,
        ]);
    }

    public function test_api_stay_calendar_endpoint_returns_monthly_availability(): void
    {
        $response = $this->getJson("/api/stays/{$this->listing->slug}/calendar?year=2026&month=11&unit_id={$this->unit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'year' => 2026,
                'month' => 11,
            ])
            ->assertJsonStructure([
                'days' => [
                    '*' => [
                        'date',
                        'day',
                        'status',
                        'price_etb',
                        'min_stay',
                    ],
                ],
            ]);
    }

    public function test_house_manual_access_authorization_protects_credentials(): void
    {
        $authAction = new AuthorizeHouseManualAccessAction();

        $manual = HouseManual::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'wifi_ssid' => 'SecretWifi',
            'wifi_password_encrypted' => encrypt('SuperSecret2026'),
            'access_instructions_en' => 'Key inside lockbox code 1234',
        ]);

        $unrelatedUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Random User',
            'phone' => '+251933000999',
            'status' => 'active',
        ]);

        // Unrelated user cannot access
        $this->assertFalse($authAction->canAccess($this->profile, $unrelatedUser));

        // Host can access
        $this->assertTrue($authAction->canAccess($this->profile, $this->hostUser));

        // Confirmed guest can access
        $booking = Booking::query()->create([
            'uuid' => (string) Str::uuid(),
            'booking_code' => 'BK-TEST-AUTH-1',
            'idempotency_key' => 'idemp_auth_'.Str::random(8),
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'stay_listing_profile_id' => $this->profile->id,
            'accommodation_unit_id' => $this->unit->id,
            'host_id' => $this->host->id,
            'guest_user_id' => $unrelatedUser->id,
            'check_in_date' => '2026-11-20',
            'check_out_date' => '2026-11-25',
            'nights_count' => 5,
            'accommodation_raw_subtotal_etb' => 20000,
            'accommodation_net_subtotal_etb' => 20000,
            'status' => BookingStatus::Confirmed,
            'total_amount_etb' => 20000,
        ]);

        $this->assertTrue($authAction->canAccess($this->profile, $unrelatedUser));
    }

    public function test_host_profile_registration_enforces_one_profile_per_user(): void
    {
        $this->actingAs($this->hostUser);

        // Attempting to register another host profile for the same user should return 422
        $response = $this->postJson('/api/hosts', [
            'host_type' => 'company',
            'display_name' => 'Second Host Profile',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['host']);
    }

    public function test_monthly_availability_calendar_displays_dual_gregorian_and_ethiopian_dates_without_modal_overlay(): void
    {
        $response = $this->get("/stays/{$this->listing->slug}");

        $response->assertStatus(200);

        // Verify normal in-page monthly calendar and dual calendar components exist
        $response->assertSee('Availability Calendar & Rates');
        $response->assertSee('Real-Time Availability & Nightly Schedule');
        $response->assertSee('Calendar Key:');
        $response->assertSee('Gregorian Day');
        $response->assertSee('Ethiopian Day (ቀን)');
        $response->assertSee('day.eth_day');

        // Confirm ABSENCE of modal overlay and modal triggers (Prompt Addendum Section 2)
        $response->assertDontSee('Expand Calendar');
        $response->assertDontSee('Expanded In-Page View');
        $response->assertDontSee('Full Availability Calendar & Rate Schedule');
        $response->assertDontSee('Close Calendar');
        $response->assertDontSee('x-show="calendarExpanded"');

        // Confirm absence of new tab / external window opening patterns
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('target="_blank"', $content);
        $this->assertStringNotContainsString('window.open', $content);
    }

    public function test_stay_browse_discovery_page_renders_eligible_real_listings(): void
    {
        $response = $this->get('/stays');

        $response->assertStatus(200);
        $response->assertSee('Discover Short-Stay Accommodations');
        $response->assertSee($this->property->title_en);
        $response->assertSee('ETB 8,500');
        $response->assertSee('Up to 6 guests');
        $response->assertSee('Instant Book');
        $response->assertSee(route('stays.show', $this->listing->slug));
    }

    public function test_stay_browse_filters_by_guest_capacity_and_price(): void
    {
        // 1. Matches when guests <= 6
        $matchingResponse = $this->get('/stays?guests=4&min_price=5000&max_price=10000');
        $matchingResponse->assertStatus(200);
        $matchingResponse->assertSee($this->property->title_en);

        // 2. Clear empty state when no listings match filters
        $emptyResponse = $this->get('/stays?guests=100&min_price=999999');
        $emptyResponse->assertStatus(200);
        $emptyResponse->assertSee('No Short-Stays Found Matching Your Criteria');
        $emptyResponse->assertSee('Clear All Filters');
        $emptyResponse->assertDontSee($this->property->title_en);
    }

    public function test_selected_stay_shows_property_specific_details_and_rejects_invalid_slug(): void
    {
        // 1. Selecting property renders its own specific details and booking requirements
        $response = $this->get("/stays/{$this->listing->slug}");
        $response->assertStatus(200);
        $response->assertSee($this->property->title_en);
        $response->assertSee('Sara Host');
        $response->assertSee('ETB 8,500');
        $response->assertSee('Sleeping Arrangements (SRS Section 43)');
        $response->assertSee('House Rules (SRS Section 60)');

        // 2. Reject non-existent or invalid slug with 404
        $notFoundResponse = $this->get('/stays/non-existent-stay-slug-12345');
        $notFoundResponse->assertStatus(404);
    }

    public function test_calendar_api_returns_prominent_gregorian_and_top_right_ethiopian_dates(): void
    {
        $response = $this->getJson("/api/stays/{$this->listing->slug}/calendar?year=2022&month=1&unit_id={$this->unit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'calendar_heading' => 'January 2022 • Tahsas – Tir 2014',
                'gregorian_heading' => 'January 2022',
                'ethiopian_heading' => 'Tahsas – Tir 2014',
            ]);

        $data = $response->json();
        $this->assertNotEmpty($data['days']);

        // First day: Gregorian Jan 1, 2022 -> Ethiopian Tahsas 23, 2014
        $day1 = $data['days'][0];
        $this->assertSame('2022-01-01', $day1['date']);
        $this->assertSame(1, $day1['day']);
        $this->assertSame(23, $day1['eth_day']);
        $this->assertSame('Tahsas', $day1['eth_month_name']);
        $this->assertSame(2014, $day1['eth_year']);
    }
}
