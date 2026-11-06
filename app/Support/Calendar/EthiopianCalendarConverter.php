<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class EthiopianCalendarConverter
{
    /**
     * Ethiopian epoch offset in Julian Day Numbers (JDN).
     */
    private const int JD_EPOCH_OFFSET_AMETE_MIHRET = 1723856;

    public static function fromGregorian(CarbonInterface|string $date): EthiopianDate
    {
        $carbon = $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)->setTimezone('Africa/Addis_Ababa')
            : CarbonImmutable::parse($date, 'Africa/Addis_Ababa');

        $jdn = self::gregorianToJdn($carbon->year, $carbon->month, $carbon->day);

        return self::jdnToEthiopian($jdn);
    }

    public static function toGregorian(int $ethYear, int $ethMonth, int $ethDay): CarbonImmutable
    {
        if ($ethMonth < 1 || $ethMonth > 13) {
            throw new InvalidArgumentException('Ethiopian month must be between 1 and 13.');
        }

        $maxDays = $ethMonth === 13 ? (self::isEthiopianLeapYear($ethYear) ? 6 : 5) : 30;
        if ($ethDay < 1 || $ethDay > $maxDays) {
            throw new InvalidArgumentException("Invalid Ethiopian day {$ethDay} for month {$ethMonth}.");
        }

        $jdn = self::ethiopianToJdn($ethYear, $ethMonth, $ethDay);

        return self::jdnToGregorian($jdn);
    }

    public static function isEthiopianLeapYear(int $ethYear): bool
    {
        return ($ethYear + 1) % 4 === 0;
    }

    /**
     * Format a dual Gregorian & Ethiopian calendar heading for a given month range.
     * Handles single-month, cross-month, and cross-year transitions.
     *
     * @return array{
     *   gregorian_heading: string,
     *   ethiopian_heading: string,
     *   ethiopian_heading_am: string,
     *   combined_heading: string,
     *   combined_heading_am: string
     * }
     */
    public static function formatDualMonthHeading(CarbonInterface $startOfMonth, CarbonInterface $endOfMonth): array
    {
        $startEth = self::fromGregorian($startOfMonth);
        $endEth = self::fromGregorian($endOfMonth);

        $gregHeading = $startOfMonth->format('F Y');

        if ($startEth->year === $endEth->year) {
            if ($startEth->month === $endEth->month) {
                $ethHeadingEn = "{$startEth->monthName('en')} {$startEth->year}";
                $ethHeadingAm = "{$startEth->monthName('am')} {$startEth->year}";
            } else {
                $ethHeadingEn = "{$startEth->monthName('en')} – {$endEth->monthName('en')} {$startEth->year}";
                $ethHeadingAm = "{$startEth->monthName('am')} – {$endEth->monthName('am')} {$startEth->year}";
            }
        } else {
            $ethHeadingEn = "{$startEth->monthName('en')} {$startEth->year} – {$endEth->monthName('en')} {$endEth->year}";
            $ethHeadingAm = "{$startEth->monthName('am')} {$startEth->year} – {$endEth->monthName('am')} {$endEth->year}";
        }

        return [
            'gregorian_heading' => $gregHeading,
            'ethiopian_heading' => $ethHeadingEn,
            'ethiopian_heading_am' => $ethHeadingAm,
            'combined_heading' => "{$gregHeading} • {$ethHeadingEn}",
            'combined_heading_am' => "{$gregHeading} • {$ethHeadingAm}",
        ];
    }

    private static function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day
            + intdiv(153 * $m + 2, 5)
            + 365 * $y
            + intdiv($y, 4)
            - intdiv($y, 100)
            + intdiv($y, 400)
            - 32045;
    }

    private static function jdnToEthiopian(int $jdn): EthiopianDate
    {
        $r = ($jdn - self::JD_EPOCH_OFFSET_AMETE_MIHRET) % 1461;
        if ($r < 0) {
            $r += 1461;
        }

        $n = ($r % 365) + 365 * intdiv($r, 1460);

        $year = 4 * intdiv($jdn - self::JD_EPOCH_OFFSET_AMETE_MIHRET, 1461)
            + intdiv($r, 365)
            - intdiv($r, 1460);

        $month = intdiv($n, 30) + 1;
        $day = ($n % 30) + 1;

        $dow = ($jdn + 1) % 7;
        $dayOfWeek = $dow < 0 ? $dow + 7 : $dow;

        return new EthiopianDate($year, $month, $day, $dayOfWeek);
    }

    private static function ethiopianToJdn(int $year, int $month, int $day): int
    {
        return (self::JD_EPOCH_OFFSET_AMETE_MIHRET + 365)
            + 365 * ($year - 1)
            + intdiv($year, 4)
            + 30 * $month
            + $day
            - 31;
    }

    private static function jdnToGregorian(int $jdn): CarbonImmutable
    {
        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);

        return CarbonImmutable::createFromDate($year, $month, $day, 'Africa/Addis_Ababa')->startOfDay();
    }
}
