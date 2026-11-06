<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Developer\Actions\CalculateInstallmentScheduleAction;
use App\Domains\Developer\Actions\CalculateProjectConstructionProgressAction;
use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use InvalidArgumentException;
use Tests\TestCase;

final class Phase3DeveloperAndProjectsTest extends TestCase
{
    public function test_calculates_weighted_construction_progress_accurately_with_conventional_rounding(): void
    {
        $calculator = new CalculateProjectConstructionProgressAction();

        // SRS Section 90 Test Case:
        // Foundation 100%, Structure 70%, Masonry 45%, Finishing 20% with equal 25% weights -> 58.75%
        $milestones = [
            ['stage_code' => 'foundation', 'progress_weight' => 25.0, 'completion_percentage' => 100.0],
            ['stage_code' => 'structure', 'progress_weight' => 25.0, 'completion_percentage' => 70.0],
            ['stage_code' => 'masonry', 'progress_weight' => 25.0, 'completion_percentage' => 45.0],
            ['stage_code' => 'finishing', 'progress_weight' => 25.0, 'completion_percentage' => 20.0],
        ];

        $result = $calculator->execute($milestones);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(100.0, $result['total_weight']);
        $this->assertSame(58.75, $result['overall_progress_pct']);
        $this->assertSame('weighted_average_conventional_rounding', $result['calculation_method']);
    }

    public function test_rejects_milestone_configuration_when_weights_do_not_sum_to_100_percent(): void
    {
        $calculator = new CalculateProjectConstructionProgressAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must total exactly 100.00%/');

        $calculator->execute([
            ['stage_code' => 'foundation', 'progress_weight' => 30.0, 'completion_percentage' => 100.0],
            ['stage_code' => 'structure', 'progress_weight' => 30.0, 'completion_percentage' => 50.0],
            // Sum is 60%, not 100%
        ]);
    }

    public function test_rejects_invalid_milestone_completion_percentages(): void
    {
        $calculator = new CalculateProjectConstructionProgressAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/between 0.00% and 100.00%/');

        $calculator->execute([
            ['stage_code' => 'foundation', 'progress_weight' => 50.0, 'completion_percentage' => 110.0],
            ['stage_code' => 'structure', 'progress_weight' => 50.0, 'completion_percentage' => 50.0],
        ]);
    }

    public function test_calculates_installment_schedule_with_exact_final_installment_cent_absorption(): void
    {
        $calculator = new CalculateInstallmentScheduleAction();

        // Contract price with uneven cents: 15,250,750.55 ETB
        $contractPrice = 15_250_750.55;

        // 6-installment standard plan: 10%, 20%, 20%, 20%, 20%, 10%
        $plan = [
            ['sequence_number' => 1, 'milestone_label_en' => 'Reservation Deposit', 'percentage' => 10.0],
            ['sequence_number' => 2, 'milestone_label_en' => 'Foundation', 'percentage' => 20.0],
            ['sequence_number' => 3, 'milestone_label_en' => 'Structure', 'percentage' => 20.0],
            ['sequence_number' => 4, 'milestone_label_en' => 'Roofing', 'percentage' => 20.0],
            ['sequence_number' => 5, 'milestone_label_en' => 'Finishing', 'percentage' => 20.0],
            ['sequence_number' => 6, 'milestone_label_en' => 'Handover', 'percentage' => 10.0],
        ];

        $schedule = $calculator->execute($contractPrice, $plan);

        $this->assertSame(100.0, $schedule['total_percentage']);
        $this->assertCount(6, $schedule['schedule']);

        // Sum of all installments must equal contract price exactly
        $calculatedTotal = array_sum(array_column($schedule['schedule'], 'amount_etb'));
        $this->assertEqualsWithDelta($contractPrice, $calculatedTotal, 0.0001);

        // Sequence 1 is 10%
        $this->assertSame(1_525_075.06, $schedule['schedule'][0]['amount_etb']);
    }

    public function test_rejects_payment_plans_whose_percentages_do_not_equal_100_percent(): void
    {
        $calculator = new CalculateInstallmentScheduleAction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must total exactly 100.00%/');

        $calculator->execute(10_000_000.0, [
            ['sequence_number' => 1, 'milestone_label_en' => 'Deposit', 'percentage' => 15.0],
            ['sequence_number' => 2, 'milestone_label_en' => 'Foundation', 'percentage' => 20.0],
            // Sum is 35%
        ]);
    }

