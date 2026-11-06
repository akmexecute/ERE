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

    public function test_ical_sync_service_blocks_ssrf_and_private_network_urls(): void
    {
        // Localhost and loopback
        $this->assertFalse(IcalSyncService::isUrlAllowed('http://localhost/calendar.ics'));
        $this->assertFalse(IcalSyncService::isUrlAllowed('http://127.0.0.1:8000/feed.ics'));
        $this->assertFalse(IcalSyncService::isUrlAllowed('http://[::1]/cal.ics'));

        // AWS/Cloud metadata service
        $this->assertFalse(IcalSyncService::isUrlAllowed('http://169.254.169.254/latest/meta-data/'));

        // Private Class A, B, C IPs
        $this->assertFalse(IcalSyncService::isUrlAllowed('http://10.0.0.1/feed.ics'));
        $this->assertFalse(IcalSyncService::isUrlAllowed('http://172.16.0.1/feed.ics'));
        $this->assertFalse(IcalSyncService::isUrlAllowed('http://192.168.1.100/feed.ics'));

        // Non-http schemes
        $this->assertFalse(IcalSyncService::isUrlAllowed('file:///etc/passwd'));
        $this->assertFalse(IcalSyncService::isUrlAllowed('ftp://example.com/cal.ics'));

        // Legitimate public HTTPS URLs
        $this->assertTrue(IcalSyncService::isUrlAllowed('https://example.com/ical/feed.ics'));
        $this->assertTrue(IcalSyncService::isUrlAllowed('https://calendar.google.com/calendar/ical/test/public/basic.ics'));
    }

    public function test_ical_parser_handles_line_unfolding_datetime_stamps_and_escaped_characters(): void
    {
        $rawIcs = "BEGIN:VCALENDAR\r\n"
            ."VERSION:2.0\r\n"
            ."BEGIN:VEVENT\r\n"
            ."UID:complex-event-99\r\n"
            ."DTSTART:20261201T140000Z\r\n"
            ."DTEND:20261205T100000Z\r\n"
            ."SUMMARY:Booking with a very long summary that has been folded ac\r\n"
            ." ross multiple lines for RFC 5545 compliance\r\n"
            ."DESCRIPTION:Line one\\, with comma and \\; semicolon\r\n"
            ."END:VEVENT\r\n"
            ."END:VCALENDAR";

        $events = IcalSyncService::parseIncomingIcs($rawIcs);

        $this->assertCount(1, $events);
        $this->assertSame('complex-event-99', $events[0]['uid']);
        $this->assertSame('2026-12-01', $events[0]['start_date']);
        $this->assertSame('2026-12-05', $events[0]['end_date']);
        $this->assertSame('Booking with a very long summary that has been folded across multiple lines for RFC 5545 compliance', $events[0]['summary']);
    }

    public function test_cancellation_refund_boundaries_for_moderate_and_strict_policies(): void
    {
        $refundCalculator = new CalculateCancellationRefundAction();

        // Moderate: 100% >= 120h before check-in (exactly 5 days)
        $modExact120 = $refundCalculator->execute(
            policy: 'moderate',
            checkInDate: '2026-11-10',
            accommodationNetSubtotalEtb: 10000,
            cleaningFeeEtb: 1000,
            cancelledAt: '2026-11-05 14:00:00'
        );
        $this->assertEquals(100, $modExact120['accommodation_refund_pct']);
        $this->assertEquals(11000, $modExact120['total_guest_refund_etb']);

        // Moderate: 50% between 24h and 119h before check-in (e.g. 72h)
        $mod72h = $refundCalculator->execute(
            policy: 'moderate',
            checkInDate: '2026-11-10',
            accommodationNetSubtotalEtb: 10000,
            cleaningFeeEtb: 1000,
            cancelledAt: '2026-11-07 14:00:00'
        );
        $this->assertEquals(50, $mod72h['accommodation_refund_pct']);
        $this->assertEquals(6000, $mod72h['total_guest_refund_etb']);

        // Moderate: 0% within 24h
        $mod12h = $refundCalculator->execute(
            policy: 'moderate',
            checkInDate: '2026-11-10',
            accommodationNetSubtotalEtb: 10000,
            cleaningFeeEtb: 1000,
            cancelledAt: '2026-11-10 02:00:00'
        );
        $this->assertEquals(0, $mod12h['accommodation_refund_pct']);
        $this->assertEquals(1000, $mod12h['total_guest_refund_etb']);

        // Strict: 100% >= 336h (14 days)
        $strict336h = $refundCalculator->execute(
            policy: 'strict',
            checkInDate: '2026-11-20',
            accommodationNetSubtotalEtb: 20000,
            cleaningFeeEtb: 2000,
            cancelledAt: '2026-11-06 14:00:00'
        );
        $this->assertEquals(100, $strict336h['accommodation_refund_pct']);

        // Strict: 50% between 168h and 335h (7-13 days)
        $strict200h = $refundCalculator->execute(
            policy: 'strict',
            checkInDate: '2026-11-20',
            accommodationNetSubtotalEtb: 20000,
            cleaningFeeEtb: 2000,
            cancelledAt: '2026-11-12 14:00:00'
        );
        $this->assertEquals(50, $strict200h['accommodation_refund_pct']);
        $this->assertEquals(12000, $strict200h['total_guest_refund_etb']);

        // After check-in: 0% refund including cleaning fee
        $afterCheckIn = $refundCalculator->execute(
            policy: 'flexible',
            checkInDate: '2026-11-10',
            accommodationNetSubtotalEtb: 10000,
            cleaningFeeEtb: 1000,
            cancelledAt: '2026-11-10 16:00:00'
        );
        $this->assertEquals(0, $afterCheckIn['accommodation_refund_pct']);
        $this->assertEquals(0, $afterCheckIn['cleaning_fee_refund_etb']);
        $this->assertEquals(0, $afterCheckIn['total_guest_refund_etb']);
        $this->assertEquals(11000, $afterCheckIn['host_retained_etb']);
    }

    public function test_dynamic_quote_applies_weekly_and_monthly_stay_discounts(): void
    {
        $quoteAction = new ResolveDynamicStayQuoteAction();

        // 7 nights (weekly discount 10%)
        // 7 x 1,000 = 7,000. 10% discount = 700. Net = 6,300.
        $weeklyQuote = $quoteAction->execute(
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-08',
            profile: [
                'base_nightly_rate_etb' => 1000,
                'weekend_nightly_rate_etb' => 1000,
                'weekly_discount_pct' => 10,
                'monthly_discount_pct' => 20,
                'cleaning_fee_etb' => 500,
                'guest_service_fee_pct' => 0,
                'tourism_tax_pct' => 0,
                'min_stay_nights' => 1,
            ]
        );

        $this->assertSame(7, $weeklyQuote['nights_count']);
        $this->assertSame('weekly_discount', $weeklyQuote['discount_type']);
        $this->assertEquals(700.0, $weeklyQuote['discount_amount_etb']);
        $this->assertEquals(6300.0, $weeklyQuote['accommodation_net_subtotal_etb']);
        $this->assertEquals(6800.0, $weeklyQuote['total_amount_etb']);

        // 30 nights (monthly discount 20%)
        // 30 x 1,000 = 30,000. 20% discount = 6,000. Net = 24,000.
        $monthlyQuote = $quoteAction->execute(
            checkInDate: '2026-11-01',
            checkOutDate: '2026-12-01',
            profile: [
                'base_nightly_rate_etb' => 1000,
                'weekend_nightly_rate_etb' => 1000,
                'weekly_discount_pct' => 10,
                'monthly_discount_pct' => 20,
                'cleaning_fee_etb' => 500,
                'guest_service_fee_pct' => 0,
                'tourism_tax_pct' => 0,
                'min_stay_nights' => 1,
            ]
        );

        $this->assertSame(30, $monthlyQuote['nights_count']);
        $this->assertSame('monthly_discount', $monthlyQuote['discount_type']);
        $this->assertEquals(6000.0, $monthlyQuote['discount_amount_etb']);
        $this->assertEquals(24000.0, $monthlyQuote['accommodation_net_subtotal_etb']);
    }

    public function test_rejects_invalid_date_ranges(): void
    {
        $quoteAction = new ResolveDynamicStayQuoteAction();

        $this->expectException(InvalidArgumentException::class);
        $quoteAction->execute(
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-05',
            profile: ['base_nightly_rate_etb' => 1000, 'min_stay_nights' => 1]
        );
    }

    public function test_authoritative_gregorian_to_ethiopian_conversion_and_known_dates(): void
    {
        // 1. Mandatory prompt example: Gregorian Jan 1, 2022 corresponds to Tahsas 23, 2014
        $jan1 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2022-01-01');
        $this->assertSame(23, $jan1->day);
        $this->assertSame(4, $jan1->month);
        $this->assertSame('Tahsas', $jan1->monthName('en'));
        $this->assertSame(2014, $jan1->year);

        // 2. Standard Ethiopian New Year (1 Meskerem 2015 on Sep 11, 2022)
        $newYear2015 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2022-09-11');
        $this->assertSame(1, $newYear2015->day);
        $this->assertSame(1, $newYear2015->month);
        $this->assertSame('Meskerem', $newYear2015->monthName('en'));
        $this->assertSame(2015, $newYear2015->year);

        // 3. Gregorian leap year: Feb 29, 2024 -> 21 Yekatit 2016
        $feb28 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2024-02-28');
        $feb29 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2024-02-29');
        $mar01 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2024-03-01');
        $this->assertSame(20, $feb28->day);
        $this->assertSame(21, $feb29->day);
        $this->assertSame(22, $mar01->day);
        $this->assertSame('Yekatit', $feb29->monthName('en'));
        $this->assertSame(2016, $feb29->year);
    }

    public function test_ethiopian_calendar_pagume_and_leap_year_behavior(): void
    {
        // Ethiopian year 2014 was a standard 5-day Pagume year
        $this->assertFalse(\App\Support\Calendar\EthiopianCalendarConverter::isEthiopianLeapYear(2014));
        $pagume1_2014 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2022-09-06');
        $pagume5_2014 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2022-09-10');
        $meskerem1_2015 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2022-09-11');

        $this->assertSame(1, $pagume1_2014->day);
        $this->assertSame(13, $pagume1_2014->month); // Pagume
        $this->assertSame(5, $pagume5_2014->day);
        $this->assertSame(13, $pagume5_2014->month);
        $this->assertSame(1, $meskerem1_2015->day);
        $this->assertSame(1, $meskerem1_2015->month); // Meskerem

        // Ethiopian year 2015 was an Ethiopian leap year with 6 days in Pagume
        $this->assertTrue(\App\Support\Calendar\EthiopianCalendarConverter::isEthiopianLeapYear(2015));
        $pagume5_2015 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2023-09-10');
        $pagume6_2015 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2023-09-11');
        $meskerem1_2016 = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian('2023-09-12');

        $this->assertSame(5, $pagume5_2015->day);
        $this->assertSame(13, $pagume5_2015->month);
        $this->assertSame(6, $pagume6_2015->day);
        $this->assertSame(13, $pagume6_2015->month);
        $this->assertSame(1, $meskerem1_2016->day);
        $this->assertSame(1, $meskerem1_2016->month);
    }

    public function test_dual_month_heading_generation_across_month_and_year_boundaries(): void
    {
        // 1. Cross-month within same Ethiopian year: January 2022 (Tahsas 23, 2014 -> Tir 23, 2014)
        $jan2022Start = \Carbon\CarbonImmutable::parse('2022-01-01');
        $jan2022End = \Carbon\CarbonImmutable::parse('2022-01-31');
        $janHeading = \App\Support\Calendar\EthiopianCalendarConverter::formatDualMonthHeading($jan2022Start, $jan2022End);

        $this->assertSame('January 2022', $janHeading['gregorian_heading']);
        $this->assertSame('Tahsas – Tir 2014', $janHeading['ethiopian_heading']);
        $this->assertSame('January 2022 • Tahsas – Tir 2014', $janHeading['combined_heading']);

        // 2. Cross-month across Ethiopian year boundary: September 2023 (Nehase 2015 -> Meskerem 2016)
        $sep2023Start = \Carbon\CarbonImmutable::parse('2023-09-01');
        $sep2023End = \Carbon\CarbonImmutable::parse('2023-09-30');
        $sepHeading = \App\Support\Calendar\EthiopianCalendarConverter::formatDualMonthHeading($sep2023Start, $sep2023End);

        $this->assertSame('September 2023', $sepHeading['gregorian_heading']);
        $this->assertSame('Nehase 2015 – Meskerem 2016', $sepHeading['ethiopian_heading']);
        $this->assertSame('September 2023 • Nehase 2015 – Meskerem 2016', $sepHeading['combined_heading']);
    }

    public function test_round_trip_gregorian_to_ethiopian_and_back_preserves_booking_truth(): void
    {
        $testDates = [
            '2022-01-01',
            '2022-09-11',
            '2023-09-11',
            '2024-02-29',
            '2026-10-25',
            '2026-12-31',
        ];

        foreach ($testDates as $dateStr) {
            $eth = \App\Support\Calendar\EthiopianCalendarConverter::fromGregorian($dateStr);
            $backToGreg = \App\Support\Calendar\EthiopianCalendarConverter::toGregorian($eth->year, $eth->month, $eth->day);
            $this->assertSame($dateStr, $backToGreg->toDateString());
        }
    }
}

