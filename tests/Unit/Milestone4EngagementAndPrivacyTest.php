<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Analytics\Services\MarketAnalyticsCalculator;
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

    public function test_enforces_srs_section_75_phone_privacy_override_rules_strictly(): void
    {
        $this->assertFalse(RevealSellerPhoneAction::resolveEffectivePrivacy('messaging_only', 'show_phone')->allowsPhoneReveal());
        $this->assertFalse(RevealSellerPhoneAction::resolveEffectivePrivacy('hide_phone', 'show_phone')->allowsPhoneReveal());
        $this->assertTrue(RevealSellerPhoneAction::resolveEffectivePrivacy(null, 'show_phone')->allowsPhoneReveal());
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
}
