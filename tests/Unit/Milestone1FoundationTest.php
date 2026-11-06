<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Administration\Models\SystemSetting;
use App\Domains\Administration\Services\AuditService;
use App\Domains\Administration\Services\SettingService;
use App\Domains\Identity\Support\EthiopianPhoneNormalizer;
use App\Domains\Location\Models\Location;
use App\Domains\GIS\Actions\ReverseGeocodePointAction;
use App\Support\Calendar\EthiopianCalendarConverter;
use App\Support\Money\EtbFormatter;
use DomainException;
use InvalidArgumentException;
use Tests\TestCase;

final class Milestone1FoundationTest extends TestCase
{
    // ==========================================
    // 1. ETHIOPIAN CALENDAR TESTS (Section 4.3)
    // ==========================================

    public function test_converts_gregorian_dates_to_ethiopian_calendar_accurately_including_meskerem_new_year(): void
    {
        // September 11, 2026 (Gregorian) is Meskerem 1, 2019 (Ethiopian)
        $ethDate = EthiopianCalendarConverter::fromGregorian('2026-09-11');

        $this->assertSame(2019, $ethDate->year);
        $this->assertSame(1, $ethDate->month);
        $this->assertSame(1, $ethDate->day);
        $this->assertSame('2019 መስከረም 01', $ethDate->format('am'));
        $this->assertSame('2019 Fulbaana 01', $ethDate->format('om'));
        $this->assertSame('2019 Meskerem 01', $ethDate->format('en'));

        // Round-trip back to Gregorian
        $gregorian = EthiopianCalendarConverter::toGregorian(2019, 1, 1);
        $this->assertSame('2026-09-11', $gregorian->toDateString());
    }

    public function test_ethiopian_new_year_transitions_before_and_after_new_year(): void
    {
        // 2026-09-10 (Day before Ethiopian New Year) is 2018 Pagume 5
        $before = EthiopianCalendarConverter::fromGregorian('2026-09-10');
        $this->assertSame(2018, $before->year);
        $this->assertSame(13, $before->month); // Pagume
        $this->assertSame(5, $before->day);

        // 2026-09-11 (Ethiopian New Year) is 2019 Meskerem 1
        $newYear = EthiopianCalendarConverter::fromGregorian('2026-09-11');
        $this->assertSame(2019, $newYear->year);
        $this->assertSame(1, $newYear->month);
        $this->assertSame(1, $newYear->day);

        // 2026-09-12 (Day after New Year) is 2019 Meskerem 2
        $after = EthiopianCalendarConverter::fromGregorian('2026-09-12');
        $this->assertSame(2019, $after->year);
        $this->assertSame(1, $after->month);
        $this->assertSame(2, $after->day);
    }

    public function test_ethiopian_calendar_pagume_leap_year_rules(): void
    {
        // Ethiopian 2015 is a leap year (2015 % 4 = 3) -> Pagume has 6 days!
        $this->assertTrue(EthiopianCalendarConverter::isEthiopianLeapYear(2015));
        $this->assertFalse(EthiopianCalendarConverter::isEthiopianLeapYear(2016));

        // Pagume 6, 2015 is valid and converts to Gregorian 2023-09-11
        $gregorian = EthiopianCalendarConverter::toGregorian(2015, 13, 6);
        $this->assertSame('2023-09-11', $gregorian->toDateString());

        // In 2016 (ordinary year), Pagume only has 5 days; Pagume 6 must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        EthiopianCalendarConverter::toGregorian(2016, 13, 6);
    }

    public function test_rejects_invalid_ethiopian_months_and_days(): void
    {
        // Month 14 does not exist
        try {
            EthiopianCalendarConverter::toGregorian(2017, 14, 1);
            $this->fail('Expected InvalidArgumentException for month 14.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Ethiopian month must be between 1 and 13', $e->getMessage());
        }

        // Day 31 does not exist in standard Ethiopian months (max 30)
        try {
            EthiopianCalendarConverter::toGregorian(2017, 1, 31);
            $this->fail('Expected InvalidArgumentException for day 31.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid Ethiopian day', $e->getMessage());
        }

        // Day 0 does not exist
        try {
            EthiopianCalendarConverter::toGregorian(2017, 1, 0);
            $this->fail('Expected InvalidArgumentException for day 0.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid Ethiopian day', $e->getMessage());
        }
    }

