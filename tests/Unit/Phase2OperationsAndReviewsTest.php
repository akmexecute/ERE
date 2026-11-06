<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Dispute\Actions\SettleSecurityDepositClaimAction;
use App\Domains\Host\Services\HostDashboardKpiCalculator;
use App\Domains\Review\Actions\EvaluateTwoWayReviewSubmissionAction;
use App\Domains\Stay\Actions\ResolveAutomatedStayTriggersAction;
use PHPUnit\Framework\TestCase;

final class Phase2OperationsAndReviewsTest extends TestCase
{
    public function test_enforces_srs_section_63_housekeeping_state_machine_transitions_from_checkout_to_ready(): void
    {
        $this->assertTrue(CleaningTaskStatus::Pending->canTransitionTo(CleaningTaskStatus::Assigned));
        $this->assertTrue(CleaningTaskStatus::Assigned->canTransitionTo(CleaningTaskStatus::Cleaning));
        $this->assertTrue(CleaningTaskStatus::Cleaning->canTransitionTo(CleaningTaskStatus::Inspection));
        $this->assertTrue(CleaningTaskStatus::Inspection->canTransitionTo(CleaningTaskStatus::Ready));
        $this->assertFalse(CleaningTaskStatus::Ready->canTransitionTo(CleaningTaskStatus::Cleaning));
    }

    public function test_resolves_srs_section_112_automated_short_stay_message_triggers_accurately(): void
    {
        $resolver = new ResolveAutomatedStayTriggersAction();

        // 7 days before check-in on a newly confirmed booking -> sends both confirmation and 7-day reminder
        $triggers = $resolver->execute(
            bookingStatus: 'confirmed',
            checkInDate: '2026-11-10',
            checkOutDate: '2026-11-15',
            referenceDate: '2026-11-03',
            alreadySentTriggers: []
        );

        $this->assertContains('booking_confirmation', $triggers);
        $this->assertContains('days_before_7', $triggers);
    }

    public function test_settles_srs_section_66_and_67_security_deposit_damage_claims_for_both_guest_acceptance_and_dispute_escalation(): void
    {
        $settler = new SettleSecurityDepositClaimAction();

        // Guest accepts 1,500 ETB damage claim out of 5,000 ETB deposit -> Partially Deducted, 3,500 ETB released
        $accepted = $settler->execute(5000.0, 1500.0, 'accept');
        $this->assertSame('partially_deducted', $accepted['deposit_status']);
        $this->assertEquals(1500.0, $accepted['deducted_amount_etb']);
        $this->assertEquals(3500.0, $accepted['released_to_guest_etb']);
        $this->assertFalse($accepted['dispute_opened']);

        // Guest disputes claim -> Deposit locked as disputed, formal Dispute opened in Evidence stage (SRS Section 68)
        $disputed = $settler->execute(5000.0, 1500.0, 'dispute');
        $this->assertSame('disputed', $disputed['deposit_status']);
        $this->assertTrue($disputed['dispute_opened']);
        $this->assertSame('evidence', $disputed['dispute_stage']);
    }

    public function test_evaluates_srs_section_69_and_70_two_way_double_blind_reviews_and_computes_srs_section_55_host_kpis(): void
    {
        $reviewEvaluator = new EvaluateTwoWayReviewSubmissionAction();

        $guestReview = $reviewEvaluator->execute(
            direction: 'guest_to_host',
            scores: [
                'cleanliness' => 5,
                'accuracy' => 5,
                'communication' => 5,
                'location' => 4,
                'value' => 5,
            ],
            comment: 'Wonderful stay in Bole with reliable backup generator and fast Wi-Fi!',
            counterpartyAlreadySubmitted: true
        );

        $this->assertEquals(4.8, $guestReview['computed_overall_rating']);
        $this->assertSame('approved', $guestReview['moderation_status']);
        $this->assertTrue($guestReview['publish_both_now']);

        // SRS Section 55 Host Dashboard KPI Example: 12 properties, 8 upcoming, 4 current guests, 72% occupancy, 850,000 ETB revenue
        $kpis = HostDashboardKpiCalculator::compute(
            propertiesCount: 12,
            upcomingBookingsCount: 8,
            currentGuestsCount: 4,
            bookedUnitNightsInPeriod: 72,
            totalAvailableUnitNightsInPeriod: 100,
            totalRevenueEtb: 850_000.0
        );

        $this->assertSame(72, $kpis['occupancy_pct']);
        $this->assertEquals(850_000.0, $kpis['total_revenue_etb']);
    }
}
