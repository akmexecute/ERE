<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Booking\Actions\ExpireUnpaidBookingHoldsAction;
use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingPriceItem;
use App\Domains\Calendar\Models\AvailabilityCalendar;
use App\Domains\Host\Models\Host;
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
        $response->assertSee('Hold Dates');
    }
}
