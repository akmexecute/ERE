<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Marketplace\Actions\ParseNaturalPropertyQueryAction;
use App\Domains\Moderation\Actions\EvaluateListingFraudSignalsAction;
use App\Domains\Verification\Enums\VerificationLevel;
use PHPUnit\Framework\TestCase;

final class Milestone3SearchAndModerationTest extends TestCase
{
    public function test_parses_srs_section_15_natural_language_property_query_into_structured_filters(): void
    {
        $parser = new ParseNaturalPropertyQueryAction();

        // Exact SRS Section 15 example: "4-bedroom villa in Bole under 30 million with generator"
        $parsed = $parser->execute('4-bedroom villa in Bole under 30 million with generator');

        $this->assertSame('villa', $parsed['property_type_slug']);
        $this->assertSame('bole', $parsed['location_keyword']);
        $this->assertSame(4, $parsed['min_bedrooms']);
        $this->assertSame(30_000_000.0, $parsed['max_price_etb']);
        $this->assertSame('BUY', $parsed['transaction_mode']);
        $this->assertContains('backup-generator', $parsed['amenities']);
    }

    public function test_computes_64_bit_perceptual_hash_hamming_distance_accurately_for_duplicate_photo_detection(): void
    {
        // Identical hashes = 0 distance
        $this->assertSame(0, EvaluateListingFraudSignalsAction::hammingDistance64Hex('f0f0f0f0f0f0f0f0', 'f0f0f0f0f0f0f0f0'));

        // 1 bit difference in last nibble (0 vs 1)
        $this->assertSame(1, EvaluateListingFraudSignalsAction::hammingDistance64Hex('f0f0f0f0f0f0f0f0', 'f0f0f0f0f0f0f0f1'));
    }

    public function test_includes_proclamation_no_1357_2024_statutory_disclaimer_on_every_verification_badge_explanation(): void
    {
        $explanation = VerificationLevel::DocumentsSubmitted->visibleExplanation('en');

        $this->assertStringContainsString('Proclamation No. 1357/2024', $explanation);
        $this->assertStringContainsString('does NOT constitute official government land-registry title certification', $explanation);
    }
}
