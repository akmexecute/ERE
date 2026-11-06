<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\BuildingFloor;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\ProjectBuilding;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Host\Models\Host;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Location\Models\Location;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyImage;
use App\Domains\Property\Models\PropertyType;
use App\Domains\VirtualTour\Models\PropertyTourHotspot;
use App\Domains\VirtualTour\Models\PropertyTourScene;
use App\Domains\VirtualTour\Models\PropertyVirtualTour;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\AccommodationUnitType;
use App\Domains\Stay\Models\BedConfiguration;
use App\Domains\Stay\Models\HouseRule;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class Phase5Milestone2LiveDemonstrationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production', 'prod')) {
            throw new \RuntimeException('Phase5Milestone2LiveDemonstrationSeeder cannot be executed in production.');
        }

        // 1. Ensure Demo User & Host
        $user = User::query()->firstOrCreate(
            ['phone' => '+251911234567'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Abebe Bikila',
                'email' => 'admin@ethioproperty.et',
                'verification_level' => 3,
                'status' => 'active',
            ]
        );

        $host = Host::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'uuid' => (string) Str::uuid(),
                'host_type' => 'individual_owner',
                'display_name' => 'Bole Premium Properties',
                'response_rate_pct' => 100,
                'avg_response_minutes' => 10,
                'average_rating' => 4.95,
                'reviews_count' => 14,
                'payout_schedule' => 'after_checkin',
                'is_superhost' => true,
                'status' => 'active',
            ]
        );

        // Reference taxonomy models
        $resCat = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $comCat = PropertyCategory::query()->where('code', 'COM')->firstOrFail();
        $lndCat = PropertyCategory::query()->where('code', 'LND')->firstOrFail();

        $aptType = PropertyType::query()->where('code', 'APT')->firstOrFail();
        $officeType = PropertyType::query()->where('code', 'OFFICE')->firstOrFail();
        $landType = PropertyType::query()->where('code', 'RES_LAND')->firstOrFail();
        $studioType = PropertyType::query()->where('code', 'STUDIO')->first() ?? $aptType;

        $forSaleType = ListingType::query()->where('code', 'FOR_SALE')->firstOrFail();
        $forRentType = ListingType::query()->where('code', 'FOR_RENT')->firstOrFail();
        $forLeaseType = ListingType::query()->where('code', 'FOR_LEASE')->firstOrFail();
        $shortStayType = ListingType::query()->where('code', 'SHORT_STAY')->firstOrFail();

        // Reference location models
        $addisCity = Location::query()->where('level', 'city')->where('name_en', 'Addis Ababa')->firstOrFail();
        $boleSubcity = Location::query()->where('level', 'subcity')->where('name_en', 'Bole')->firstOrFail();
        $kirkosSubcity = Location::query()->where('level', 'subcity')->where('name_en', 'Kirkos')->first() ?? $boleSubcity;
        $kazanchisNeighborhood = Location::query()->where('level', 'neighborhood')->where('name_en', 'Kazanchis')->first();
        $summitNeighborhood = Location::query()->where('level', 'neighborhood')->where('name_en', 'Summit')->first();

        // -------------------------------------------------------------
        // Record 1: BUY — Modern 2-Bedroom Apartment for Sale in Bole
        // Destination: /search?mode=BUY
        // -------------------------------------------------------------
        $buyProp = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-BOLE-RES-00002001'],
            [
                'uuid' => '11111111-2222-3333-4444-555555550001',
                'owner_id' => $user->id,
                'category_id' => $resCat->id,
                'property_type_id' => $aptType->id,
                'city_id' => $addisCity->id,
                'subcity_id' => $boleSubcity->id,
                'title_en' => 'Modern 2-Bedroom Apartment for Sale in Bole',
                'title_am' => 'ዘመናዊ ባለ 2 መኝታ አፓርታማ በቦሌ ለሽያጭ',
                'description_en' => 'Bright and modern 2-bedroom luxury apartment situated in prime Bole. Features modern granite countertops, master en-suite, standby backup generator, elevator access, and secure basement parking.',
                'building_area_sqm' => 120.00,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'floor_level' => 4,
                'floors' => 8,
                'parking_spaces' => 1,
                'has_generator' => true,
                'has_security' => true,
                'status' => PropertyStatus::Available,
                'verification_level' => 3,
                'ownership_status' => 'verified',
                'latitude' => 9.0012,
                'longitude' => 38.7845,
            ]
        );

        $buyListing = Listing::query()->firstOrCreate(
            ['slug' => 'modern-2-bedroom-apartment-for-sale-in-bole'],
            [
                'uuid' => '11111111-2222-3333-4444-666666660001',
                'listing_code' => 'LST-ETH-2026-BUY-001',
                'property_id' => $buyProp->id,
                'published_by_user_id' => $user->id,
                'listing_type_id' => $forSaleType->id,
                'transaction_mode' => 'BUY',
                'price_etb' => 8500000.00,
                'currency' => 'ETB',
                'price_period' => 'total',
                'price_per_sqm_etb' => 70833.33,
                'is_negotiable' => true,
                'quality_score' => 95,
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        PropertyImage::query()->firstOrCreate(
            ['property_id' => $buyProp->id, 'sort_order' => 1],
            [
                'disk' => 'public',
                'original_sanitized_path' => 'properties/1/04d1b7d1-6cc5-4219-9808-16dcfcdf0f97_1.webp',
                'webp_medium_path' => 'properties/1/04d1b7d1-6cc5-4219-9808-16dcfcdf0f97_1.webp',
                'caption' => 'Living Room & Dining',
                'is_cover' => true,
                'exif_stripped' => true,
                'watermarked' => true,
            ]
        );

        // -------------------------------------------------------------
        // Record 2: RENT — Furnished 2-Bedroom Apartment for Rent in Kazanchis
        // Destination: /search?mode=RENT
        // -------------------------------------------------------------
        $rentProp = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-KIRKOS-RES-00002002'],
            [
                'uuid' => '11111111-2222-3333-4444-555555550002',
                'owner_id' => $user->id,
                'category_id' => $resCat->id,
                'property_type_id' => $aptType->id,
                'city_id' => $addisCity->id,
                'subcity_id' => $kirkosSubcity->id,
                'neighborhood_id' => $kazanchisNeighborhood?->id,
                'title_en' => 'Furnished 2-Bedroom Apartment for Rent in Kazanchis',
                'title_am' => 'ባለ 2 መኝታ የተሟላለት አፓርታማ በካዛንቺስ ለኪራይ',
                'description_en' => 'Fully furnished executive 2-bedroom residence within walking distance to UNECA and international offices in Kazanchis. High-speed fiber internet, 24/7 security, backup water tank, and monthly cleaning included.',
                'building_area_sqm' => 110.00,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'floor_level' => 3,
                'floors' => 6,
                'parking_spaces' => 1,
                'has_generator' => true,
                'has_security' => true,
                'status' => PropertyStatus::Available,
                'verification_level' => 3,
                'ownership_status' => 'verified',
                'latitude' => 9.0180,
                'longitude' => 38.7690,
            ]
        );

        $rentListing = Listing::query()->firstOrCreate(
            ['slug' => 'furnished-2-bedroom-apartment-for-rent-in-kazanchis'],
            [
                'uuid' => '11111111-2222-3333-4444-666666660002',
                'listing_code' => 'LST-ETH-2026-RENT-002',
                'property_id' => $rentProp->id,
                'published_by_user_id' => $user->id,
                'listing_type_id' => $forRentType->id,
                'transaction_mode' => 'RENT',
                'price_etb' => 35000.00,
                'currency' => 'ETB',
                'price_period' => 'per_month',
                'price_per_sqm_etb' => 318.18,
                'is_negotiable' => false,
                'quality_score' => 94,
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        PropertyImage::query()->firstOrCreate(
            ['property_id' => $rentProp->id, 'sort_order' => 1],
            [
                'disk' => 'public',
                'original_sanitized_path' => 'properties/1/09a91c55-0490-4b6a-bb55-fabe916ac684_1.webp',
                'webp_medium_path' => 'properties/1/09a91c55-0490-4b6a-bb55-fabe916ac684_1.webp',
                'caption' => 'Master Bedroom & Balcony',
                'is_cover' => true,
                'exif_stripped' => true,
                'watermarked' => true,
            ]
        );

        // -------------------------------------------------------------
        // Record 3: LEASE — Modern Commercial Office Space for Lease in Bole
        // Destination: /search?mode=LEASE
        // -------------------------------------------------------------
        $leaseProp = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-BOLE-COM-00002003'],
            [
                'uuid' => '11111111-2222-3333-4444-555555550003',
                'owner_id' => $user->id,
                'category_id' => $comCat->id,
                'property_type_id' => $officeType->id,
                'city_id' => $addisCity->id,
                'subcity_id' => $boleSubcity->id,
                'title_en' => 'Modern Commercial Office Space for Lease in Bole',
                'title_am' => 'ዘመናዊ የንግድ ቢሮ ቦታ በቦሌ ለሊዝ',
                'description_en' => 'Premium 250 m² open-plan commercial office floor in central Bole business avenue. Equipped with dedicated 3-phase electricity, dual backup generators, server room with AC, and 4 dedicated parking slots.',
                'building_area_sqm' => 250.00,
                'bedrooms' => 0,
                'bathrooms' => 2,
                'total_rooms' => 6,
                'floor_level' => 2,
                'floors' => 10,
                'parking_spaces' => 4,
                'has_generator' => true,
                'has_security' => true,
                'status' => PropertyStatus::Available,
                'verification_level' => 3,
                'ownership_status' => 'verified',
                'latitude' => 9.0065,
                'longitude' => 38.7890,
            ]
        );

        $leaseListing = Listing::query()->firstOrCreate(
            ['slug' => 'modern-commercial-office-space-for-lease-in-bole'],
            [
                'uuid' => '11111111-2222-3333-4444-666666660003',
                'listing_code' => 'LST-ETH-2026-LEASE-003',
                'property_id' => $leaseProp->id,
                'published_by_user_id' => $user->id,
                'listing_type_id' => $forLeaseType->id,
                'transaction_mode' => 'LEASE',
                'price_etb' => 180000.00,
                'currency' => 'ETB',
                'price_period' => 'per_month',
                'price_per_sqm_etb' => 720.00,
                'is_negotiable' => true,
                'quality_score' => 96,
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        PropertyImage::query()->firstOrCreate(
            ['property_id' => $leaseProp->id, 'sort_order' => 1],
            [
                'disk' => 'public',
                'original_sanitized_path' => 'properties/1/0c9fef3a-e302-4c10-9994-a7241a921f75_1.webp',
                'webp_medium_path' => 'properties/1/0c9fef3a-e302-4c10-9994-a7241a921f75_1.webp',
                'caption' => 'Commercial Open-Plan Office Floor',
                'is_cover' => true,
                'exif_stripped' => true,
                'watermarked' => true,
            ]
        );

        // -------------------------------------------------------------
        // Record 4: LAND — Residential Land Plot for Sale in Summit
        // Destination: /search?category=land
        // -------------------------------------------------------------
        $landProp = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-SUMMIT-LND-00002004'],
            [
                'uuid' => '11111111-2222-3333-4444-555555550004',
                'owner_id' => $user->id,
                'category_id' => $lndCat->id,
                'property_type_id' => $landType->id,
                'city_id' => $addisCity->id,
                'subcity_id' => $boleSubcity->id,
                'neighborhood_id' => $summitNeighborhood?->id,
                'title_en' => 'Residential Land Plot for Sale in Summit',
                'title_am' => 'ለመኖሪያ የሚሆን የይዞታ ቦታ በሰሚት ለሽያጭ',
                'description_en' => 'Prime 300 m² residential corner land plot located in Summit residential enclave. Clear digital title deed with verified demarcation, 12m wide asphalt access, electricity, and municipal water line available.',
                'land_area_sqm' => 300.00,
                'building_area_sqm' => 0.00,
                'bedrooms' => 0,
                'bathrooms' => 0,
                'has_generator' => false,
                'has_security' => true,
                'status' => PropertyStatus::Available,
                'verification_level' => 3,
                'ownership_status' => 'verified',
                'latitude' => 9.0225,
                'longitude' => 38.8650,
            ]
        );

        $landListing = Listing::query()->firstOrCreate(
            ['slug' => 'residential-land-plot-for-sale-in-summit'],
            [
                'uuid' => '11111111-2222-3333-4444-666666660004',
                'listing_code' => 'LST-ETH-2026-LAND-004',
                'property_id' => $landProp->id,
                'published_by_user_id' => $user->id,
                'listing_type_id' => $forSaleType->id,
                'transaction_mode' => 'BUY',
                'price_etb' => 6500000.00,
                'currency' => 'ETB',
                'price_period' => 'total',
                'price_per_sqm_etb' => 21666.67,
                'is_negotiable' => true,
                'quality_score' => 92,
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        PropertyImage::query()->firstOrCreate(
            ['property_id' => $landProp->id, 'sort_order' => 1],
            [
                'disk' => 'public',
                'original_sanitized_path' => 'properties/1/0eac3e6f-bf40-4384-9f5a-1c8a9ed31547_1.webp',
                'webp_medium_path' => 'properties/1/0eac3e6f-bf40-4384-9f5a-1c8a9ed31547_1.webp',
                'caption' => 'Summit Corner Plot Survey',
                'is_cover' => true,
                'exif_stripped' => true,
                'watermarked' => true,
            ]
        );

        // -------------------------------------------------------------
        // Record 5: STAYS — Furnished Short-Stay Studio Apartment in Bole
        // Destination: /stays
        // -------------------------------------------------------------
        $stayProp = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-BOLE-STAY-00002005'],
            [
                'uuid' => '11111111-2222-3333-4444-555555550005',
                'owner_id' => $user->id,
                'category_id' => $resCat->id,
                'property_type_id' => $studioType->id,
                'city_id' => $addisCity->id,
                'subcity_id' => $boleSubcity->id,
                'title_en' => 'Furnished Short-Stay Studio Apartment in Bole',
                'title_am' => 'ለአጭር ጊዜ ቆይታ የተዘጋጀ ዘመናዊ ስቱዲዮ አፓርታማ በቦሌ',
                'description_en' => 'Charming and fully equipped studio apartment in central Bole near Atlas and Edna Mall. Ideal for business travelers and tourists. Includes kitchenette, high-speed WiFi, smart TV, hot water, and keyless entry.',
                'building_area_sqm' => 55.00,
                'bedrooms' => 1,
                'bathrooms' => 1,
                'floor_level' => 2,
                'floors' => 5,
                'parking_spaces' => 1,
                'has_generator' => true,
                'has_security' => true,
                'status' => PropertyStatus::Available,
                'verification_level' => 3,
                'ownership_status' => 'verified',
                'latitude' => 9.0090,
                'longitude' => 38.7810,
            ]
        );

        $stayListing = Listing::query()->firstOrCreate(
            ['slug' => 'furnished-short-stay-studio-apartment-in-bole'],
            [
                'uuid' => '11111111-2222-3333-4444-666666660005',
                'listing_code' => 'LST-ETH-2026-STAY-005',
                'property_id' => $stayProp->id,
                'published_by_user_id' => $user->id,
                'listing_type_id' => $shortStayType->id,
                'transaction_mode' => 'STAY',
                'price_etb' => 2500.00,
                'currency' => 'ETB',
                'price_period' => 'per_night',
                'price_per_sqm_etb' => 45.45,
                'is_negotiable' => false,
                'quality_score' => 98,
                'status' => ListingStatus::Published,
                'published_at' => now(),
            ]
        );

        $stayProfile = StayListingProfile::query()->firstOrCreate(
            ['listing_id' => $stayListing->id],
            [
                'property_id' => $stayProp->id,
                'host_id' => $host->id,
                'accommodation_model' => 'entire_place',
                'base_nightly_rate_etb' => 2500.00,
                'weekend_nightly_rate_etb' => 2800.00,
                'cleaning_fee_etb' => 500.00,
                'security_deposit_etb' => 1500.00,
                'guest_service_fee_pct' => 5.0,
                'tourism_tax_pct' => 15.00,
                'min_stay_nights' => 1,
                'max_stay_nights' => 30,
                'check_in_after_time' => '14:00',
                'check_out_before_time' => '11:00',
                'max_guests' => 2,
                'max_adults' => 2,
                'max_children' => 0,
                'instant_book_enabled' => true,
                'cancellation_policy' => 'flexible',
                'hold_duration_minutes' => 15,
                'allows_pets' => false,
            ]
        );

        $unitType = AccommodationUnitType::query()->firstOrCreate(
            ['stay_listing_profile_id' => $stayProfile->id],
            [
                'property_id' => $stayProp->id,
                'name_en' => 'Studio Suite',
                'max_guests' => 2,
                'base_nightly_rate_etb' => 2500.00,
                'total_rooms_count' => 1,
            ]
        );

        AccommodationUnit::query()->firstOrCreate(
            ['unit_type_id' => $unitType->id],
            [
                'property_id' => $stayProp->id,
                'unit_number_or_code' => 'STUDIO-101',
                'status' => 'ready',
            ]
        );

        BedConfiguration::query()->firstOrCreate(
            ['stay_listing_profile_id' => $stayProfile->id, 'room_label_en' => 'Studio Bedroom Area'],
            ['bed_type' => 'queen', 'bed_count' => 1, 'sort_order' => 1]
        );

        HouseRule::query()->firstOrCreate(
            ['stay_listing_profile_id' => $stayProfile->id],
            [
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'smoking_allowed' => false,
                'events_allowed' => false,
                'additional_rules_en' => 'No commercial photography without prior host approval.',
            ]
        );

        PropertyImage::query()->firstOrCreate(
            ['property_id' => $stayProp->id, 'sort_order' => 1],
            [
                'disk' => 'public',
                'original_sanitized_path' => 'properties/1/14fe83aa-4748-49d3-a8bf-45c735426922_1.webp',
                'webp_medium_path' => 'properties/1/14fe83aa-4748-49d3-a8bf-45c735426922_1.webp',
                'caption' => 'Studio Queen Bed & Lounge Area',
                'is_cover' => true,
                'exif_stripped' => true,
                'watermarked' => true,
            ]
        );

        // -------------------------------------------------------------
        // Record 6: DEVELOPMENTS — Bole Tower Residences Available Unit
        // Destination: /projects/bole-tower-residences
        // -------------------------------------------------------------
        $project = DevelopmentProject::query()->where('slug', 'bole-tower-residences')->first();
        if ($project) {
            $buildingB = ProjectBuilding::query()->where('project_id', $project->id)
                ->where('building_code', 'TOWER-B')
                ->first();

            if (! $buildingB) {
                $buildingB = ProjectBuilding::create([
                    'project_id' => $project->id,
                    'building_code' => 'TOWER-B',
                    'name_en' => 'Tower B (Horizon East)',
                    'floors_count' => 8,
                    'basement_floors_count' => 2,
                ]);
            }

            $floor1B = BuildingFloor::query()->firstOrCreate(
                ['building_id' => $buildingB->id, 'floor_number' => 1],
                [
                    'project_id' => $project->id,
                    'floor_label' => 'Floor 1',
                    'units_count' => 4,
                ]
            );

            ProjectUnit::query()->updateOrCreate(
                ['building_id' => $buildingB->id, 'unit_number' => 'B-102'],
                [
                    'uuid' => '11111111-2222-3333-4444-777777770006',
                    'project_id' => $project->id,
                    'floor_id' => $floor1B->id,
                    'floor_number' => 1,
                    'unit_type' => '2br',
                    'area_sqm' => 115.00,
                    'bedrooms' => 2,
                    'bathrooms' => 2,
                    'price_etb' => 9200000.00,
                    'price_per_sqm_etb' => 80000.00,
                    'orientation' => 'south_west',
                    'parking_allocation' => 1,
                    'status' => UnitInventoryStatus::Available,
                ]
            );
        }

        // -------------------------------------------------------------
        // 360° Virtual Tour for Demo Buy Property (Module 34)
        // -------------------------------------------------------------
        $tour = PropertyVirtualTour::query()->firstOrCreate(
            ['uuid' => 'tour-live-demo-uuid-002'],
            [
                'property_id' => $buyProp->id,
                'created_by_user_id' => $user->id,
                'title' => 'Bole Modern 2-Bedroom 360° Virtual Walkthrough',
                'description' => 'Interactive 360 panorama tour through the modern living room and master en-suite.',
                'tour_type' => 'equirectangular_panorama',
                'is_published' => true,
                'published_at' => now(),
                'views_count' => 42,
            ]
        );

        $scene1 = PropertyTourScene::query()->firstOrCreate(
            ['tour_id' => $tour->id, 'room_name' => 'Living Room'],
            [
                'panorama_image_path' => 'properties/1/04d1b7d1-6cc5-4219-9808-16dcfcdf0f97_1.webp',
                'sort_order' => 1,
                'initial_pitch' => 0.0,
                'initial_yaw' => 0.0,
                'initial_zoom' => 100.0,
                'is_default_entry' => true,
            ]
        );

        $scene2 = PropertyTourScene::query()->firstOrCreate(
            ['tour_id' => $tour->id, 'room_name' => 'Master Bedroom'],
            [
                'panorama_image_path' => 'properties/1/09a91c55-0490-4b6a-bb55-fabe916ac684_1.webp',
                'sort_order' => 2,
                'initial_pitch' => -5.0,
                'initial_yaw' => 45.0,
                'initial_zoom' => 100.0,
                'is_default_entry' => false,
            ]
        );

        // Hotspot connecting Living Room to Master Bedroom
        PropertyTourHotspot::query()->firstOrCreate(
            ['scene_id' => $scene1->id, 'target_scene_id' => $scene2->id],
            [
                'title' => 'Walk into Master Bedroom',
                'pitch' => 5.0,
                'yaw' => 60.0,
                'icon' => 'arrow-forward',
            ]
        );

        // Hotspot connecting Master Bedroom back to Living Room
        PropertyTourHotspot::query()->firstOrCreate(
            ['scene_id' => $scene2->id, 'target_scene_id' => $scene1->id],
            [
                'title' => 'Return to Living Room',
                'pitch' => -2.0,
                'yaw' => -120.0,
                'icon' => 'arrow-back',
            ]
        );
    }
}