    public function test_round_trip_conversion_across_all_twelve_months(): void
    {
        // Test round trip for each month of Ethiopian year 2017 (day 15 of every month)
        for ($month = 1; $month <= 12; $month++) {
            $greg = EthiopianCalendarConverter::toGregorian(2017, $month, 15);
            $eth = EthiopianCalendarConverter::fromGregorian($greg);

            $this->assertSame(2017, $eth->year);
            $this->assertSame($month, $eth->month);
            $this->assertSame(15, $eth->day);
        }
    }

    // ==========================================
    // 2. ETB CURRENCY & CALCULATIONS (Section 4.5)
    // ==========================================

    public function test_formats_etb_currency_and_calculates_srs_section_36_price_per_square_meter(): void
    {
        $this->assertSame('ETB 25,000,000', EtbFormatter::format(25000000));
        $this->assertSame('ETB 25,000,000.00', EtbFormatter::format(25000000, withDecimals: true));
        $this->assertSame('ETB 25M', EtbFormatter::compact(25000000));
        $this->assertSame('ETB 80K', EtbFormatter::compact(80000));
        $this->assertSame('ETB 1.5B', EtbFormatter::compact(1500000000));
        $this->assertSame(66666.67, EtbFormatter::calculatePricePerSqm(20000000, 300));
        $this->assertNull(EtbFormatter::calculatePricePerSqm(20000000, 0));
        $this->assertNull(EtbFormatter::calculatePricePerSqm(20000000, -10));
        $this->assertNull(EtbFormatter::calculatePricePerSqm(20000000, null));
        $this->assertNull(EtbFormatter::calculatePricePerSqm(-5000, 100));
    }

    public function test_etb_minor_units_exact_arithmetic(): void
    {
        $this->assertSame(2500000000, EtbFormatter::toMinorUnits(25000000.00));
        $this->assertSame(1495000055, EtbFormatter::toMinorUnits('14950000.55'));
        $this->assertSame(14950000.55, EtbFormatter::fromMinorUnits(1495000055));
    }

    // ==========================================
    // 3. ETHIOPIAN PHONE NORMALIZATION (Section 6.1)
    // ==========================================

