<?php

declare(strict_types=1);

namespace App\Domains\Host\Services;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\Host\Models\Host;
use App\Domains\Review\Models\StayReview;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\StayListingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class HostDashboardKpiCalculator
{
    /**
     * All configurable optional KPI card keys.
     */
    public const CARD_KEYS = [
        'kpi_occupancy' => 'Occupancy & RevPAR',
        'kpi_adr' => 'Average Daily Rate (ADR)',
        'kpi_revenue' => 'Net Revenue & Earnings',
        'kpi_arrivals_departures' => "Today's Arrivals & Departures",
        'kpi_housekeeping' => 'Housekeeping & Turnover Pipeline',
        'kpi_deposits' => 'Security Deposit Custody & Claims',
        'kpi_reputation' => 'Two-Way Reputation & Ratings',
        'kpi_booking_stats' => 'Booking Lead Time & Duration',
    ];

    /**
     * Default card preferences (all enabled).
     *
     * @return array<string, bool>
     */
    public static function defaultPreferences(): array
    {
        $prefs = [];
        foreach (array_keys(self::CARD_KEYS) as $key) {
            $prefs[$key] = true;
        }

        return $prefs;
    }

    /**
     * Get persisted show/hide preferences for a host.
     *
     * @return array<string, bool>
     */
    public static function getPreferences(?Host $host): array
    {
        $defaults = self::defaultPreferences();
        if (! $host || empty($host->kpi_card_preferences)) {
            return $defaults;
        }

        return array_merge($defaults, (array) $host->kpi_card_preferences);
    }

    /**
     * Persist host card preferences.
     *
     * @param array<string, bool> $cardPreferences
     * @return array<string, bool>
     */
    public static function savePreferences(Host $host, array $cardPreferences): array
    {
        $validated = [];
        foreach (array_keys(self::CARD_KEYS) as $key) {
            $validated[$key] = (bool) ($cardPreferences[$key] ?? false);
        }

        $host->update(['kpi_card_preferences' => $validated]);

        return $validated;
    }

    /**
     * Reset host card preferences to defaults.
     *
     * @return array<string, bool>
     */
    public static function resetPreferences(Host $host): array
    {
        $defaults = self::defaultPreferences();
        $host->update(['kpi_card_preferences' => $defaults]);

        return $defaults;
    }

    /**
     * Legacy static computation method preserving backward compatibility with Phase 2 Milestone 1.
     *
     * @return array{
     *   properties_count: int,
     *   upcoming_bookings_count: int,
     *   current_guests_count: int,
     *   occupancy_pct: int,
     *   adr_etb: float,
     *   total_revenue_etb: float
     * }
     */
    public static function compute(
        int $propertiesCount,
        int $upcomingBookingsCount,
        int $currentGuestsCount,
        int $bookedUnitNightsInPeriod,
        int $totalAvailableUnitNightsInPeriod,
        float $totalRevenueEtb
    ): array {
        $occupancyPct = $totalAvailableUnitNightsInPeriod > 0
            ? (int) round(($bookedUnitNightsInPeriod / $totalAvailableUnitNightsInPeriod) * 100)
            : 0;

        $adrEtb = $bookedUnitNightsInPeriod > 0
            ? round($totalRevenueEtb / $bookedUnitNightsInPeriod, 2)
            : 0.0;

        return [
            'properties_count' => $propertiesCount,
            'upcoming_bookings_count' => $upcomingBookingsCount,
            'current_guests_count' => $currentGuestsCount,
            'occupancy_pct' => min(100, max(0, $occupancyPct)),
            'adr_etb' => $adrEtb,
            'total_revenue_etb' => round($totalRevenueEtb, 2),
        ];
    }

    /**
     * Calculate comprehensive, real database-backed KPIs scoped to the host.
     *
     * @return array<string, mixed>
     */
    public static function computeFromDatabase(
        Host $host,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $now = CarbonImmutable::now();
        $start = $startDate ? CarbonImmutable::parse($startDate)->startOfDay() : $now->subDays(30)->startOfDay();
        $end = $endDate ? CarbonImmutable::parse($endDate)->endOfDay() : $now->addDays(30)->endOfDay();
        $todayStr = $now->toDateString();

        // 1. Properties & Units Scoped to Host
        $profileIds = StayListingProfile::query()->where('host_id', $host->id)->pluck('id')->all();
        $propertyIds = StayListingProfile::query()->where('host_id', $host->id)->pluck('property_id')->unique()->all();
        $propertiesCount = count($propertyIds);

        $unitsCount = AccommodationUnit::query()
            ->whereIn('property_id', $propertyIds ?: [0])
            ->count();

        $readyUnitsCount = AccommodationUnit::query()
            ->whereIn('property_id', $propertyIds ?: [0])
            ->where('status', 'ready')
            ->count();

        // 2. Bookings Queries
        $allHostBookings = Booking::query()->where('host_id', $host->id);

        $upcomingBookingsCount = (clone $allHostBookings)
            ->where('status', BookingStatus::Confirmed)
            ->whereDate('check_in_date', '>=', $todayStr)
            ->count();

        $currentGuestsCount = (clone $allHostBookings)
            ->whereIn('status', [BookingStatus::CheckedIn, BookingStatus::Active])
            ->count();

        $todayArrivalsCount = (clone $allHostBookings)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->whereDate('check_in_date', $todayStr)
            ->count();

        $todayDeparturesCount = (clone $allHostBookings)
            ->whereIn('status', [BookingStatus::CheckedIn, BookingStatus::Active, BookingStatus::CheckedOut])
            ->whereDate('check_out_date', $todayStr)
            ->count();

        $pendingRequestsCount = (clone $allHostBookings)
            ->where('status', BookingStatus::Pending)
            ->count();

        // 3. Occupancy, ADR & RevPAR in Period
        $periodBookings = (clone $allHostBookings)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::CheckedOut, BookingStatus::Completed])
            ->whereDate('check_in_date', '<=', $end->toDateString())
            ->whereDate('check_out_date', '>=', $start->toDateString())
            ->get();

        $bookedUnitNights = 0;
        $periodRevenueEtb = 0.0;
        $totalNights = 0;

        foreach ($periodBookings as $b) {
            $bIn = CarbonImmutable::parse($b->check_in_date);
            $bOut = CarbonImmutable::parse($b->check_out_date);
            $overlapStart = $bIn->greaterThan($start) ? $bIn : $start;
            $overlapEnd = $bOut->lessThan($end) ? $bOut : $end;
            $nights = max(0, $overlapStart->diffInDays($overlapEnd));
            $bookedUnitNights += $nights;
            $totalNights += $b->nights_count;
            $periodRevenueEtb += (float) $b->accommodation_net_subtotal_etb;
        }

        $daysInPeriod = max(1, $start->diffInDays($end));
        $totalAvailableUnitNights = max(1, $unitsCount * $daysInPeriod);

        $occupancyPct = (int) min(100, round(($bookedUnitNights / $totalAvailableUnitNights) * 100));
        $adrEtb = $bookedUnitNights > 0 ? round($periodRevenueEtb / $bookedUnitNights, 2) : 0.0;
        $revparEtb = $totalAvailableUnitNights > 0 ? round($periodRevenueEtb / $totalAvailableUnitNights, 2) : 0.0;
        $avgLengthOfStay = count($periodBookings) > 0 ? round($totalNights / count($periodBookings), 1) : 0.0;

        // 4. Financial KPIs
        $financialBookings = (clone $allHostBookings)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::CheckedOut, BookingStatus::Completed])
            ->selectRaw('
                COALESCE(SUM(total_amount_etb), 0) as gross_value,
                COALESCE(SUM(accommodation_net_subtotal_etb), 0) as net_subtotal,
                COALESCE(SUM(cleaning_fee_etb), 0) as cleaning_fees,
                COALESCE(SUM(platform_fee_etb), 0) as platform_fees
            ')
            ->first();

        $grossBookingValueEtb = round((float) ($financialBookings?->gross_value ?? 0), 2);
        $accommodationRevenueEtb = round((float) ($financialBookings?->net_subtotal ?? 0), 2);
        $cleaningFeesEtb = round((float) ($financialBookings?->cleaning_fees ?? 0), 2);
        $platformFeesEtb = round((float) ($financialBookings?->platform_fees ?? 0), 2);

        // Security Deposit breakdown
        $depositsStats = SecurityDeposit::query()
            ->where('host_id', $host->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'held' THEN held_amount_etb ELSE 0 END), 0) as held_sum,
                COALESCE(SUM(deducted_amount_etb), 0) as deducted_sum,
                COALESCE(SUM(released_to_guest_etb), 0) as released_sum,
                COALESCE(SUM(CASE WHEN status = 'disputed' THEN held_amount_etb ELSE 0 END), 0) as disputed_sum
            ")
            ->first();

        $depositsHeldEtb = round((float) ($depositsStats?->held_sum ?? 0), 2);
        $depositsDeductedEtb = round((float) ($depositsStats?->deducted_sum ?? 0), 2);
        $depositsReleasedEtb = round((float) ($depositsStats?->released_sum ?? 0), 2);
        $depositsDisputedEtb = round((float) ($depositsStats?->disputed_sum ?? 0), 2);

        $netEarningsEtb = round($accommodationRevenueEtb + $cleaningFeesEtb - $platformFeesEtb + $depositsDeductedEtb, 2);

        // 5. Housekeeping KPIs
        $hostTasks = CleaningTask::query()->where('host_id', $host->id);
        $tasksPending = (clone $hostTasks)->where('status', CleaningTaskStatus::Pending)->count();
        $tasksAssigned = (clone $hostTasks)->where('status', CleaningTaskStatus::Assigned)->count();
        $tasksCleaning = (clone $hostTasks)->where('status', CleaningTaskStatus::Cleaning)->count();
        $tasksInspection = (clone $hostTasks)->where('status', CleaningTaskStatus::Inspection)->count();
        $tasksReady = (clone $hostTasks)->where('status', CleaningTaskStatus::Ready)->count();

        // 6. Reputation KPIs
        $reviewsQuery = StayReview::query()
            ->where('host_id', $host->id)
            ->where('direction', 'guest_to_host')
            ->where('is_published', true);

        $reviewCount = (clone $reviewsQuery)->count();
        $avgCleanliness = round((float) ((clone $reviewsQuery)->avg('cleanliness_rating') ?? 0), 1);
        $avgAccuracy = round((float) ((clone $reviewsQuery)->avg('accuracy_rating') ?? 0), 1);
        $avgCommunication = round((float) ((clone $reviewsQuery)->avg('communication_rating') ?? 0), 1);
        $avgLocation = round((float) ((clone $reviewsQuery)->avg('location_rating') ?? 0), 1);
        $avgValue = round((float) ((clone $reviewsQuery)->avg('value_rating') ?? 0), 1);

        return [
            // General & Property
            'properties_count' => $propertiesCount,
            'bookable_units_count' => $unitsCount,
            'ready_units_count' => $readyUnitsCount,

            // Booking Activity
            'upcoming_bookings_count' => $upcomingBookingsCount,
            'current_guests_count' => $currentGuestsCount,
            'today_arrivals_count' => $todayArrivalsCount,
            'today_departures_count' => $todayDeparturesCount,
            'pending_requests_count' => $pendingRequestsCount,
            'avg_length_of_stay' => $avgLengthOfStay,

            // Occupancy & Rates
            'occupancy_pct' => $occupancyPct,
            'adr_etb' => $adrEtb,
            'revpar_etb' => $revparEtb,
            'booked_unit_nights' => $bookedUnitNights,
            'total_available_unit_nights' => $totalAvailableUnitNights,

            // Financial Breakdown
            'gross_booking_value_etb' => $grossBookingValueEtb,
            'accommodation_revenue_etb' => $accommodationRevenueEtb,
            'cleaning_fees_etb' => $cleaningFeesEtb,
            'platform_fees_etb' => $platformFeesEtb,
            'net_earnings_etb' => $netEarningsEtb,
            'total_revenue_etb' => $netEarningsEtb,

            // Deposits
            'deposits_held_etb' => $depositsHeldEtb,
            'deposits_deducted_etb' => $depositsDeductedEtb,
            'deposits_released_etb' => $depositsReleasedEtb,
            'deposits_disputed_etb' => $depositsDisputedEtb,

            // Housekeeping Pipeline
            'tasks_pending' => $tasksPending,
            'tasks_assigned' => $tasksAssigned,
            'tasks_cleaning' => $tasksCleaning,
            'tasks_inspection' => $tasksInspection,
            'tasks_ready' => $tasksReady,

            // Reputation & Reviews
            'average_rating' => (float) $host->average_rating,
            'reviews_count' => $reviewCount,
            'is_superhost' => (bool) $host->is_superhost,
            'rating_breakdown' => [
                'cleanliness' => $avgCleanliness,
                'accuracy' => $avgAccuracy,
                'communication' => $avgCommunication,
                'location' => $avgLocation,
                'value' => $avgValue,
            ],
        ];
    }
}
