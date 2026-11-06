<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Analytics\Services\MarketAnalyticsCalculator;
use App\Domains\Identity\Enums\PhonePrivacyMode;
use App\Domains\Inquiry\Actions\RevealSellerPhoneAction;
use App\Domains\Marketplace\Actions\ComparePropertiesMatrixAction;
use PHPUnit\Framework\TestCase;

final class Milestone4EngagementAndPrivacyTest extends TestCase
{
    public function test_compares_up_to_4_properties_and_highlights_lowest_price_per_sqm_and_highest_verification_level(): void
    {
        $matrix = (new ComparePropertiesMatrixAction())->execute([
            [
                'id' => 101,
                'property_code' => 'ETH-AA-BOLE-RES-00000101',
                'title' => 'Villa A in Bole',
                'price_etb' => 24_000_000,
                'area_sqm' => 400, // 60,000 ETB/m²
                'bedrooms' => 4,
                'bathrooms' => 4,
                'parking_spaces' => 3,
                'verification_level' => 3,
            ],
            [
                'id' => 102,
                'property_code' => 'ETH-AA-YEKA-RES-00000102',
                'title' => 'Villa B in Yeka',
                'price_etb' => 21_000_000,
                'area_sqm' => 300, // 70,000 ETB/m²
                'bedrooms' => 4,
                'bathrooms' => 3,
                'parking_spaces' => 2,
                'verification_level' => 2,
            ],
        ]);

        $this->assertSame(102, $matrix['highlights']['lowest_price_id']);
        $this->assertSame(101, $matrix['highlights']['lowest_price_per_sqm_id']);
        $this->assertSame(101, $matrix['highlights']['largest_area_id']);
        $this->assertSame(101, $matrix['highlights']['highest_verification_id']);
    }

    public function test_comparison_matrix_handles_empty_single_and_exceeding_property_counts(): void
    {
        $action = new ComparePropertiesMatrixAction();

        // 1. Empty input
        $empty = $action->execute([]);
        $this->assertCount(0, $empty['items']);
        $this->assertNull($empty['highlights']['lowest_price_id']);
        $this->assertNull($empty['highlights']['lowest_price_per_sqm_id']);
        $this->assertNull($empty['highlights']['largest_area_id']);
        $this->assertNull($empty['highlights']['highest_verification_id']);

        // 2. Single property
        $single = $action->execute([
            [
                'id' => 50,
                'property_code' => 'ETH-AA-ARAD-RES-00000050',
                'title' => 'Piazza Townhouse',
                'price_etb' => 12_000_000,
                'area_sqm' => 150,
                'verification_level' => 2,
            ],
        ]);
        $this->assertCount(1, $single['items']);
        $this->assertSame(50, $single['highlights']['lowest_price_id']);
        $this->assertSame(50, $single['highlights']['lowest_price_per_sqm_id']);
        $this->assertSame(50, $single['highlights']['largest_area_id']);
        $this->assertSame(50, $single['highlights']['highest_verification_id']);

        // 3. More than 4 properties -> strictly limited to 4
        $five = $action->execute([
            ['id' => 1, 'price_etb' => 10_000_000, 'area_sqm' => 100],
            ['id' => 2, 'price_etb' => 20_000_000, 'area_sqm' => 200],
            ['id' => 3, 'price_etb' => 30_000_000, 'area_sqm' => 300],
            ['id' => 4, 'price_etb' => 40_000_000, 'area_sqm' => 400],
            ['id' => 5, 'price_etb' => 50_000_000, 'area_sqm' => 500],
            ['id' => 6, 'price_etb' => 60_000_000, 'area_sqm' => 600],
        ]);
        $this->assertCount(4, $five['items']);
        $this->assertSame(1, $five['highlights']['lowest_price_id']);
        $this->assertSame(4, $five['highlights']['largest_area_id']);
    }

