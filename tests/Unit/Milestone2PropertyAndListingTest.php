<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Listing\Actions\CalculateListingQualityScoreAction;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyFeature;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Property\Services\ImageSanitizerAndHasherService;
use App\Support\Money\EtbFormatter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Milestone2PropertyAndListingTest extends TestCase
{
    public function test_calculates_srs_section_165_listing_quality_score_and_identifies_missing_fields_accurately(): void
    {
        $propertyType = new PropertyType(['area_basis' => 'land_area']);
        $property = new Property([
            'city_id' => 2,
            'subcity_id' => 3,
            'latitude' => 8.9894,
            'longitude' => 38.7890,
            'land_area_sqm' => 300,
            'building_area_sqm' => 240,
            'description_en' => str_repeat('Modern G+2 villa in Bole with backup generator, reserve water tank, and paved road access. ', 2),
        ]);
        $property->setRelation('propertyType', $propertyType);

        $scorer = new CalculateListingQualityScoreAction();

        $result = $scorer->execute(
            property: $property,
            priceEtb: 25_000_000,
            photoCount: 6,
            uploadedRoomTags: ['exterior', 'living_room', 'bedroom', 'kitchen'],
            amenityCount: 4,
            documentCount: 0
        );

        // 25 (photos + tags) + 20 (desc) + 15 (location) + 15 (price/area) + 15 (amenities) + 0 (docs) = 90%
        $this->assertSame(90, $result['score']);
        $this->assertTrue($result['breakdown']['photos']);
        $this->assertTrue($result['breakdown']['location']);
        $this->assertFalse($result['breakdown']['documents']);
        $this->assertCount(1, $result['missing_fields']);
    }

    public function test_quality_scorer_requires_both_latitude_and_longitude_for_location_points(): void
    {
        $propertyType = new PropertyType(['area_basis' => 'building_area']);
        $property = new Property([
            'city_id' => 2,
            'subcity_id' => 3,
            'latitude' => 8.9894,
            'longitude' => null, // Longitude missing!
            'building_area_sqm' => 120,
            'description_en' => str_repeat('Spacious apartment in Kazanchis with high quality finishes. ', 3),
        ]);
        $property->setRelation('propertyType', $propertyType);

        $scorer = new CalculateListingQualityScoreAction();
        $result = $scorer->execute(
            property: $property,
            priceEtb: 10_000_000,
            photoCount: 5,
            uploadedRoomTags: ['exterior', 'living_room', 'bedroom'],
            amenityCount: 3,
            documentCount: 1
        );

        $this->assertFalse($result['breakdown']['location']);
        $this->assertContains('Pin exact map coordinates (latitude and longitude) and select Sub-city / Neighborhood.', $result['missing_fields']);
    }

    public function test_computes_accurate_price_per_square_meter_for_both_land_basis_villas_and_building_basis_apartments(): void
    {
        // SRS Section 36 Example: 20,000,000 ETB / 300 m² = 66,666.67 ETB/m²
        $this->assertSame(66666.67, EtbFormatter::calculatePricePerSqm(20_000_000, 300));
        $this->assertSame(80000.00, EtbFormatter::calculatePricePerSqm(12_000_000, 150));
        $this->assertNull(EtbFormatter::calculatePricePerSqm(20_000_000, 0));
        $this->assertNull(EtbFormatter::calculatePricePerSqm(20_000_000, null));
    }

    public function test_perceptual_hasher_throws_exception_on_corrupt_or_missing_image(): void
    {
        $service = new ImageSanitizerAndHasherService();

        $this->expectException(RuntimeException::class);
        $service->computePerceptualHash(sys_get_temp_dir() . '/non_existent_image_file.webp');
    }

    public function test_localized_name_fallback_resolves_amharic_oromo_and_english(): void
    {
        $category = new PropertyCategory([
            'code' => 'RES',
            'name_en' => 'Residential',
            'name_am' => 'መኖሪያ ቤት',
            'name_om' => 'Mana Jireenyaa',
        ]);

        $this->assertSame('መኖሪያ ቤት', $category->localizedName('am'));
        $this->assertSame('Mana Jireenyaa', $category->localizedName('om'));
        $this->assertSame('Residential', $category->localizedName('en'));
        $this->assertSame('Residential', $category->localizedName('fr')); // Fallback to en

        $feature = new PropertyFeature([
            'slug' => 'road_width',
            'name_en' => 'Road Width',
            'name_am' => 'የመንገድ ስፋት',
            'name_om' => null, // null om should fallback to name_en
        ]);

        $this->assertSame('የመንገድ ስፋት', $feature->localizedName('am'));
        $this->assertSame('Road Width', $feature->localizedName('om'));
    }
}
