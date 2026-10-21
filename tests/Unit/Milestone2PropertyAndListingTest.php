<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Listing\Actions\CalculateListingQualityScoreAction;
use App\Domains\Property\Models\Property;
use App\Support\Money\EtbFormatter;
use PHPUnit\Framework\TestCase;

final class Milestone2PropertyAndListingTest extends TestCase
{
    public function test_calculates_srs_section_165_listing_quality_score_and_identifies_missing_fields_accurately(): void
    {
        $property = new Property([
            'city_id' => 2,
            'subcity_id' => 3,
            'latitude' => 8.9894,
            'longitude' => 38.7890,
            'land_area_sqm' => 300,
            'building_area_sqm' => 240,
            'description_en' => str_repeat('Modern G+2 villa in Bole with backup generator, reserve water tank, and paved road access. ', 2),
        ]);

        $scorer = new CalculateListingQualityScoreAction();

        $result = $scorer->execute(
            property: $property,
            priceEtb: 25_000_000,
            photoCount: 6,
            uploadedRoomTags: ['exterior', 'living_room', 'bedroom', 'kitchen'],
            amenityCount: 4,
            documentCount: 0
        );

        // 25 (photos) + 20 (desc) + 15 (location) + 15 (price/area) + 15 (amenities) + 0 (docs) = 90%
        $this->assertSame(90, $result['score']);
        $this->assertTrue($result['breakdown']['photos']);
        $this->assertFalse($result['breakdown']['documents']);
        $this->assertCount(1, $result['missing_fields']);
    }

    public function test_computes_accurate_price_per_square_meter_for_both_land_basis_villas_and_building_basis_apartments(): void
    {
        // SRS Section 36 Example: 20,000,000 ETB / 300 m² = 66,666.67 ETB/m²
        $this->assertSame(66666.67, EtbFormatter::calculatePricePerSqm(20_000_000, 300));
        $this->assertSame(80000.00, EtbFormatter::calculatePricePerSqm(12_000_000, 150));
    }
}
