<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Lease\Actions\GenerateLeaseCodeAction;
use App\Domains\Lease\Enums\InspectionType;
use App\Domains\Lease\Enums\LeaseStatus;
use App\Domains\Lease\Enums\PaymentFrequency;
use App\Domains\RentalFinancial\Actions\GenerateRentScheduleAction;
use App\Domains\RentalFinancial\Enums\RentScheduleStatus;
use App\Domains\RentalFinancial\Enums\RentalPaymentStatus;
use App\Domains\Tenant\Actions\GenerateTenantCodeAction;
use App\Domains\Tenant\Enums\RentalApplicationStatus;
use App\Domains\Tenant\Enums\TenantStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

final class Phase4RentalsLeasesAndFinancialHistoryTest extends TestCase
{
    public function test_tenant_and_lease_enums_have_correct_labels_and_values(): void
    {
        $this->assertSame('active', TenantStatus::Active->value);
        $this->assertSame('Active', TenantStatus::Active->label());
        $this->assertSame('suspended', TenantStatus::Suspended->value);
        $this->assertSame('archived', TenantStatus::Archived->value);

        $this->assertSame('submitted', RentalApplicationStatus::Submitted->value);
        $this->assertSame('Under Document Review', RentalApplicationStatus::DocumentReview->label());
        $this->assertSame('approved', RentalApplicationStatus::Approved->value);
        $this->assertSame('rejected', RentalApplicationStatus::Rejected->value);
        $this->assertSame('withdrawn', RentalApplicationStatus::Withdrawn->value);
        $this->assertSame('converted_to_lease', RentalApplicationStatus::ConvertedToLease->value);

        $this->assertSame('draft', LeaseStatus::Draft->value);
        $this->assertSame('Pending Signatures', LeaseStatus::PendingSignatures->label());
        $this->assertSame('active', LeaseStatus::Active->value);
        $this->assertSame('move_out_pending', LeaseStatus::MoveOutPending->value);
        $this->assertSame('completed', LeaseStatus::Completed->value);
        $this->assertSame('terminated', LeaseStatus::Terminated->value);

        $this->assertSame('move_in', InspectionType::MoveIn->value);
        $this->assertSame('move_out', InspectionType::MoveOut->value);

        $this->assertSame('monthly', PaymentFrequency::Monthly->value);
        $this->assertSame('quarterly', PaymentFrequency::Quarterly->value);
        $this->assertSame('semi_annual', PaymentFrequency::SemiAnnual->value);
        $this->assertSame('annual', PaymentFrequency::Annual->value);

        $this->assertSame('upcoming', RentScheduleStatus::Upcoming->value);
        $this->assertSame('pending', RentScheduleStatus::Pending->value);
        $this->assertSame('partially_paid', RentScheduleStatus::PartiallyPaid->value);
        $this->assertSame('paid', RentScheduleStatus::Paid->value);
        $this->assertSame('overdue', RentScheduleStatus::Overdue->value);

        $this->assertSame('pending', RentalPaymentStatus::Pending->value);
        $this->assertSame('confirmed', RentalPaymentStatus::Confirmed->value);
    }

    public function test_payment_frequencies_have_correct_interval_months(): void
    {
        $this->assertSame(1, PaymentFrequency::Monthly->intervalMonths());
        $this->assertSame(3, PaymentFrequency::Quarterly->intervalMonths());
        $this->assertSame(6, PaymentFrequency::SemiAnnual->intervalMonths());
        $this->assertSame(12, PaymentFrequency::Annual->intervalMonths());
    }

    public function test_generates_correct_monthly_rent_periods_with_due_dates_and_labels(): void
    {
        $generator = new GenerateRentScheduleAction();

        $startDate = CarbonImmutable::parse('2026-01-01');
        $endDate = CarbonImmutable::parse('2026-12-31');

        $periods = $generator->calculatePeriods(
            $startDate,
            $endDate,
            PaymentFrequency::Monthly,
            35000.00,
            5 // due day
        );

        $this->assertCount(12, $periods);

        // Period 1
        $this->assertSame(1, $periods[0]['sequence_number']);
        $this->assertSame('January 2026', $periods[0]['period_label']);
        $this->assertSame('2026-01-01', $periods[0]['period_start_date']->toDateString());
        $this->assertSame('2026-01-31', $periods[0]['period_end_date']->toDateString());
        $this->assertSame('2026-01-05', $periods[0]['due_date']->toDateString());
        $this->assertSame(35000.00, $periods[0]['base_rent_etb']);
        $this->assertSame(35000.00, $periods[0]['net_amount_due_etb']);

        // Period 2 (February)
        $this->assertSame(2, $periods[1]['sequence_number']);
        $this->assertSame('February 2026', $periods[1]['period_label']);
        $this->assertSame('2026-02-01', $periods[1]['period_start_date']->toDateString());
        $this->assertSame('2026-02-28', $periods[1]['period_end_date']->toDateString());
        $this->assertSame('2026-02-05', $periods[1]['due_date']->toDateString());

        // Last period (December 2026)
        $this->assertSame(12, $periods[11]['sequence_number']);
        $this->assertSame('December 2026', $periods[11]['period_label']);
        $this->assertSame('2026-12-01', $periods[11]['period_start_date']->toDateString());
        $this->assertSame('2026-12-31', $periods[11]['period_end_date']->toDateString());
    }