    public function test_comparison_matrix_safeguards_zero_and_null_area_and_resolves_ties_deterministically(): void
    {
        $action = new ComparePropertiesMatrixAction();

        $result = $action->execute([
            [
                'id' => 10,
                'price_etb' => 15_000_000,
                'area_sqm' => null, // Area missing
                'verification_level' => 2,
            ],
            [
                'id' => 20,
                'price_etb' => 15_000_000, // Price tie with #10
                'area_sqm' => 0.0, // Zero area measurement
                'verification_level' => 2,
            ],
            [
                'id' => 30,
                'price_etb' => 25_000_000,
                'area_sqm' => 250.0, // Only valid area
                'verification_level' => 2,
            ],
        ]);

        // Deterministic price tie: First record #10 selected
        $this->assertSame(10, $result['highlights']['lowest_price_id']);

        // Null and 0 area are NEVER treated as largest area
        $this->assertSame(30, $result['highlights']['largest_area_id']);

        // Only #30 has valid price_per_sqm (25M / 250 = 100,000 ETB/m²)
        $this->assertSame(30, $result['highlights']['lowest_price_per_sqm_id']);
    }

    public function test_enforces_srs_section_75_phone_privacy_override_rules_strictly(): void
    {
        $this->assertFalse(RevealSellerPhoneAction::resolveEffectivePrivacy('messaging_only', 'show_phone')->allowsPhoneReveal());
        $this->assertFalse(RevealSellerPhoneAction::resolveEffectivePrivacy('hide_phone', 'show_phone')->allowsPhoneReveal());
        $this->assertTrue(RevealSellerPhoneAction::resolveEffectivePrivacy(null, 'show_phone')->allowsPhoneReveal());
    }

    public function test_phone_privacy_fails_closed_on_invalid_or_corrupt_settings(): void
    {
        // Invalid or corrupt strings fail closed to HidePhone
        $this->assertSame(PhonePrivacyMode::HidePhone, RevealSellerPhoneAction::resolveEffectivePrivacy('invalid_value', 'show_phone'));
        $this->assertSame(PhonePrivacyMode::HidePhone, RevealSellerPhoneAction::resolveEffectivePrivacy(null, 'corrupt_mode'));
        $this->assertSame(PhonePrivacyMode::HidePhone, RevealSellerPhoneAction::resolveEffectivePrivacy('', ''));
        $this->assertFalse(RevealSellerPhoneAction::resolveEffectivePrivacy(null, null)->allowsPhoneReveal());
    }

    public function test_computes_srs_section_37_and_127_market_analytics_and_labels_data_basis_as_platform_asking_price_indicator(): void
    {
        $summary = MarketAnalyticsCalculator::summarizeListings([
            ['price_etb' => 15_000_000, 'price_per_sqm_etb' => 50_000],
            ['price_etb' => 20_000_000, 'price_per_sqm_etb' => 66_666.67],
            ['price_etb' => 25_000_000, 'price_per_sqm_etb' => 75_000],
        ]);

        $this->assertSame(3, $summary['count']);
        $this->assertSame(20_000_000.0, $summary['avg_asking_price_etb']);
        $this->assertSame(20_000_000.0, $summary['median_asking_price_etb']);
        $this->assertSame('platform_asking_price_indicator', $summary['data_basis']);
        $this->assertStringContainsString('Platform-Derived Market Indicator', $summary['disclosure_notice']);
    }

    public function test_market_analytics_calculates_even_and_odd_medians_and_handles_empty_dataset(): void
    {
        // 1. Empty dataset
        $empty = MarketAnalyticsCalculator::summarizeListings([]);
        $this->assertSame(0, $empty['count']);
        $this->assertNull($empty['avg_asking_price_etb']);
        $this->assertNull($empty['median_asking_price_etb']);
        $this->assertNull($empty['avg_price_per_sqm_etb']);

        // 2. Even number of items: Median is average of the two middle elements
        $even = MarketAnalyticsCalculator::summarizeListings([
            ['price_etb' => 10_000_000],
            ['price_etb' => 20_000_000],
            ['price_etb' => 30_000_000],
            ['price_etb' => 40_000_000],
        ]);
        $this->assertSame(4, $even['count']);
        $this->assertSame(25_000_000.0, $even['median_asking_price_etb']);
        $this->assertSame(25_000_000.0, $even['avg_asking_price_etb']);
    }
}