    public function test_enforces_authoritative_unit_inventory_status_transitions(): void
    {
        $available = UnitInventoryStatus::Available;
        $reserved = UnitInventoryStatus::Reserved;
        $contracted = UnitInventoryStatus::Contracted;
        $sold = UnitInventoryStatus::Sold;
        $blocked = UnitInventoryStatus::Blocked;

        // Available can transition to Reserved or Blocked
        $this->assertTrue($available->canTransitionTo($reserved));
        $this->assertTrue($available->canTransitionTo($blocked));
        $this->assertFalse($available->canTransitionTo($sold));

        // Reserved can transition to Available (if hold expires/cancelled) or Contracted
        $this->assertTrue($reserved->canTransitionTo($available));
        $this->assertTrue($reserved->canTransitionTo($contracted));

        // Contracted can transition to Sold or Available (if terminated)
        $this->assertTrue($contracted->canTransitionTo($sold));
        $this->assertTrue($contracted->canTransitionTo($available));

        // Sold is terminal!
        $this->assertFalse($sold->canTransitionTo($available));
        $this->assertFalse($sold->canTransitionTo($reserved));
    }

    public function test_reservation_status_transitions(): void
    {
        $this->assertTrue(ReservationStatus::Reserved->isActive());
        $this->assertTrue(ReservationStatus::PendingPayment->isActive());
        $this->assertFalse(ReservationStatus::Expired->isActive());
        $this->assertFalse(ReservationStatus::Converted->isActive());

        $this->assertTrue(ReservationStatus::Reserved->canTransitionTo(ReservationStatus::Converted));
        $this->assertTrue(ReservationStatus::Reserved->canTransitionTo(ReservationStatus::Expired));
        $this->assertFalse(ReservationStatus::Converted->canTransitionTo(ReservationStatus::Reserved));
    }

    public function test_custom_construction_stages_with_custom_names_and_consistent_rounding(): void
    {
        $calculator = new CalculateProjectConstructionProgressAction();

        // Custom stages: excavation (10%), structural_steel (30%), curtain_wall (25%), interior_fitout (25%), commissioning (10%) = 100%
        $customStages = [
            ['stage_code' => 'excavation', 'name_en' => 'Deep Excavation & Shoring', 'progress_weight' => 10.0, 'completion_percentage' => 100.0],
            ['stage_code' => 'structural_steel', 'name_en' => 'Heavy Structural Steel', 'progress_weight' => 30.0, 'completion_percentage' => 80.0],
            ['stage_code' => 'curtain_wall', 'name_en' => 'Double Glazed Curtain Wall', 'progress_weight' => 25.0, 'completion_percentage' => 40.0],
            ['stage_code' => 'interior_fitout', 'name_en' => 'Luxury Interior Fitout', 'progress_weight' => 25.0, 'completion_percentage' => 20.0],
            ['stage_code' => 'commissioning', 'name_en' => 'MEP Testing & Commissioning', 'progress_weight' => 10.0, 'completion_percentage' => 0.0],
        ];

        // Weighted progress: (100*10 + 80*30 + 40*25 + 20*25 + 0*10) / 100 = (1000 + 2400 + 1000 + 500) / 100 = 4900 / 100 = 49.00%
        $result = $calculator->execute($customStages);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(100.0, $result['total_weight']);
        $this->assertSame(49.0, $result['overall_progress_pct']);
        $this->assertSame(49, $result['overall_progress_floor_pct']);
        $this->assertSame(49, $result['overall_progress_round_pct']);
    }

    public function test_building_media_helpers_distinguish_renders_from_actual_photos(): void
    {
        $building = new \App\Domains\Developer\Models\ProjectBuilding([
            'building_code' => 'TOWER-C',
            'name_en' => 'Tower C',
            'media_json' => [
                ['type' => 'architectural_render', 'url' => '/media/render1.jpg', 'caption' => '3D Render of South Elevation'],
                ['type' => 'architectural_render', 'url' => '/media/render2.jpg', 'caption' => 'Lobby Interior Concept'],
                ['type' => 'construction_photo', 'url' => '/media/actual_foundation.jpg', 'caption' => 'Raft Foundation Concrete Pour'],
                ['type' => 'completed_photo', 'url' => '/media/exterior_finished.jpg', 'caption' => 'Finished Tower Facade'],
            ],
        ]);

        $renders = $building->architectural_renders;
        $constructionPhotos = $building->construction_photos;
        $completedPhotos = $building->completed_photos;

        $this->assertCount(2, $renders);
        $this->assertSame('3D Render of South Elevation', $renders[0]['caption']);

        $this->assertCount(1, $constructionPhotos);
        $this->assertSame('Raft Foundation Concrete Pour', $constructionPhotos[0]['caption']);

        $this->assertCount(1, $completedPhotos);
        $this->assertSame('Finished Tower Facade', $completedPhotos[0]['caption']);
    }
}
