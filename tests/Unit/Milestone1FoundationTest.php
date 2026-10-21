<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Identity\Support\EthiopianPhoneNormalizer;
use App\Support\Calendar\EthiopianCalendarConverter;
use App\Support\Money\EtbFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Milestone1FoundationTest extends TestCase
{
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

    public function test_formats_etb_currency_and_calculates_srs_section_36_price_per_square_meter(): void
    {
        $this->assertSame('ETB 25,000,000', EtbFormatter::format(25000000));
        $this->assertSame('ETB 25M', EtbFormatter::compact(25000000));
        $this->assertSame('ETB 80K', EtbFormatter::compact(80000));
        $this->assertSame('ETB 1.5B', EtbFormatter::compact(1500000000));
        $this->assertSame(66666.67, EtbFormatter::calculatePricePerSqm(20000000, 300));
        $this->assertNull(EtbFormatter::calculatePricePerSqm(20000000, 0));
        $this->assertNull(EtbFormatter::calculatePricePerSqm(20000000, null));
    }

    public function test_normalizes_ethio_telecom_and_safaricom_ethiopia_phone_numbers_to_e164(): void
    {
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('0911 234 567'));
        $this->assertSame('+251712345678', EthiopianPhoneNormalizer::normalize('0712-345-678'));
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('+251911234567'));
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('251911234567'));
        $this->assertSame('+251911234567', EthiopianPhoneNormalizer::normalize('00251911234567'));
    }

    public function test_rejects_invalid_phone_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EthiopianPhoneNormalizer::normalize('0112345678'); // Landline prefix not mobile
    }
}