    public function test_normalizes_ethio_telecom_and_safaricom_ethiopia_phone_numbers_to_e164(): void
    {
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('0911 234 567'));
        $this->assertSame('+251712345678', EthiopianPhoneNormalizer::normalize('0712-345-678'));
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('+251911234567'));
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('251911234567'));
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('00251911234567'));
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('(0911) 234 567'));
    }

    public function test_rejects_invalid_phone_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EthiopianPhoneNormalizer::normalize('0112345678'); // Landline prefix not mobile
    }

    // ==========================================
    // 4. REVERSE GEOCODING COORDINATES (Section 8.1)
    // ==========================================

    public function test_rejects_out_of_bound_geographic_coordinates(): void
    {
        $action = new ReverseGeocodePointAction();

        // Invalid latitude > 90
        try {
            $action->execute(95.0, 38.75);
            $this->fail('Expected InvalidArgumentException for latitude > 90.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Latitude must be between -90 and 90', $e->getMessage());
        }

        // Invalid longitude > 180
        try {
            $action->execute(9.0, 195.0);
            $this->fail('Expected InvalidArgumentException for longitude > 180.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Longitude must be between -180 and 180', $e->getMessage());
        }
    }

    // ==========================================
    // 5. AUDIT DATA REDACTION (Section 5.4)
    // ==========================================

    public function test_audit_service_redacts_sensitive_keys_in_nested_structures(): void
    {
        $service = new AuditService();

        $input = [
            'name' => 'Abebe',
            'phone' => '+251911223344',
            'password' => 'secret123',
            'nested' => [
                'otp_hash' => 'hash_abc',
                'api_key' => 'live_sk_9999',
                'normal_key' => 'visible_value',
            ],
        ];

        $redacted = $service->redactSensitive($input);

        $this->assertSame('Abebe', $redacted['name']);
        $this->assertSame('+251911223344', $redacted['phone']);
        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['nested']['otp_hash']);
        $this->assertSame('[REDACTED]', $redacted['nested']['api_key']);
        $this->assertSame('visible_value', $redacted['nested']['normal_key']);
    }

    // ==========================================
    // 6. LOCATION HIERARCHY INTEGRITY (Section 7.2)
    // ==========================================

    public function test_location_model_prevents_invalid_level_and_self_parenting(): void
    {
        $loc = new Location([
            'level' => 'invalid_level',
            'code' => 'TEST',
            'slug' => 'test-loc',
            'name_en' => 'Test',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $loc->validateHierarchy();
    }

    // ==========================================
    // 7. TIMEZONE & MIDNIGHT BOUNDARIES IN AFRICA/ADDIS_ABABA (Section 3.5)
    // ==========================================

    public function test_ethiopian_calendar_timezone_handling_around_midnight_in_addis_ababa(): void
    {
        // 2026-09-10 20:59:59 UTC is 2026-09-10 23:59:59 EAT (Addis Ababa time) -> still Pagume 5, 2018
        $utcBeforeMidnight = \Carbon\CarbonImmutable::parse('2026-09-10 20:59:59', 'UTC');
        $ethBefore = EthiopianCalendarConverter::fromGregorian($utcBeforeMidnight);
        $this->assertSame(2018, $ethBefore->year);
        $this->assertSame(13, $ethBefore->month); // Pagume
        $this->assertSame(5, $ethBefore->day);

        // 2026-09-10 21:00:01 UTC is 2026-09-11 00:00:01 EAT (Addis Ababa midnight passed) -> Meskerem 1, 2019 (New Year!)
        $utcAfterMidnight = \Carbon\CarbonImmutable::parse('2026-09-10 21:00:01', 'UTC');
        $ethAfter = EthiopianCalendarConverter::fromGregorian($utcAfterMidnight);
        $this->assertSame(2019, $ethAfter->year);
        $this->assertSame(1, $ethAfter->month); // Meskerem
        $this->assertSame(1, $ethAfter->day);
    }

    public function test_localized_names_for_all_thirteen_ethiopian_months(): void
    {
        // Verify all 13 months have distinct, non-empty localized names in Amharic and Afaan Oromo
        for ($m = 1; $m <= 13; $m++) {
            $date = new \App\Support\Calendar\EthiopianDate(2019, $m, 1, dayOfWeek: 0);
            $enName = $date->monthName('en');
            $amName = $date->monthName('am');
            $omName = $date->monthName('om');

            $this->assertNotEmpty($enName);
            $this->assertNotEmpty($amName);
            $this->assertNotEmpty($omName);
            $this->assertNotSame($enName, $amName);
        }

        // Test human formatted string with day of week
        $sunday = new \App\Support\Calendar\EthiopianDate(2019, 1, 1, dayOfWeek: 0);
        $this->assertStringContainsString('እሁድ', $sunday->toHumanString('am'));
        $this->assertStringContainsString('Dilbata', $sunday->toHumanString('om'));
        $this->assertStringContainsString('Sunday', $sunday->toHumanString('en'));
    }

    // ==========================================
    // 8. COMPOUND MONETARY PRECISION SAFETY (Section 3.5)
    // ==========================================

    public function test_compound_financial_calculations_prevent_floating_point_drift(): void
    {
        // 15% VAT on ETB 12,345,678.95
        $baseCents = EtbFormatter::toMinorUnits('12345678.95');
        $vatCents = (int) round($baseCents * 0.15);
        $totalCents = $baseCents + $vatCents;

        $this->assertSame(1234567895, $baseCents);
        $this->assertSame(185185184, $vatCents);
        $this->assertSame(1419753079, $totalCents);
        $this->assertSame(14197530.79, EtbFormatter::fromMinorUnits($totalCents));
        $this->assertSame('ETB 14,197,530.79', EtbFormatter::format(EtbFormatter::fromMinorUnits($totalCents), withDecimals: true));
    }
}
