<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Booking\Actions\CalculateCancellationRefundAction;
use App\Domains\Calendar\Actions\ResolveDynamicStayQuoteAction;
use App\Domains\Calendar\Services\IcalSyncService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Phase2StayBookingTest extends TestCase
{
    public function test_calculates_srs_section_50_itemized_short_stay_price_quote_accurately(): void
    {
        $quoteAction = new ResolveDynamicStayQuoteAction();

        // SRS Section 50 Example: 5 nights x 8,500 ETB = 42,500 ETB + Cleaning 2,000 ETB
        $quote = $quoteAction->execute(
            checkInDate: '2026-10-25',
            checkOutDate: '2026-10-30', // 5 nights
            profile: [
                'base_nightly_rate_etb' => 8500,
                'weekend_nightly_rate_etb' => 8500,
                'cleaning_fee_etb' => 2000,
                'guest_service_fee_pct' => 5.6179775, // ~2,500 ETB
                'tourism_tax_pct' => 15.0,
                'min_stay_nights' => 2,
            ]
        );

        $this->assertSame(5, $quote['nights_count']);
        $this->assertEquals(42500.0, $quote['accommodation_net_subtotal_etb']);
        $this->assertEquals(2000.0, $quote['cleaning_fee_etb']);
        $this->assertEquals(2500.0, $quote['platform_fee_etb']);
    }

    public function test_enforces_srs_section_52_holiday_minimum_stay_overrides(): void
    {
        $quoteAction = new ResolveDynamicStayQuoteAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minimum of 5 nights');

        // Normal min stay = 2 nights, Holiday rule requires 5 nights
        $quoteAction->execute(
            checkInDate: '2027-01-18', // Timket Holiday period
            checkOutDate: '2027-01-21', // Only 3 nights
            profile: [
                'base_nightly_rate_etb' => 8500,
                'min_stay_nights' => 2,
            ],
            calendarOverridesByDate: [],
            seasonalRules: [
                [
                    'start_date' => '2027-01-17',
                    'end_date' => '2027-01-22',
                    'nightly_rate_etb' => 11000,
                    'min_stay_nights' => 5,
                ],
            ]
        );
    }

    public function test_calculates_srs_section_53_cancellation_refunds_across_flexible_moderate_and_strict_policies(): void
    {
        $refundCalculator = new CalculateCancellationRefundAction();

        // Cancelled 48 hours before check-in under Flexible policy -> 100% refund
        $flexible = $refundCalculator->execute(
            policy: 'flexible',
            checkInDate: '2026-11-10',
            accommodationNetSubtotalEtb: 42500,
            cleaningFeeEtb: 2000,
            cancelledAt: '2026-11-08 10:00:00'
        );

        // Cancelled 48 hours before check-in under Strict policy (< 7 days) -> 0% accommodation refund, 100% cleaning fee refund
        $strict = $refundCalculator->execute(
            policy: 'strict',
            checkInDate: '2026-11-10',
            accommodationNetSubtotalEtb: 42500,
            cleaningFeeEtb: 2000,
            cancelledAt: '2026-11-08 10:00:00'
        );

        $this->assertEquals(44500.0, $flexible['total_guest_refund_etb']);
        $this->assertEquals(0.0, $strict['accommodation_refund_etb']);
        $this->assertEquals(2000.0, $strict['cleaning_fee_refund_etb']);
        $this->assertEquals(2000.0, $strict['total_guest_refund_etb']);
    }

    public function test_exports_and_imports_rfc_5545_ical_feeds_accurately_for_srs_section_57_synchronization(): void
    {
        $ics = IcalSyncService::generateExportIcs('ETH-AA-BOLE-RES-00001245', [
            [
                'uid' => 'BK-2026-0001045',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-05',
                'summary' => 'Confirmed Guest Stay',
            ],
        ]);

        $parsed = IcalSyncService::parseIncomingIcs($ics);

        $this->assertCount(1, $parsed);
        $this->assertSame('2026-11-01', $parsed[0]['start_date']);
        $this->assertSame('2026-11-05', $parsed[0]['end_date']);
    }
}
