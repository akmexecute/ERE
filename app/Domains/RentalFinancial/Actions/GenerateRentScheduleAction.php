<?php

declare(strict_types=1);

namespace App\Domains\RentalFinancial\Actions;

use App\Domains\Lease\Enums\PaymentFrequency;
use App\Domains\Lease\Models\Lease;
use App\Domains\RentalFinancial\Enums\RentScheduleStatus;
use App\Domains\RentalFinancial\Models\RentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class GenerateRentScheduleAction
{
    /**
     * Generates or idempotently updates persistent rent schedules for a lease contract (SRS Section 153).
     *
     * @return Collection<int, RentSchedule>
     */
    public function execute(Lease $lease, ?Carbon $referenceClock = null): Collection
    {
        $now = $referenceClock ?? now();
        $startDate = $lease->start_date->copy();
        $endDate = $lease->end_date->copy();

        $freq = is_string($lease->payment_frequency)
            ? PaymentFrequency::tryFrom($lease->payment_frequency) ?? PaymentFrequency::Monthly
            : ($lease->payment_frequency instanceof PaymentFrequency ? $lease->payment_frequency : PaymentFrequency::Monthly);

        $intervalMonths = $freq->monthsInterval();
        $dueDayConfig = (int) $lease->due_day_of_month;

        $currentStart = $startDate->copy();
        $sequence = 1;
        $createdSchedules = collect();

        DB::transaction(function () use (
            $lease,
            $now,
            $currentStart,
            $endDate,
            $intervalMonths,
            $dueDayConfig,
            $freq,
            &$sequence,
            &$createdSchedules
        ): void {
            while ($currentStart->lessThan($endDate)) {
                // Calculate period end date (inclusive)
                $periodEnd = $currentStart->copy()->addMonths($intervalMonths)->subDay();
                if ($periodEnd->greaterThan($endDate)) {
                    $periodEnd = $endDate->copy();
                }

                // Calculate due date honoring due_day_of_month without month-end overflow
                $dueYear = $currentStart->year;
                $dueMonth = $currentStart->month;
                $daysInDueMonth = (int) Carbon::create($dueYear, $dueMonth, 1)->daysInMonth;
                $clampedDueDay = min($dueDayConfig, $daysInDueMonth);
                $dueDate = Carbon::create($dueYear, $dueMonth, $clampedDueDay)->startOfDay();

                // Compute base rent for the period
                $baseRent = round(((float) $lease->monthly_rent_etb) * $intervalMonths, 2);

                // Format descriptive localized period label with year
                $periodLabel = $this->buildPeriodLabel($freq, $currentStart, $periodEnd);

                /** @var RentSchedule|null $existing */
                $existing = RentSchedule::query()
                    ->where('lease_id', $lease->id)
                    ->where('period_sequence', $sequence)
                    ->first();

                if (! $existing) {
                    // Initial status calculation
                    $status = RentScheduleStatus::Upcoming;
                    if ($now->toDateString() > $dueDate->toDateString()) {
                        $status = RentScheduleStatus::Overdue;
                    } elseif ($now->toDateString() >= $currentStart->toDateString()) {
                        $status = RentScheduleStatus::Pending;
                    }

                    $schedule = RentSchedule::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'lease_id' => $lease->id,
                        'period_sequence' => $sequence,
                        'period_label' => $periodLabel,
                        'period_start_date' => $currentStart->toDateString(),
                        'period_end_date' => $periodEnd->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'base_rent_etb' => $baseRent,
                        'adjustments_amount_etb' => 0.00,
                        'net_amount_due_etb' => $baseRent,
                        'confirmed_paid_etb' => 0.00,
                        'outstanding_balance_etb' => $baseRent,
                        'status' => $status,
                    ]);
                } else {
                    // Idempotent update: never overwrite payment history or confirmed amounts!
                    $existing->update([
                        'period_label' => $periodLabel,
                        'period_start_date' => $currentStart->toDateString(),
                        'period_end_date' => $periodEnd->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'base_rent_etb' => $baseRent,
                    ]);

                    $existing->reconcileFinancials($now);
                    $schedule = $existing->fresh();
                }

                $createdSchedules->push($schedule);

                // Advance to next interval
                $currentStart = $currentStart->copy()->addMonths($intervalMonths);
                $sequence++;
            }
        });

        return $createdSchedules;
    }

    /**
     * Calculates the deterministic rent periods based on contractual terms without database side effects.
     *
     * @return array<int, array{
     *     sequence_number: int,
     *     period_label: string,
     *     period_start_date: \Carbon\CarbonImmutable,
     *     period_end_date: \Carbon\CarbonImmutable,
     *     due_date: \Carbon\CarbonImmutable,
     *     base_rent_etb: float,
     *     net_amount_due_etb: float
     * }>
     */
    public function calculatePeriods(
        \Carbon\CarbonInterface $startDate,
        \Carbon\CarbonInterface $endDate,
        PaymentFrequency $freq,
        float $monthlyRentEtb,
        int $dueDayConfig
    ): array {
        if (! $startDate->lessThan($endDate)) {
            throw new \InvalidArgumentException('Start date must be strictly before end date.');
        }

        $intervalMonths = $freq->intervalMonths();
        $currentStart = \Carbon\CarbonImmutable::instance($startDate);
        $termEnd = \Carbon\CarbonImmutable::instance($endDate);

        $periods = [];
        $sequence = 1;

        while ($currentStart->lessThan($termEnd)) {
            $periodEnd = $currentStart->addMonths($intervalMonths)->subDay();
            if ($periodEnd->greaterThan($termEnd)) {
                $periodEnd = $termEnd;
            }

            $dueYear = $currentStart->year;
            $dueMonth = $currentStart->month;
            $daysInDueMonth = (int) Carbon::create($dueYear, $dueMonth, 1)->daysInMonth;
            $clampedDueDay = min(max(1, $dueDayConfig), $daysInDueMonth);
            $dueDate = \Carbon\CarbonImmutable::create($dueYear, $dueMonth, $clampedDueDay)->startOfDay();

            $baseRent = round($monthlyRentEtb * $intervalMonths, 2);
            $label = $this->buildPeriodLabel($freq, Carbon::instance($currentStart), Carbon::instance($periodEnd));

            $periods[] = [
                'sequence_number' => $sequence,
                'period_label' => $label,
                'period_start_date' => $currentStart,
                'period_end_date' => $periodEnd,
                'due_date' => $dueDate,
                'base_rent_etb' => $baseRent,
                'net_amount_due_etb' => $baseRent,
            ];

            $currentStart = $currentStart->addMonths($intervalMonths);
            $sequence++;
        }

        return $periods;
    }

    private function buildPeriodLabel(PaymentFrequency $freq, Carbon $start, Carbon $end): string
    {
        return match ($freq) {
            PaymentFrequency::Quarterly => sprintf('Q%d %d (%s - %s)', $start->quarter, $start->year, $start->format('M'), $end->format('M')),
            PaymentFrequency::SemiAnnual => sprintf('H%d %d (%s - %s)', $start->month <= 6 ? 1 : 2, $start->year, $start->format('M'), $end->format('M')),
            PaymentFrequency::Annual => sprintf('Year %d', $start->year),
            default => $start->format('F Y'),
        };
    }
}
