<?php

declare(strict_types=1);

namespace App\Domains\Calendar\Actions;

use App\Domains\Calendar\Models\AvailabilityCalendar;
use App\Domains\Listing\Models\Listing;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\StayListingProfile;
use App\Support\Calendar\EthiopianCalendarConverter;
use App\Support\Money\EtbFormatter;
use Carbon\CarbonImmutable;

final readonly class GetStayCalendarAvailabilityAction
{
    /**
     * Resolve calendar days for an accommodation unit over a specified month (or 2-month window).
     *
     * @return array{
     *   unit_id: int,
     *   unit_code: string,
     *   year: int,
     *   month: int,
     *   year_month: string,
     *   month_name: string,
     *   gregorian_heading: string,
     *   ethiopian_heading: string,
     *   calendar_heading: string,
     *   days: array<int, array<string, mixed>>,
     *   legend: array<string, string>
     * }
     */
    public function executeForUnit(AccommodationUnit $unit, ?string $yearMonth = null): array
    {
        $unit->loadMissing(['property', 'unitType.profile.seasonalPrices']);
        $profile = $unit->unitType?->profile;

        $targetDate = $yearMonth ? CarbonImmutable::parse($yearMonth.'-01') : CarbonImmutable::today();
        $startOfMonth = $targetDate->startOfMonth();
        $endOfMonth = $targetDate->endOfMonth();
        $today = CarbonImmutable::today();

        $headingInfo = EthiopianCalendarConverter::formatDualMonthHeading($startOfMonth, $endOfMonth);

        // 1. Fetch calendar records in range
        $calendarRecords = AvailabilityCalendar::query()
            ->where('accommodation_unit_id', $unit->id)
            ->whereBetween('calendar_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->keyBy(fn ($c) => $c->calendar_date->toDateString());

        // 2. Pricing configuration
        $baseRate = $profile ? (float) $profile->base_nightly_rate_etb : 8500.0;
        $weekendRate = ($profile && $profile->weekend_nightly_rate_etb !== null)
            ? (float) $profile->weekend_nightly_rate_etb
            : $baseRate;
        $profileMinStay = $profile ? (int) $profile->min_stay_nights : 1;
        $seasonalRules = $profile?->seasonalPrices ?? collect();

        $days = [];
        $cursor = $startOfMonth;

        while ($cursor <= $endOfMonth) {
            $dateStr = $cursor->toDateString();
            $record = $calendarRecords->get($dateStr);
            $isPast = $cursor < $today;

            // Resolve effective status
            $status = 'available';
            if ($record && in_array($record->status, ['booked', 'held_pending_payment', 'blocked_by_host', 'external_ical_blocked'], true)) {
                $status = $record->status;
            }

            // Resolve effective rate
            $rate = $baseRate;
            $minStay = $profileMinStay;

            if ($record && ! empty($record->custom_nightly_rate_etb)) {
                $rate = (float) $record->custom_nightly_rate_etb;
            } else {
                // Seasonal / Holiday rule
                $matchedRule = $seasonalRules->first(fn ($r) => $dateStr >= $r->start_date && $dateStr <= $r->end_date);
                if ($matchedRule) {
                    $rate = ! empty($matchedRule->nightly_rate_etb)
                        ? (float) $matchedRule->nightly_rate_etb
                        : round($baseRate * (float) $matchedRule->rate_multiplier, 2);

                    if (! empty($matchedRule->min_stay_nights)) {
                        $minStay = max($minStay, (int) $matchedRule->min_stay_nights);
                    }
                } else {
                    // Weekend (Fri = 5, Sat = 6)
                    if (in_array($cursor->dayOfWeekIso, [5, 6], true)) {
                        $rate = $weekendRate;
                    }
                }
            }

            if ($record && ! empty($record->min_stay_override_nights)) {
                $minStay = max($minStay, (int) $record->min_stay_override_nights);
            }

            $isAvailable = (! $isPast && $status === 'available');

            // Convert exact Gregorian date to authoritative Ethiopian Date representation
            $ethDate = EthiopianCalendarConverter::fromGregorian($cursor);

            $days[] = [
                'date' => $dateStr,
                'day' => $cursor->day,
                'day_of_week' => $cursor->dayOfWeek, // 0 = Sunday, 6 = Saturday
                'is_past' => $isPast,
                'is_today' => $cursor->isToday(),
                'status' => $status,
                'is_available' => $isAvailable,
                'price_etb' => $rate,
                'rate_etb' => $rate,
                'rate_formatted' => EtbFormatter::format($rate),
                'min_stay' => $minStay,
                'eth_day' => $ethDate->day,
                'eth_month' => $ethDate->month,
                'eth_month_name' => $ethDate->monthName('en'),
                'eth_month_name_am' => $ethDate->monthName('am'),
                'eth_year' => $ethDate->year,
                'eth_formatted' => "{$ethDate->day} {$ethDate->monthName('en')}",
            ];

            $cursor = $cursor->addDay();
        }

        return [
            'success' => true,
            'unit_id' => $unit->id,
            'unit_code' => $unit->unit_number_or_code,
            'year' => (int) $startOfMonth->format('Y'),
            'month' => (int) $startOfMonth->format('n'),
            'year_month' => $startOfMonth->format('Y-m'),
            'month_name' => $headingInfo['combined_heading'],
            'gregorian_heading' => $headingInfo['gregorian_heading'],
            'ethiopian_heading' => $headingInfo['ethiopian_heading'],
            'ethiopian_heading_am' => $headingInfo['ethiopian_heading_am'],
            'calendar_heading' => $headingInfo['combined_heading'],
            'days' => $days,
            'legend' => [
                'available' => 'Available',
                'booked' => 'Booked',
                'held_pending_payment' => 'Temporary Hold (15m)',
                'blocked_by_host' => 'Blocked by Host',
                'external_ical_blocked' => 'External Calendar Block',
            ],
        ];
    }
}