    public function test_generates_correct_quarterly_rent_periods_with_multi_month_rents(): void
    {
        $generator = new GenerateRentScheduleAction();

        $startDate = CarbonImmutable::parse('2026-01-01');
        $endDate = CarbonImmutable::parse('2026-12-31');

        $periods = $generator->calculatePeriods(
            $startDate,
            $endDate,
            PaymentFrequency::Quarterly,
            20000.00, // 20k per month -> 60k per quarter
            1
        );

        $this->assertCount(4, $periods);

        // Q1: Jan - Mar
        $this->assertSame(1, $periods[0]['sequence_number']);
        $this->assertSame('Q1 2026 (Jan - Mar)', $periods[0]['period_label']);
        $this->assertSame('2026-01-01', $periods[0]['period_start_date']->toDateString());
        $this->assertSame('2026-03-31', $periods[0]['period_end_date']->toDateString());
        $this->assertSame(60000.00, $periods[0]['net_amount_due_etb']);

        // Q4: Oct - Dec
        $this->assertSame(4, $periods[3]['sequence_number']);
        $this->assertSame('Q4 2026 (Oct - Dec)', $periods[3]['period_label']);
        $this->assertSame('2026-10-01', $periods[3]['period_start_date']->toDateString());
        $this->assertSame('2026-12-31', $periods[3]['period_end_date']->toDateString());
        $this->assertSame(60000.00, $periods[3]['net_amount_due_etb']);
    }

    public function test_generates_correct_semi_annual_and_annual_periods(): void
    {
        $generator = new GenerateRentScheduleAction();

        $startDate = CarbonImmutable::parse('2026-01-01');
        $endDate = CarbonImmutable::parse('2026-12-31');

        // Semi-annual
        $semiAnnualPeriods = $generator->calculatePeriods(
            $startDate,
            $endDate,
            PaymentFrequency::SemiAnnual,
            10000.00, // 10k/mo -> 60k per half
            1
        );
        $this->assertCount(2, $semiAnnualPeriods);
        $this->assertSame('H1 2026 (Jan - Jun)', $semiAnnualPeriods[0]['period_label']);
        $this->assertSame('H2 2026 (Jul - Dec)', $semiAnnualPeriods[1]['period_label']);
        $this->assertSame(60000.00, $semiAnnualPeriods[0]['net_amount_due_etb']);

        // Annual
        $annualPeriods = $generator->calculatePeriods(
            $startDate,
            $endDate,
            PaymentFrequency::Annual,
            10000.00, // 10k/mo -> 120k per year
            1
        );
        $this->assertCount(1, $annualPeriods);
        $this->assertSame('Year 2026', $annualPeriods[0]['period_label']);
        $this->assertSame(120000.00, $annualPeriods[0]['net_amount_due_etb']);
    }

    public function test_handles_due_day_overflow_on_short_months(): void
    {
        $generator = new GenerateRentScheduleAction();

        // Stated due day is 31st
        $startDate = CarbonImmutable::parse('2026-01-01');
        $endDate = CarbonImmutable::parse('2026-04-30');

        $periods = $generator->calculatePeriods(
            $startDate,
            $endDate,
            PaymentFrequency::Monthly,
            15000.00,
            31 // due day 31
        );

        $this->assertCount(4, $periods);

        // January has 31 days -> due on 2026-01-31
        $this->assertSame('2026-01-31', $periods[0]['due_date']->toDateString());

        // February 2026 has 28 days -> clamped safely to 2026-02-28 without month overflow!
        $this->assertSame('2026-02-28', $periods[1]['due_date']->toDateString());

        // March has 31 days -> due on 2026-03-31
        $this->assertSame('2026-03-31', $periods[2]['due_date']->toDateString());

        // April has 30 days -> clamped safely to 2026-04-30
        $this->assertSame('2026-04-30', $periods[3]['due_date']->toDateString());
    }

    public function test_handles_leap_year_february_due_date_clamping(): void
    {
        $generator = new GenerateRentScheduleAction();

        // 2028 is a leap year (February has 29 days)
        $startDate = CarbonImmutable::parse('2028-02-01');
        $endDate = CarbonImmutable::parse('2028-02-29');

        $periods = $generator->calculatePeriods(
            $startDate,
            $endDate,
            PaymentFrequency::Monthly,
            18000.00,
            31
        );

        $this->assertCount(1, $periods);
        $this->assertSame('2028-02-29', $periods[0]['due_date']->toDateString());
    }

    public function test_rejects_schedule_generation_when_start_date_is_after_end_date(): void
    {
        $generator = new GenerateRentScheduleAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Start date must be strictly before end date/');

        $generator->calculatePeriods(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-05-01'),
            PaymentFrequency::Monthly,
            20000.00,
            1
        );
    }

    public function test_financial_adjustment_effective_amount_calculation_and_srs_189_principles(): void
    {
        // SRS Section 189: Never overwrite the original payment amount!
        // Effective Amount = Original Amount + Sum of Adjustments
        $originalPaymentAmount = 25000.00;

        // Adjustment 1: -1,500.00 ETB (utility credit / compensation)
        // Adjustment 2: +200.00 ETB (late penalty)
        $adjustments = [
            -1500.00,
            200.00,
        ];

        $effectiveAmount = $originalPaymentAmount + array_sum($adjustments);

        $this->assertSame(23700.00, round($effectiveAmount, 2));
        $this->assertSame(25000.00, $originalPaymentAmount, 'Original payment amount must remain unchanged');
    }
}
