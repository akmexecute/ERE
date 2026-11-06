<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Commission\Actions\CalculateAgentCommissionAction;
use App\Domains\CRM\Enums\CrmLeadSource;
use App\Domains\CRM\Enums\CrmPipelineStage;
use App\Domains\Offer\Actions\RecordNegotiationTurnAction;
use App\Domains\Viewing\Enums\ViewingAppointmentStatus;
use App\Services\CommissionCalculatorService;
use App\Services\CRM\PipelineVelocityEngine;
use Tests\TestCase;

final class Phase3AgentsCrmAndOffersTest extends TestCase
{
    public function test_calculates_srs_section_99_commission_example_and_selects_most_specific_rule(): void
    {
        $calculator = new CalculateAgentCommissionAction();

        // 20,000,000 ETB at 2% = 400,000 ETB gross; Agent split 60% = 240,000 ETB, Company split 40% = 160,000 ETB
        $result = $calculator->execute(
            transactionAmountEtb: 20_000_000.0,
            transactionType: 'sale',
            companyId: 10,
            propertyTypeId: 1,
            agentUserId: 55,
            transactionDate: '2026-10-22',
            candidateRules: [
                [
                    'id' => 1,
                    'name' => 'Company Standard Sale Rule (2%)',
                    'company_id' => 10,
                    'transaction_type' => 'sale',
                    'commission_rate_pct' => 2.0,
                    'agent_split_pct' => 60.0,
                    'company_split_pct' => 40.0,
                ],
            ]
        );

        $this->assertSame(400_000.0, $result['gross_commission_etb']);
        $this->assertSame(240_000.0, $result['agent_share_etb']);
        $this->assertSame(160_000.0, $result['company_share_etb']);
        $this->assertSame(1, $result['matched_rule_id']);
    }

    public function test_enforces_srs_section_98_crm_pipeline_transitions_from_new_lead_to_closed_deal(): void
    {
        $this->assertTrue(CrmPipelineStage::NewLead->canTransitionTo(CrmPipelineStage::Contacted));
        $this->assertTrue(CrmPipelineStage::Contacted->canTransitionTo(CrmPipelineStage::Qualified));
        $this->assertTrue(CrmPipelineStage::Qualified->canTransitionTo(CrmPipelineStage::Viewing));
        $this->assertTrue(CrmPipelineStage::Viewing->canTransitionTo(CrmPipelineStage::Negotiation));
        $this->assertTrue(CrmPipelineStage::Negotiation->canTransitionTo(CrmPipelineStage::Offer));
        $this->assertTrue(CrmPipelineStage::Offer->canTransitionTo(CrmPipelineStage::Closed));
        $this->assertFalse(CrmPipelineStage::Closed->canTransitionTo(CrmPipelineStage::Lost));
        $this->assertTrue(CrmPipelineStage::NewLead->canTransitionTo(CrmPipelineStage::Lost));
    }

    public function test_records_srs_section_77_multi_round_negotiation_ladder(): void
    {
        $engine = new RecordNegotiationTurnAction();
        $asking = 25_000_000.0;

        // Round 1: Buyer offers 22M
        $state = $engine->execute($asking, [], 'buyer', 'offer', 22_000_000.0, 'Initial offer');
        $this->assertSame('submitted', $state['status']);
        $this->assertSame('seller', $state['awaiting_response_from']);

        // Round 2: Seller counters 24M
        $state = $engine->execute($asking, $state['history'], 'seller', 'counteroffer', 24_000_000.0, 'Counter 24M');
        $this->assertSame('countered', $state['status']);
        $this->assertSame('buyer', $state['awaiting_response_from']);

        // Round 3: Buyer counters 23M
        $state = $engine->execute($asking, $state['history'], 'buyer', 'counteroffer', 23_000_000.0, 'Counter 23M');

        // Round 4: Seller counters 23.5M
        $state = $engine->execute($asking, $state['history'], 'seller', 'counteroffer', 23_500_000.0, 'Final counter 23.5M');

        // Round 5: Buyer accepts 23.5M
        $state = $engine->execute($asking, $state['history'], 'buyer', 'accept', null, 'Deal accepted');

        $this->assertSame('accepted', $state['status']);
        $this->assertSame(23_500_000.0, $state['latest_amount_etb']);
        $this->assertSame(1_500_000.0, $state['discount_from_asking_etb']);
        $this->assertCount(5, $state['history']);
        $this->assertNull($state['awaiting_response_from']);
    }

    public function test_commission_calculator_service_computes_default_and_splits_accurately(): void
    {
        $service = new CommissionCalculatorService();

        // 20M ETB on company without custom rule -> default 2% = 400k ETB (50/50 split)
        $calculation = $service->calculate(20_000_000.0, 999999);

        $this->assertSame(400_000.0, $calculation['total_commission']);
        $this->assertSame(200_000.0, $calculation['company_share']);
        $this->assertSame(200_000.0, $calculation['agent_share']);
    }

    public function test_crm_lead_sources_and_viewing_appointment_statuses(): void
    {
        $this->assertSame('telegram', CrmLeadSource::Telegram->value);
        $this->assertSame('whatsapp', CrmLeadSource::WhatsApp->value);
        $this->assertSame('walk_in', CrmLeadSource::WalkIn->value);

        $this->assertFalse(ViewingAppointmentStatus::Requested->isTerminal());
        $this->assertTrue(ViewingAppointmentStatus::Completed->isTerminal());
        $this->assertTrue(ViewingAppointmentStatus::Cancelled->isTerminal());
    }
}
