<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Calendar\Models\AvailabilityCalendar;
use App\Domains\Calendar\Models\SeasonalPrice;
use App\Domains\Host\Models\Host;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Location\Models\Location;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\AccommodationUnitType;
use App\Domains\Stay\Models\BedConfiguration;
use App\Domains\Stay\Models\HouseManual;
use App\Domains\Stay\Models\HouseRule;
use App\Domains\Stay\Models\LocalGuide;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class Phase2ShortStayDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure prerequisites (taxonomy, roles, locations)
        $this->call([
            RbacRolePermissionSeeder::class,
            PropertyTaxonomySeeder::class,
            EthiopianLocationsSeeder::class,
        ]);

        $resCat = PropertyCategory::query()->where('code', 'RES')->first() ?? PropertyCategory::query()->first();
        $villaType = PropertyType::query()->where('code', 'VILLA')->first() ?? PropertyType::query()->first();
        $aptType = PropertyType::query()->where('code', 'APT')->first() ?? PropertyType::query()->first();

        $addisCity = Location::query()->where('level', 'city')->where('name_en', 'Addis Ababa')->first();
        $boleSubcity = Location::query()->where('level', 'subcity')->where('name_en', 'Bole')->first();
        $kirkosSubcity = Location::query()->where('level', 'subcity')->where('name_en', 'Kirkos')->first();
        $yekaSubcity = Location::query()->where('level', 'subcity')->where('name_en', 'Yeka')->first();
        $nifasSubcity = Location::query()->where('level', 'subcity')->where('name_en', 'Nifas Silk-Lafto')->first();

        // 2. Setup Hosts
        $hostUser1 = User::query()->firstOrCreate(
            ['phone' => '+251911990011'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Alemayehu Tadesse',
                'email' => 'alemayehu.host@ethioproperty.et',
                'status' => 'active',
                'verification_level' => 3,
            ]
        );
        $hostUser1->assignRoleBySlug('host');

        $host1 = Host::query()->firstOrCreate(
            ['user_id' => $hostUser1->id],
            [
                'uuid' => (string) Str::uuid(),
                'display_name' => 'Alemayehu Tadesse',
                'host_type' => 'individual_owner',
                'is_superhost' => true,
                'payout_schedule' => 'after_checkin',
                'response_rate_pct' => 100,
                'avg_response_minutes' => 12,
                'average_rating' => 4.95,
                'reviews_count' => 38,
            ]
        );

        $hostUser2 = User::query()->firstOrCreate(
            ['phone' => '+251911990022'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Helen Mesfin',
                'email' => 'helen.host@ethioproperty.et',
                'status' => 'active',
                'verification_level' => 3,
            ]
        );
        $hostUser2->assignRoleBySlug('host');

        $host2 = Host::query()->firstOrCreate(
            ['user_id' => $hostUser2->id],
            [
                'uuid' => (string) Str::uuid(),
                'display_name' => 'Helen Mesfin',
                'host_type' => 'individual_owner',
                'is_superhost' => true,
                'payout_schedule' => 'after_checkin',
                'response_rate_pct' => 98,
                'avg_response_minutes' => 15,
                'average_rating' => 4.91,
                'reviews_count' => 24,
            ]
        );

        $listingType = ListingType::query()->where('transaction_mode', 'STAY')->first()
            ?? ListingType::query()->firstOrCreate(
                ['code' => 'SHORT_STAY'],
                [
                    'slug' => 'short-stay',
                    'name_en' => 'Short Stay',
                    'transaction_mode' => 'STAY',
                    'default_price_period' => 'per_night',
                    'is_phase1_enabled' => true,
                ]
            );

        // 3. Stay 1: Modern Garden Villa — Bole Medhanialem
        $prop1 = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-BOLE-STAY-0001'],
            [
                'uuid' => (string) Str::uuid(),
                'owner_id' => $hostUser1->id,
                'category_id' => $resCat->id,
                'property_type_id' => $villaType->id,
                'city_id' => $addisCity?->id,
                'subcity_id' => $boleSubcity?->id,
                'title_en' => 'Modern Garden Villa with Backup Generator — Bole Medhanialem',
                'title_am' => 'ዘመናዊ የቪላ መኖሪያ ከጀነሬተር ጋር — ቦሌ መድኃኔዓለም',
                'description_en' => 'High-end serviced villa featuring a standby 30kVA Perkins generator, 5G fiber internet, private landscaped garden, and secure parking in Bole Medhanialem.',
                'address_line' => 'Behind Medhanialem Cathedral, Bole, Addis Ababa',
                'bedrooms' => 3,
                'bathrooms' => 3,
                'building_area_sqm' => 350.00,
                'status' => PropertyStatus::Available->value,
                'verification_level' => 3,
            ]
        );

        $listing1 = Listing::query()->firstOrCreate(
            ['slug' => 'modern-garden-villa-bole'],
            [
                'uuid' => (string) Str::uuid(),
                'listing_code' => 'LST-2026-STAY-001',
                'property_id' => $prop1->id,
                'published_by_user_id' => $hostUser1->id,
                'listing_type_id' => $listingType->id,
                'transaction_mode' => 'STAY',
                'price_etb' => 8500.00,
                'currency' => 'ETB',
                'price_period' => 'per_night',
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        $profile1 = StayListingProfile::query()->firstOrCreate(
            ['listing_id' => $listing1->id],
            [
                'property_id' => $prop1->id,
                'host_id' => $host1->id,
                'accommodation_model' => 'entire_place',
                'base_nightly_rate_etb' => 8500.00,
                'weekend_nightly_rate_etb' => 9500.00,
                'weekly_discount_pct' => 10.00,
                'monthly_discount_pct' => 20.00,
                'cleaning_fee_etb' => 2000.00,
                'security_deposit_etb' => 5000.00,
                'guest_service_fee_pct' => 5.6179775,
                'tourism_tax_pct' => 15.00,
                'min_stay_nights' => 2,
                'max_stay_nights' => 90,
                'check_in_after_time' => '14:00',
                'check_out_before_time' => '11:00',
                'max_guests' => 6,
                'max_adults' => 4,
                'max_children' => 2,
                'instant_book_enabled' => true,
                'cancellation_policy' => 'flexible',
                'hold_duration_minutes' => 15,
                'allows_pets' => true,
            ]
        );

        $unitType1 = AccommodationUnitType::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile1->id],
            [
                'property_id' => $prop1->id,
                'name_en' => 'Entire Villa',
                'max_guests' => 6,
                'base_nightly_rate_etb' => 8500.00,
                'total_rooms_count' => 3,
            ]
        );

        $unit1 = AccommodationUnit::query()->firstOrCreate(
            ['unit_type_id' => $unitType1->id],
            [
                'property_id' => $prop1->id,
                'unit_number_or_code' => 'VILLA-1',
                'status' => 'ready',
            ]
        );

        // Bed Configurations
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile1->id, 'room_label_en' => 'Master Bedroom'],
            ['bed_type' => 'king', 'bed_count' => 1, 'sort_order' => 1]
        );
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile1->id, 'room_label_en' => 'Bedroom 2'],
            ['bed_type' => 'queen', 'bed_count' => 1, 'sort_order' => 2]
        );
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile1->id, 'room_label_en' => 'Bedroom 3'],
            ['bed_type' => 'single', 'bed_count' => 2, 'sort_order' => 3]
        );

        // House Rule & House Manual
        HouseRule::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile1->id],
            [
                'smoking_allowed' => false,
                'parties_allowed' => false,
                'children_allowed' => true,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'additional_rules_en' => 'Please turn off the AC when leaving. Shoes off in carpeted master bedroom.',
            ]
        );

        HouseManual::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile1->id],
            [
                'wifi_ssid' => 'Villa-Bole-Fiber-5G',
                'wifi_password_encrypted' => encrypt('Medhanialem#Safe2026'),
                'check_in_method' => 'smart_lock',
                'access_instructions_en' => 'Enter 8492# on the front keypad. The gate remote is inside on the console table.',
                'appliance_and_ac_guide_en' => '30kVA Perkins automatic diesel generator starts automatically within 12 seconds of grid outage.',
                'parking_instructions_en' => 'Gated driveway parking for up to 3 vehicles with 24/7 security guard.',
                'emergency_contact_phone' => '+251911990011',
            ]
        );

        // Seasonal Holiday Pricing
        SeasonalPrice::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile1->id, 'name' => 'Meskel Flower Festival Season'],
            [
                'rule_type' => 'custom_season',
                'start_date' => '2026-09-24',
                'end_date' => '2026-09-29',
                'rate_multiplier' => 1.25,
                'min_stay_nights' => 3,
                'is_active' => true,
            ]
        );
        SeasonalPrice::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile1->id, 'name' => 'Timket Epiphany Celebration (Jan)'],
            [
                'rule_type' => 'custom_season',
                'start_date' => '2027-01-17',
                'end_date' => '2027-01-22',
                'nightly_rate_etb' => 11000.00,
                'rate_multiplier' => 1.29,
                'min_stay_nights' => 5,
                'is_active' => true,
            ]
        );

        // 4. Stay 2: Kazanchis Diplomatic Luxury Penthouse
        $prop2 = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-KZ-STAY-0002'],
            [
                'uuid' => (string) Str::uuid(),
                'owner_id' => $hostUser2->id,
                'category_id' => $resCat->id,
                'property_type_id' => $aptType->id,
                'city_id' => $addisCity?->id,
                'subcity_id' => $kirkosSubcity?->id,
                'title_en' => 'Kazanchis Diplomatic Luxury Penthouse with City View',
                'title_am' => 'ካዛንቺስ ዲፕሎማቲክ ፔንትሃውስ ከከተማ እይታ ጋር',
                'description_en' => 'Top-floor serviced executive penthouse near ECA, featuring panoramic city skyline views, dedicated workspace, and round-the-clock building security.',
                'address_line' => 'Near UN-ECA HQ, Kazanchis, Kirkos, Addis Ababa',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'building_area_sqm' => 180.00,
                'status' => PropertyStatus::Available->value,
                'verification_level' => 3,
            ]
        );

        $listing2 = Listing::query()->firstOrCreate(
            ['slug' => 'kazanchis-diplomatic-luxury-penthouse'],
            [
                'uuid' => (string) Str::uuid(),
                'listing_code' => 'LST-2026-STAY-002',
                'property_id' => $prop2->id,
                'published_by_user_id' => $hostUser2->id,
                'listing_type_id' => $listingType->id,
                'transaction_mode' => 'STAY',
                'price_etb' => 6800.00,
                'currency' => 'ETB',
                'price_period' => 'per_night',
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        $profile2 = StayListingProfile::query()->firstOrCreate(
            ['listing_id' => $listing2->id],
            [
                'property_id' => $prop2->id,
                'host_id' => $host2->id,
                'accommodation_model' => 'entire_place',
                'base_nightly_rate_etb' => 6800.00,
                'weekend_nightly_rate_etb' => 7500.00,
                'weekly_discount_pct' => 12.00,
                'monthly_discount_pct' => 25.00,
                'cleaning_fee_etb' => 1500.00,
                'security_deposit_etb' => 4000.00,
                'guest_service_fee_pct' => 5.6179775,
                'tourism_tax_pct' => 15.00,
                'min_stay_nights' => 1,
                'max_stay_nights' => 60,
                'check_in_after_time' => '15:00',
                'check_out_before_time' => '12:00',
                'max_guests' => 4,
                'max_adults' => 3,
                'max_children' => 2,
                'instant_book_enabled' => true,
                'cancellation_policy' => 'moderate',
                'hold_duration_minutes' => 15,
                'allows_pets' => false,
            ]
        );

        $unitType2 = AccommodationUnitType::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile2->id],
            [
                'property_id' => $prop2->id,
                'name_en' => 'Executive Penthouse Suite',
                'max_guests' => 4,
                'base_nightly_rate_etb' => 6800.00,
                'total_rooms_count' => 2,
            ]
        );

        $unit2 = AccommodationUnit::query()->firstOrCreate(
            ['unit_type_id' => $unitType2->id],
            [
                'property_id' => $prop2->id,
                'unit_number_or_code' => 'PENTHOUSE-7A',
                'status' => 'ready',
            ]
        );

        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile2->id, 'room_label_en' => 'Master Suite'],
            ['bed_type' => 'king', 'bed_count' => 1, 'sort_order' => 1]
        );
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile2->id, 'room_label_en' => 'Guest Bedroom'],
            ['bed_type' => 'queen', 'bed_count' => 1, 'sort_order' => 2]
        );

        HouseRule::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile2->id],
            [
                'smoking_allowed' => false,
                'parties_allowed' => false,
                'children_allowed' => true,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'additional_rules_en' => 'Elevator key card provided at front desk. No loud music after 10 PM.',
            ]
        );

        HouseManual::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile2->id],
            [
                'wifi_ssid' => 'Kazanchis-Penthouse-5G',
                'wifi_password_encrypted' => encrypt('Diplomat#Addis2026'),
                'check_in_method' => 'smart_lock',
                'access_instructions_en' => 'Take elevator to 7th floor. Keypad is to the left of the double doors.',
                'emergency_contact_phone' => '+251911990022',
            ]
        );

        // 5. Stay 3: CMC Executive Serviced Residence
        $prop3 = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-CMC-STAY-0003'],
            [
                'uuid' => (string) Str::uuid(),
                'owner_id' => $hostUser1->id,
                'category_id' => $resCat->id,
                'property_type_id' => $aptType->id,
                'city_id' => $addisCity?->id,
                'subcity_id' => $yekaSubcity?->id,
                'title_en' => 'CMC Executive Serviced Residence with Private Terrace',
                'title_am' => 'ሲኤምሲ ዘመናዊ ባለ 3 መኝታ አፓርታማ ከቴራስ ጋር',
                'description_en' => 'Quiet, family-friendly serviced 3-bedroom apartment in CMC with dedicated backup water reservoir, solar water heating, and high-speed Wi-Fi.',
                'address_line' => 'CMC Michael Road, Yeka, Addis Ababa',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'building_area_sqm' => 195.00,
                'status' => PropertyStatus::Available->value,
                'verification_level' => 3,
            ]
        );

        $listing3 = Listing::query()->firstOrCreate(
            ['slug' => 'cmc-executive-serviced-residence'],
            [
                'uuid' => (string) Str::uuid(),
                'listing_code' => 'LST-2026-STAY-003',
                'property_id' => $prop3->id,
                'published_by_user_id' => $hostUser1->id,
                'listing_type_id' => $listingType->id,
                'transaction_mode' => 'STAY',
                'price_etb' => 5400.00,
                'currency' => 'ETB',
                'price_period' => 'per_night',
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        $profile3 = StayListingProfile::query()->firstOrCreate(
            ['listing_id' => $listing3->id],
            [
                'property_id' => $prop3->id,
                'host_id' => $host1->id,
                'accommodation_model' => 'entire_place',
                'base_nightly_rate_etb' => 5400.00,
                'weekend_nightly_rate_etb' => 6000.00,
                'weekly_discount_pct' => 15.00,
                'monthly_discount_pct' => 30.00,
                'cleaning_fee_etb' => 1200.00,
                'security_deposit_etb' => 3000.00,
                'guest_service_fee_pct' => 5.6179775,
                'tourism_tax_pct' => 15.00,
                'min_stay_nights' => 2,
                'max_stay_nights' => 90,
                'check_in_after_time' => '14:00',
                'check_out_before_time' => '11:00',
                'max_guests' => 5,
                'max_adults' => 4,
                'max_children' => 2,
                'instant_book_enabled' => true,
                'cancellation_policy' => 'flexible',
                'hold_duration_minutes' => 15,
                'allows_pets' => true,
            ]
        );

        $unitType3 = AccommodationUnitType::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile3->id],
            [
                'property_id' => $prop3->id,
                'name_en' => 'Entire 3-Bedroom Flat',
                'max_guests' => 5,
                'base_nightly_rate_etb' => 5400.00,
                'total_rooms_count' => 3,
            ]
        );

        $unit3 = AccommodationUnit::query()->firstOrCreate(
            ['unit_type_id' => $unitType3->id],
            [
                'property_id' => $prop3->id,
                'unit_number_or_code' => 'CMC-RES-03',
                'status' => 'ready',
            ]
        );

        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile3->id, 'room_label_en' => 'Bedroom 1'],
            ['bed_type' => 'king', 'bed_count' => 1, 'sort_order' => 1]
        );
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile3->id, 'room_label_en' => 'Bedroom 2'],
            ['bed_type' => 'queen', 'bed_count' => 1, 'sort_order' => 2]
        );
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile3->id, 'room_label_en' => 'Bedroom 3'],
            ['bed_type' => 'single', 'bed_count' => 1, 'sort_order' => 3]
        );

        HouseRule::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile3->id],
            [
                'smoking_allowed' => false,
                'parties_allowed' => false,
                'children_allowed' => true,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
            ]
        );

        HouseManual::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile3->id],
            [
                'wifi_ssid' => 'CMC-Fiber-Res',
                'wifi_password_encrypted' => encrypt('SafeHomeCMC2026'),
                'check_in_method' => 'host_greets',
                'access_instructions_en' => 'Security gate guard will direct you to Block B, Unit 3.',
                'emergency_contact_phone' => '+251911990011',
            ]
        );

        // 6. Stay 4: Old Airport Diplomatic Garden Villa
        $prop4 = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-OLD-STAY-0004'],
            [
                'uuid' => (string) Str::uuid(),
                'owner_id' => $hostUser2->id,
                'category_id' => $resCat->id,
                'property_type_id' => $villaType->id,
                'city_id' => $addisCity?->id,
                'subcity_id' => $nifasSubcity?->id,
                'title_en' => 'Old Airport Diplomatic Garden Villa with Solar Backup',
                'title_am' => 'ኦልድ ኤርፖርት ዲፕሎማቲክ ቪላ ከመናፈሻና ሶላር ጋር',
                'description_en' => 'Spacious 4-bedroom diplomatic residence in Old Airport with landscaped private gardens, solar hybrid system, staff quarters, and high perimeter security.',
                'address_line' => 'Near International Community School (ICS), Old Airport, Addis Ababa',
                'bedrooms' => 4,
                'bathrooms' => 4,
                'building_area_sqm' => 420.00,
                'status' => PropertyStatus::Available->value,
                'verification_level' => 3,
            ]
        );

        $listing4 = Listing::query()->firstOrCreate(
            ['slug' => 'old-airport-diplomatic-garden-villa'],
            [
                'uuid' => (string) Str::uuid(),
                'listing_code' => 'LST-2026-STAY-004',
                'property_id' => $prop4->id,
                'published_by_user_id' => $hostUser2->id,
                'listing_type_id' => $listingType->id,
                'transaction_mode' => 'STAY',
                'price_etb' => 12000.00,
                'currency' => 'ETB',
                'price_period' => 'per_night',
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        $profile4 = StayListingProfile::query()->firstOrCreate(
            ['listing_id' => $listing4->id],
            [
                'property_id' => $prop4->id,
                'host_id' => $host2->id,
                'accommodation_model' => 'entire_place',
                'base_nightly_rate_etb' => 12000.00,
                'weekend_nightly_rate_etb' => 13500.00,
                'weekly_discount_pct' => 10.00,
                'monthly_discount_pct' => 20.00,
                'cleaning_fee_etb' => 2500.00,
                'security_deposit_etb' => 8000.00,
                'guest_service_fee_pct' => 5.6179775,
                'tourism_tax_pct' => 15.00,
                'min_stay_nights' => 3,
                'max_stay_nights' => 90,
                'check_in_after_time' => '14:00',
                'check_out_before_time' => '11:00',
                'max_guests' => 8,
                'max_adults' => 6,
                'max_children' => 4,
                'instant_book_enabled' => true,
                'cancellation_policy' => 'firm',
                'hold_duration_minutes' => 15,
                'allows_pets' => true,
            ]
        );

        $unitType4 = AccommodationUnitType::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile4->id],
            [
                'property_id' => $prop4->id,
                'name_en' => 'Diplomatic Villa',
                'max_guests' => 8,
                'base_nightly_rate_etb' => 12000.00,
                'total_rooms_count' => 4,
            ]
        );

        $unit4 = AccommodationUnit::query()->firstOrCreate(
            ['unit_type_id' => $unitType4->id],
            [
                'property_id' => $prop4->id,
                'unit_number_or_code' => 'OLD-VILLA-A',
                'status' => 'ready',
            ]
        );

        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile4->id, 'room_label_en' => 'Master Suite 1'],
            ['bed_type' => 'king', 'bed_count' => 1, 'sort_order' => 1]
        );
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile4->id, 'room_label_en' => 'Master Suite 2'],
            ['bed_type' => 'king', 'bed_count' => 1, 'sort_order' => 2]
        );
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile4->id, 'room_label_en' => 'Bedroom 3'],
            ['bed_type' => 'single', 'bed_count' => 2, 'sort_order' => 3]
        );
        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile4->id, 'room_label_en' => 'Bedroom 4'],
            ['bed_type' => 'single', 'bed_count' => 2, 'sort_order' => 4]
        );

        HouseRule::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile4->id],
            [
                'smoking_allowed' => false,
                'parties_allowed' => false,
                'children_allowed' => true,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
            ]
        );

        HouseManual::query()->firstOrCreate(
            ['stay_listing_profile_id' => $profile4->id],
            [
                'wifi_ssid' => 'OldAirport-SolarVilla',
                'wifi_password_encrypted' => encrypt('OldAirport2026!'),
                'check_in_method' => 'smart_lock',
                'emergency_contact_phone' => '+251911990022',
            ]
        );

        // Seed some sample future booked days for Unit 4 to demonstrate date filtering
        $nextMonth = CarbonImmutable::today()->addDays(15);
        AvailabilityCalendar::query()->firstOrCreate(
            ['accommodation_unit_id' => $unit4->id, 'calendar_date' => $nextMonth->toDateString()],
            ['status' => 'booked', 'custom_nightly_rate_etb' => 12000.00]
        );
        AvailabilityCalendar::query()->firstOrCreate(
            ['accommodation_unit_id' => $unit4->id, 'calendar_date' => $nextMonth->addDay()->toDateString()],
            ['status' => 'booked', 'custom_nightly_rate_etb' => 12000.00]
        );
    }
}
