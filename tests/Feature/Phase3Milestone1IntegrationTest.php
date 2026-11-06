<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Agency\Actions\VerifyAgentProfileAction;
use App\Domains\Agency\Models\AgentProfile;
use App\Domains\Agency\Services\AgentPerformanceMetricsCalculator;
use App\Domains\Commission\Actions\CalculateAgentCommissionAction;
use App\Domains\Commission\Actions\RecordCommissionCalculationAction;
use App\Domains\Commission\Models\CommissionCalculation;
use App\Domains\Commission\Models\CommissionRule;
use App\Domains\Company\Actions\CreateOrUpdateCompanyBranchAction;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Company\Services\CompanyBranchAccessGuard;
use App\Domains\CRM\Actions\TransitionCrmLeadStageAction;
use App\Domains\CRM\Enums\CrmLeadSource;
use App\Domains\CRM\Enums\CrmPipelineStage;
use App\Domains\CRM\Models\CrmLead;
use App\Domains\CRM\Models\CrmLeadActivity;
use App\Domains\CRM\Services\CrmLeadAccessGuard;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Location\Models\Location;
use App\Domains\Offer\Actions\RecordNegotiationTurnAction;
use App\Domains\Offer\Models\OfferNegotiationEvent;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Viewing\Actions\ScheduleViewingAppointmentAction;
use App\Domains\Viewing\Actions\TransitionViewingAppointmentStatusAction;
use App\Domains\Viewing\Enums\ViewingAppointmentStatus;
use App\Domains\Viewing\Models\ViewingAppointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class Phase3Milestone1IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $companyOwner;
    private User $agentUser;
    private User $buyerUser;
    private User $strangerUser;
    private Company $company;
    private Property $property;
    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);

        // 1. Setup Actor Users
        $this->admin = $this->createUser(['name' => 'Platform Super Admin']);
        $this->admin->assignRole('super_admin');

        $this->companyOwner = $this->createUser(['name' => 'Abdi Hassan (Company Owner)']);
        $this->companyOwner->assignRole('property_owner');

        $this->agentUser = $this->createUser(['name' => 'Tewodros Kassahun (Agent)']);
        $this->agentUser->assignRole('agent');

        $this->buyerUser = $this->createUser(['name' => 'Almaz Wolde (Buyer)']);
        $this->buyerUser->assignRole('buyer');

        $this->strangerUser = $this->createUser(['name' => 'Unauthorized Stranger']);

        // 2. Setup Company
        $this->company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->companyOwner->id,
            'legal_name' => 'Horn Real Estate Enterprise PLC',
            'trade_name' => 'Horn Properties Ethiopia',
            'company_type' => 'brokerage',
            'tin_number' => '0098765432',
            'verification_level' => 2,
            'status' => 'active',
        ]);

        // 3. Setup Locations
        $city = Location::query()->where('code', 'AA')->first()
            ?? Location::query()->where('level', 'city')->first()
            ?? Location::query()->firstOrFail();

        $cat = PropertyCategory::query()->where('code', 'RES')->first()
            ?? PropertyCategory::query()->firstOrFail();

        $type = PropertyType::query()->where('code', 'VILLA')->first()
            ?? PropertyType::query()->firstOrFail();

        $listingType = ListingType::query()->where('code', 'FOR_SALE')->first()
            ?? ListingType::query()->where('transaction_mode', 'BUY')->first()
            ?? ListingType::query()->firstOrFail();

        // 4. Setup Property & Listing
        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00009988',
            'owner_id' => $this->companyOwner->id,
            'category_id' => $cat->id,
            'property_type_id' => $type->id,
            'city_id' => $city->id,
            'title_en' => 'Luxury Bole Medhanialem Villa',
            'price_etb' => 25_000_000,
            'status' => 'available',
            'ownership_status' => 'authority_verified',
            'ownership_evidence_type' => 'title_deed',
            'ownership_verified_at' => now(),
            'ownership_legal_disclaimer' => 'Internal platform document review only; not official government land registry certification.',
        ]);

        $this->listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-0009988',
            'property_id' => $this->property->id,
            'listing_type_id' => $listingType->id,
            'published_by_user_id' => $this->companyOwner->id,
            'slug' => 'luxury-bole-medhanialem-villa-' . Str::random(6),
            'price_etb' => 25_000_000,
            'transaction_mode' => 'BUY',
            'status' => 'published',
        ]);
    }

    private function createUser(array $attributes = []): User
    {
        static $counter = 50000;
        $counter++;

        return User::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'User ' . $counter,
            'phone' => '+251922' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
            'status' => 'active',
            'verification_level' => 1,
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-' . $counter,
            'identity_verified_at' => now(),
        ], $attributes));
    }

    // ==========================================
    // MODULE 19: COMPANY & MULTI-BRANCH TESTS
    // ==========================================

    public function test_branch_creation_and_head_office_uniqueness_enforcement(): void
    {
        $action = app(CreateOrUpdateCompanyBranchAction::class);

        // 1. Create Addis Ababa Head Office
        $hq = $action->execute(
            actor: $this->companyOwner,
            companyId: $this->company->id,
            payload: [
                'branch_code' => 'HQ-AA',
                'name_en' => 'Addis Ababa Head Office',
                'name_am' => 'አዲስ አበባ ዋና መሥሪያ ቤት',
                'is_head_office' => true,
                'is_active' => true,
            ]
        );

        $this->assertTrue($hq->is_head_office);
        $this->assertSame('HQ-AA', $hq->branch_code);

        // 2. Create Harar Branch as standard branch
        $hararBranch = $action->execute(
            actor: $this->companyOwner,
            companyId: $this->company->id,
            payload: [
                'branch_code' => 'BR-HAR',
                'name_en' => 'Harar Regional Branch',
                'name_am' => 'ሐረር ቅርንጫፍ',
                'is_head_office' => false,
                'is_active' => true,
            ]
        );

        $this->assertFalse($hararBranch->is_head_office);
        $this->assertTrue($hq->fresh()->is_head_office);

        // 3. Promote Harar to Head Office -> HQ-AA must automatically lose head office status
        $action->execute(
            actor: $this->companyOwner,
            companyId: $this->company->id,
            payload: [
                'branch_code' => 'BR-HAR',
                'name_en' => 'Harar Regional Head Office',
                'is_head_office' => true,
                'is_active' => true,
            ],
            existingBranch: $hararBranch
        );

        $this->assertTrue($hararBranch->fresh()->is_head_office);
        $this->assertFalse($hq->fresh()->is_head_office, 'Previous head office must be unset when a new head office is promoted.');
    }

    public function test_branch_code_must_be_unique_within_same_company(): void
    {
        $action = app(CreateOrUpdateCompanyBranchAction::class);

        $action->execute(
            actor: $this->companyOwner,
            companyId: $this->company->id,
            payload: [
                'branch_code' => 'BR-JIJIGA',
                'name_en' => 'Jijiga Branch',
                'is_head_office' => false,
            ]
        );

        $this->expectException(ValidationException::class);

        // Duplicate branch code in same company must be rejected
        $action->execute(
            actor: $this->companyOwner,
            companyId: $this->company->id,
            payload: [
                'branch_code' => 'BR-JIJIGA',
                'name_en' => 'Duplicate Jijiga Branch',
                'is_head_office' => false,
            ]
        );
    }

    public function test_cross_company_branch_access_is_denied(): void
    {
        $action = app(CreateOrUpdateCompanyBranchAction::class);

        $branch = $action->execute(
            actor: $this->companyOwner,
            companyId: $this->company->id,
            payload: [
                'branch_code' => 'BR-DIREDAWA',
                'name_en' => 'Dire Dawa Branch',
                'is_head_office' => false,
            ]
        );

        $guard = app(CompanyBranchAccessGuard::class);

        // Owner has access
        $this->assertTrue($guard->canManageBranch($this->companyOwner, $branch));

        // Stranger has no access
        $this->assertFalse($guard->canManageBranch($this->strangerUser, $branch));
        $this->assertFalse($guard->canViewBranch($this->strangerUser, $branch));
        $this->assertFalse($guard->validateCompanyScope($this->strangerUser, $this->company->id));
    }

    // ==========================================
    // MODULE 19: AGENT PROFILE & VERIFICATION TESTS
    // ==========================================

    public function test_agent_cannot_self_verify_profile_and_staff_verification_succeeds(): void
    {
        $profile = AgentProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'agent_code' => 'AGT-ETH-000184',
            'user_id' => $this->agentUser->id,
            'company_id' => $this->company->id,
            'license_number' => 'LIC-MOU-2026-9901',
            'specialization' => 'luxury_residential',
            'years_experience' => 5,
            'is_verified_agent' => false,
            'status' => 'pending',
        ]);

        $verifier = app(VerifyAgentProfileAction::class);

        // 1. Agent attempting to self-verify must fail with AuthorizationException
        $this->expectException(AuthorizationException::class);
        $verifier->execute($this->agentUser, $profile);
    }

    public function test_staff_officer_can_verify_agent_profile(): void
    {
        $profile = AgentProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'agent_code' => 'AGT-ETH-000185',
            'user_id' => $this->agentUser->id,
            'company_id' => $this->company->id,
            'license_number' => 'LIC-MOU-2026-9902',
            'is_verified_agent' => false,
            'status' => 'pending',
        ]);

        $verifier = app(VerifyAgentProfileAction::class);
        $updated = $verifier->execute($this->admin, $profile, 'Verified under Proclamation No. 1357/2024');

        $this->assertTrue($updated->is_verified_agent);
        $this->assertSame('verified', $updated->status);
    }

    public function test_agent_public_profile_endpoint_loads_without_private_leakage(): void
    {
        $profile = AgentProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'agent_code' => 'AGT-ETH-000186',
            'user_id' => $this->agentUser->id,
            'company_id' => $this->company->id,
            'license_number' => 'LIC-MOU-2026-9903',
            'bio_en' => 'Experienced real estate broker in Addis Ababa.',
            'bio_am' => 'በአዲስ አበባ ከፍተኛ ልምድ ያለው የሪል እስቴት ደላላ።',
            'is_verified_agent' => true,
            'status' => 'verified',
        ]);

        $response = $this->get('/agents/AGT-ETH-000186');

        $response->assertStatus(200);
        $response->assertSee('AGT-ETH-000186');
        $response->assertSee('Tewodros Kassahun (Agent)');
        $response->assertSee('Horn Properties Ethiopia');
        $response->assertSee('LIC-MOU-2026-9903');
    }

    // ==========================================
    // MODULE 19: COMMISSION ENGINE TESTS
    // ==========================================

    public function test_commission_calculation_srs_reference_example_and_split(): void
    {
        $calculator = app(CalculateAgentCommissionAction::class);

        // 20,000,000 ETB at 2% = 400,000 ETB gross; 60% agent (240k), 40% company (160k)
        $result = $calculator->execute(
            transactionAmountEtb: 20_000_000.0,
            transactionType: 'sale',
            companyId: $this->company->id,
            candidateRules: [
                [
                    'id' => 101,
                    'name' => 'Horn 60/40 Split Rule',
                    'company_id' => $this->company->id,
                    'transaction_type' => 'sale',
                    'commission_rate_pct' => 2.0,
                    'flat_bonus_etb' => 0.0,
                    'agent_split_pct' => 60.0,
                    'company_split_pct' => 40.0,
                ],
            ]
        );

        $this->assertSame(400_000.0, $result['gross_commission_etb']);
        $this->assertSame(240_000.0, $result['agent_share_etb']);
        $this->assertSame(160_000.0, $result['company_share_etb']);
        $this->assertSame(101, $result['matched_rule_id']);
    }

    public function test_commission_rejects_splits_not_totaling_100_percent(): void
    {
        $calculator = app(CalculateAgentCommissionAction::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent and company splits must total 100%');

        $calculator->execute(
            transactionAmountEtb: 10_000_000.0,
            transactionType: 'sale',
            candidateRules: [
                [
                    'id' => 999,
                    'name' => 'Broken Split Rule',
                    'transaction_type' => 'sale',
                    'commission_rate_pct' => 2.0,
                    'agent_split_pct' => 50.0,
                    'company_split_pct' => 30.0, // Only 80% total!
                ],
            ]
        );
    }

    public function test_record_commission_calculation_action_persists_idempotently(): void
    {
        CommissionRule::query()->create([
            'name' => 'Company Sale Commission',
            'company_id' => $this->company->id,
            'transaction_type' => 'sale',
            'commission_rate_pct' => 2.0,
            'agent_split_pct' => 60.0,
            'company_split_pct' => 40.0,
            'is_active' => true,
        ]);

        $recorder = app(RecordCommissionCalculationAction::class);

        // 1. First execution creates calculation ledger row
        $calc1 = $recorder->execute(
            agentUserId: $this->agentUser->id,
            propertyId: $this->property->id,
            transactionValueEtb: 20_000_000.0,
            transactionType: 'sale',
            listingId: $this->listing->id,
            companyId: $this->company->id
        );

        $this->assertInstanceOf(CommissionCalculation::class, $calc1);
        $this->assertSame(400_000.0, (float) $calc1->gross_commission_etb);
        $this->assertSame(240_000.0, (float) $calc1->agent_payout_etb);
        $this->assertSame(160_000.0, (float) $calc1->company_share_etb);
        $this->assertSame('pending_settlement', $calc1->status);

        // 2. Second execution returns existing record idempotently without creating duplicate row
        $calc2 = $recorder->execute(
            agentUserId: $this->agentUser->id,
            propertyId: $this->property->id,
            transactionValueEtb: 20_000_000.0,
            transactionType: 'sale',
            listingId: $this->listing->id,
            companyId: $this->company->id
        );

        $this->assertSame($calc1->id, $calc2->id);
        $this->assertSame(1, CommissionCalculation::query()->where('property_id', $this->property->id)->count());
    }

    // ==========================================
    // MODULE 20: AGENT CRM PIPELINE TESTS
    // ==========================================

    public function test_crm_lead_creation_and_10_supported_sources(): void
    {
        $sources = [
            CrmLeadSource::Website->value,
            CrmLeadSource::Mobile->value,
            CrmLeadSource::Facebook->value,
            CrmLeadSource::Google->value,
            CrmLeadSource::Telegram->value,
            CrmLeadSource::WhatsApp->value,
            CrmLeadSource::Referral->value,
            CrmLeadSource::Phone->value,
            CrmLeadSource::Advertisement->value,
            CrmLeadSource::Agent->value,
        ];

        foreach ($sources as $source) {
            $lead = CrmLead::query()->create([
                'uuid' => (string) Str::uuid(),
                'lead_code' => 'LEAD-SRC-' . strtoupper($source),
                'company_id' => $this->company->id,
                'agent_id' => $this->agentUser->id,
                'assigned_agent_id' => $this->agentUser->id,
                'client_name' => 'Client for ' . $source,
                'client_phone' => '+251911009988',
                'lead_source' => $source,
                'stage' => CrmPipelineStage::NewLead->value,
            ]);

            $this->assertSame($source, $lead->lead_source);
        }

        $this->assertSame(10, CrmLead::query()->where('company_id', $this->company->id)->count());
    }

    public function test_crm_pipeline_stage_transitions_and_history_logging(): void
    {
        $lead = CrmLead::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'agent_id' => $this->agentUser->id,
            'assigned_agent_id' => $this->agentUser->id,
            'client_name' => 'Dawit Tadesse',
            'client_phone' => '+251911443322',
            'lead_source' => 'telegram',
            'stage' => CrmPipelineStage::NewLead->value,
        ]);

        $transitionAction = app(TransitionCrmLeadStageAction::class);

        // 1. Transition NewLead -> Contacted
        $lead = $transitionAction->execute(
            actor: $this->agentUser,
            lead: $lead,
            targetStage: CrmPipelineStage::Contacted,
            notes: 'Contacted via Telegram chat'
        );

        $this->assertSame('contacted', $lead->stage);
        $this->assertNotNull($lead->last_contacted_at);
        $this->assertDatabaseHas('crm_stage_history', [
            'crm_lead_id' => $lead->id,
            'from_stage' => 'new_lead',
            'to_stage' => 'contacted',
        ]);
        $this->assertDatabaseHas('crm_lead_activities', [
            'crm_lead_id' => $lead->id,
            'activity_type' => 'stage_changed',
        ]);

        // 2. Invalid Transition: Contacted -> Closed directly must fail
        $this->expectException(InvalidArgumentException::class);
        $transitionAction->execute(
            actor: $this->agentUser,
            lead: $lead,
            targetStage: CrmPipelineStage::Closed
        );
    }

    public function test_process_crm_follow_ups_command_deduplicates_reminders(): void
    {
        $lead = CrmLead::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'agent_id' => $this->agentUser->id,
            'assigned_agent_id' => $this->agentUser->id,
            'client_name' => 'Hanan Mohammed',
            'client_phone' => '+251911556677',
            'lead_source' => 'whatsapp',
            'stage' => CrmPipelineStage::Qualified->value,
            'next_follow_up_at' => now()->subMinutes(10), // Overdue!
        ]);

        // Run command first time -> records activity
        $this->artisan('crm:process-followups')->assertExitCode(0);

        $this->assertSame(1, CrmLeadActivity::query()->where('crm_lead_id', $lead->id)->where('activity_type', 'follow_up_reminder')->count());

        // Run command second time -> duplicate is skipped
        $this->artisan('crm:process-followups')->assertExitCode(0);

        $this->assertSame(1, CrmLeadActivity::query()->where('crm_lead_id', $lead->id)->where('activity_type', 'follow_up_reminder')->count());
    }

    // ==========================================
    // MODULE 21: VIEWING APPOINTMENTS TESTS
    // ==========================================

    public function test_viewing_appointment_scheduling_and_conflict_prevention(): void
    {
        $scheduler = app(ScheduleViewingAppointmentAction::class);
        $date = now()->addDays(2)->toDateString();
        $slot = '15:00 - 16:00';

        // 1. Schedule first appointment
        $app1 = $scheduler->execute(
            buyer: $this->buyerUser,
            sellerOrAgentUserId: $this->agentUser->id,
            payload: [
                'listing_id' => $this->listing->id,
                'property_id' => $this->property->id,
                'scheduled_date' => $date,
                'scheduled_time_slot' => $slot,
                'viewing_type' => 'in_person',
            ]
        );

        $this->assertSame('requested', $app1->status);

        // Confirm app1
        $transition = app(TransitionViewingAppointmentStatusAction::class);
        $transition->execute($this->agentUser, $app1, ViewingAppointmentStatus::Confirmed, 'Confirmed by agent');

        $this->assertSame('confirmed', $app1->fresh()->status);

        // 2. Attempt to schedule conflicting appointment for same agent & slot
        $anotherBuyer = $this->createUser();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Scheduling conflict');

        $scheduler->execute(
            buyer: $anotherBuyer,
            sellerOrAgentUserId: $this->agentUser->id,
            payload: [
                'listing_id' => $this->listing->id,
                'property_id' => $this->property->id,
                'scheduled_date' => $date,
                'scheduled_time_slot' => $slot,
            ]
        );
    }

    public function test_buyer_cannot_confirm_viewing_on_behalf_of_agent(): void
    {
        $scheduler = app(ScheduleViewingAppointmentAction::class);
        $date = now()->addDays(3)->toDateString();

        $app = $scheduler->execute(
            buyer: $this->buyerUser,
            sellerOrAgentUserId: $this->agentUser->id,
            payload: [
                'listing_id' => $this->listing->id,
                'property_id' => $this->property->id,
                'scheduled_date' => $date,
                'scheduled_time_slot' => '10:00 - 11:00',
            ]
        );

        $transition = app(TransitionViewingAppointmentStatusAction::class);

        $this->expectException(AuthorizationException::class);
        // Buyer attempts to confirm their own appointment -> must be rejected
        $transition->execute($this->buyerUser, $app, ViewingAppointmentStatus::Confirmed);
    }

    // ==========================================
    // MODULE 21: MULTI-ROUND NEGOTIATION TESTS
    // ==========================================

    public function test_srs_reference_negotiation_ladder_persisted_to_database(): void
    {
        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-TEST-001',
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'buyer_user_id' => $this->buyerUser->id,
            'seller_user_id' => $this->companyOwner->id,
            'agent_user_id' => $this->agentUser->id,
            'listed_asking_price_etb' => 25_000_000.0,
            'initial_offer_amount_etb' => 22_000_000.0,
            'latest_amount_etb' => 22_000_000.0,
            'awaiting_response_from' => 'seller',
            'status' => 'submitted',
            'expires_at' => now()->addDays(7),
        ]);

        $turnAction = app(RecordNegotiationTurnAction::class);

        // Round 1 was initial offer (22M). Record Round 1 event:
        OfferNegotiationEvent::query()->create([
            'property_offer_id' => $offer->id,
            'round_number' => 1,
            'actor_party' => 'buyer',
            'actor_user_id' => $this->buyerUser->id,
            'event_type' => 'initial_offer',
            'proposed_amount_etb' => 22_000_000.0,
            'message' => 'Initial offer: ETB 22,000,000',
            'created_at' => now(),
        ]);

        // Round 2: Seller counters at 24,000,000 ETB
        $offer = $turnAction->executeDatabaseTurn(
            actor: $this->companyOwner,
            offer: $offer,
            action: 'counteroffer',
            proposedAmountEtb: 24_000_000.0,
            message: 'Seller counter: ETB 24M'
        );

        $this->assertSame('countered', $offer->status);
        $this->assertSame(24_000_000.0, (float) $offer->latest_amount_etb);
        $this->assertSame('buyer', $offer->awaiting_response_from);

        // Round 3: Buyer counters at 23,000,000 ETB
        $offer = $turnAction->executeDatabaseTurn(
            actor: $this->buyerUser,
            offer: $offer,
            action: 'counteroffer',
            proposedAmountEtb: 23_000_000.0,
            message: 'Buyer counter: ETB 23M'
        );

        $this->assertSame('seller', $offer->awaiting_response_from);
        $this->assertSame(23_000_000.0, (float) $offer->latest_amount_etb);

        // Round 4: Seller counters at 23,500,000 ETB
        $offer = $turnAction->executeDatabaseTurn(
            actor: $this->companyOwner,
            offer: $offer,
            action: 'counteroffer',
            proposedAmountEtb: 23_500_000.0,
            message: 'Seller bottom line: ETB 23.5M'
        );

        $this->assertSame('buyer', $offer->awaiting_response_from);
        $this->assertSame(23_500_000.0, (float) $offer->latest_amount_etb);

        // Round 5: Buyer accepts 23,500,000 ETB
        $offer = $turnAction->executeDatabaseTurn(
            actor: $this->buyerUser,
            offer: $offer,
            action: 'accept',
            message: 'Buyer accepts 23.5M deal.'
        );

        $this->assertSame('accepted', $offer->status);
        $this->assertNull($offer->awaiting_response_from);
        $this->assertNotNull($offer->accepted_at);
        $this->assertSame(5, $offer->events()->count());

        // Verify Price Difference and Discount
        $comp = RecordNegotiationTurnAction::calculatePriceComparison(25_000_000.0, 23_500_000.0);
        $this->assertSame(1_500_000.0, $comp['difference_etb']);
        $this->assertSame(6.0, $comp['discount_pct']);
        $this->assertTrue($comp['is_discount']);

        // Terminal State Guard: Attempting another turn after acceptance must be blocked
        $this->expectException(DomainException::class);
        $turnAction->executeDatabaseTurn(
            actor: $this->companyOwner,
            offer: $offer,
            action: 'counteroffer',
            proposedAmountEtb: 24_000_000.0
        );
    }

    public function test_unauthorized_stranger_cannot_participate_in_negotiation(): void
    {
        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-TEST-002',
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'buyer_user_id' => $this->buyerUser->id,
            'seller_user_id' => $this->companyOwner->id,
            'listed_asking_price_etb' => 25_000_000.0,
            'initial_offer_amount_etb' => 22_000_000.0,
            'latest_amount_etb' => 22_000_000.0,
            'awaiting_response_from' => 'seller',
            'status' => 'submitted',
            'expires_at' => now()->addDays(7),
        ]);

        $turnAction = app(RecordNegotiationTurnAction::class);

        $this->expectException(AuthorizationException::class);
        // Stranger attempts to counter -> must fail
        $turnAction->executeDatabaseTurn(
            actor: $this->strangerUser,
            offer: $offer,
            action: 'counteroffer',
            proposedAmountEtb: 24_000_000.0
        );
    }

    public function test_commission_calculation_decimal_rounding_odd_cents_and_bcmath_precision(): void
    {
        $calculator = app(CalculateAgentCommissionAction::class);

        // Test with 33.33% / 66.67% split on 1,500,000 ETB at 2% = 30,000 gross
        $result = $calculator->execute(
            transactionAmountEtb: 1_500_000.0,
            transactionType: 'sale',
            candidateRules: [
                [
                    'id' => 99,
                    'name' => 'Odd Split Rule',
                    'transaction_type' => 'sale',
                    'commission_rate_pct' => 2.0,
                    'flat_bonus_etb' => 0.0,
                    'agent_split_pct' => 33.33,
                    'company_split_pct' => 66.67,
                    'is_active' => true,
                ],
            ]
        );

        $this->assertSame(30_000.0, $result['gross_commission_etb']);
        $this->assertSame(9_999.0, $result['agent_share_etb']);
        $this->assertSame(20_001.0, $result['company_share_etb']);
        // Verify invariant: agent_share + company_share === gross_commission exactly
        $this->assertSame($result['gross_commission_etb'], round($result['agent_share_etb'] + $result['company_share_etb'], 2));
    }

    public function test_commission_rule_specificity_and_exclusion_of_inactive_rules(): void
    {
        $calculator = app(CalculateAgentCommissionAction::class);

        $rules = [
            [
                'id' => 1,
                'name' => 'Inactive Rule (Must be skipped)',
                'transaction_type' => 'sale',
                'commission_rate_pct' => 5.0,
                'agent_split_pct' => 50.0,
                'company_split_pct' => 50.0,
                'is_active' => false,
            ],
            [
                'id' => 2,
                'name' => 'Expired Rule (Must be skipped)',
                'transaction_type' => 'sale',
                'commission_rate_pct' => 4.0,
                'agent_split_pct' => 50.0,
                'company_split_pct' => 50.0,
                'effective_from' => '2025-01-01',
                'effective_to' => '2025-12-31',
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'General Sale Rule (Score 1)',
                'transaction_type' => 'sale',
                'commission_rate_pct' => 2.0,
                'agent_split_pct' => 50.0,
                'company_split_pct' => 50.0,
                'is_active' => true,
            ],
            [
                'id' => 4,
                'name' => 'Agent-Specific Sale Rule (Score 9, Wins)',
                'agent_user_id' => $this->agentUser->id,
                'transaction_type' => 'sale',
                'commission_rate_pct' => 3.5,
                'agent_split_pct' => 70.0,
                'company_split_pct' => 30.0,
                'is_active' => true,
            ],
        ];

        $result = $calculator->execute(
            transactionAmountEtb: 10_000_000.0,
            transactionType: 'sale',
            agentUserId: $this->agentUser->id,
            transactionDate: '2026-03-15',
            candidateRules: $rules
        );

        $this->assertSame(4, $result['matched_rule_id']);
        $this->assertSame('Agent-Specific Sale Rule (Score 9, Wins)', $result['matched_rule_name']);
        $this->assertSame(3.5, $result['commission_rate_pct']);
        $this->assertSame(350_000.0, $result['gross_commission_etb']);
        $this->assertSame(245_000.0, $result['agent_share_etb']);
        $this->assertSame(105_000.0, $result['company_share_etb']);
    }

    public function test_settled_commission_ledger_immutability(): void
    {
        $recorder = app(RecordCommissionCalculationAction::class);

        $calc = $recorder->execute(
            agentUserId: $this->agentUser->id,
            propertyId: $this->property->id,
            transactionValueEtb: 20_000_000.0,
            transactionType: 'sale',
            listingId: $this->listing->id,
            companyId: $this->company->id
        );

        $this->assertSame('pending_settlement', $calc->status);

        // Approve the calculation
        $calc->update(['status' => CommissionCalculation::STATUS_APPROVED]);
        $this->assertSame('approved', $calc->fresh()->status);

        // Mutating financial figures must throw DomainException
        try {
            $calc->update(['gross_commission_etb' => 999_999.0]);
            $this->fail('Expected DomainException was not thrown when mutating approved commission.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Immutable commission ledger', $e->getMessage());
        }

        // Deleting commission calculation record must throw DomainException
        try {
            $calc->delete();
            $this->fail('Expected DomainException was not thrown when deleting commission calculation.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }
    }

    public function test_viewing_slot_reuse_after_cancellation_and_non_overlapping_slots(): void
    {
        $scheduler = app(ScheduleViewingAppointmentAction::class);
        $transition = app(TransitionViewingAppointmentStatusAction::class);

        $date = now()->addDays(5)->toDateString();
        $slot1 = '09:00 - 10:00';
        $slot2 = '11:00 - 12:00';

        // 1. Schedule and confirm slot 1
        $app1 = $scheduler->execute(
            buyer: $this->buyerUser,
            sellerOrAgentUserId: $this->agentUser->id,
            payload: [
                'listing_id' => $this->listing->id,
                'property_id' => $this->property->id,
                'scheduled_date' => $date,
                'scheduled_time_slot' => $slot1,
            ]
        );
        $transition->execute($this->agentUser, $app1, ViewingAppointmentStatus::Confirmed);

        // 2. Schedule non-overlapping slot 2 for same agent -> must succeed
        $app2 = $scheduler->execute(
            buyer: $this->buyerUser,
            sellerOrAgentUserId: $this->agentUser->id,
            payload: [
                'listing_id' => $this->listing->id,
                'property_id' => $this->property->id,
                'scheduled_date' => $date,
                'scheduled_time_slot' => $slot2,
            ]
        );
        $transition->execute($this->agentUser, $app2, ViewingAppointmentStatus::Confirmed);
        $this->assertSame('confirmed', $app2->fresh()->status);

        // 3. Cancel slot 1
        $transition->execute($this->agentUser, $app1, ViewingAppointmentStatus::Cancelled, 'Cancelled by client request');
        $this->assertSame('cancelled', $app1->fresh()->status);

        // 4. Now another buyer can book and confirm the cancelled slot 1!
        $newBuyer = $this->createUser();
        $app3 = $scheduler->execute(
            buyer: $newBuyer,
            sellerOrAgentUserId: $this->agentUser->id,
            payload: [
                'listing_id' => $this->listing->id,
                'property_id' => $this->property->id,
                'scheduled_date' => $date,
                'scheduled_time_slot' => $slot1,
            ]
        );
        $transition->execute($this->agentUser, $app3, ViewingAppointmentStatus::Confirmed);
        $this->assertSame('confirmed', $app3->fresh()->status);
    }

    public function test_negotiation_historical_events_are_immutable(): void
    {
        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-TEST-IMMUTABLE',
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'buyer_user_id' => $this->buyerUser->id,
            'seller_user_id' => $this->companyOwner->id,
            'listed_asking_price_etb' => 25_000_000.0,
            'initial_offer_amount_etb' => 22_000_000.0,
            'latest_amount_etb' => 22_000_000.0,
            'awaiting_response_from' => 'seller',
            'status' => 'submitted',
            'expires_at' => now()->addDays(7),
        ]);

        $event = OfferNegotiationEvent::query()->create([
            'property_offer_id' => $offer->id,
            'round_number' => 1,
            'actor_party' => 'buyer',
            'actor_user_id' => $this->buyerUser->id,
            'event_type' => 'initial_offer',
            'proposed_amount_etb' => 22_000_000.0,
            'message' => 'Initial buyer offer',
            'created_at' => now(),
        ]);

        // Mutating historical event must throw DomainException
        try {
            $event->update(['proposed_amount_etb' => 99_000_000.0]);
            $this->fail('Expected DomainException was not thrown when mutating negotiation event.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Immutable negotiation event', $e->getMessage());
        }

        // Deleting historical event must throw DomainException
        try {
            $event->delete();
            $this->fail('Expected DomainException was not thrown when deleting negotiation event.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        // Mark offer as accepted
        $offer->update(['status' => 'accepted']);

        // Deleting accepted offer must throw DomainException
        try {
            $offer->delete();
            $this->fail('Expected DomainException was not thrown when deleting accepted offer.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }
    }

    public function test_viewing_time_interval_overlap_detection_1500_to_1600_vs_1530_to_1630(): void
    {
        $scheduler = app(ScheduleViewingAppointmentAction::class);
        $transition = app(TransitionViewingAppointmentStatusAction::class);

        $date = now()->addDays(3)->toDateString();

        // 1. Schedule & confirm appointment at 15:00 - 16:00
        $app1 = $scheduler->execute(
            buyer: $this->buyerUser,
            sellerOrAgentUserId: $this->agentUser->id,
            payload: [
                'listing_id' => $this->listing->id,
                'property_id' => $this->property->id,
                'scheduled_date' => $date,
                'scheduled_time_slot' => '15:00 - 16:00',
            ]
        );
        $transition->execute($this->agentUser, $app1, ViewingAppointmentStatus::Confirmed);

        // 2. Attempt to schedule overlapping appointment at 15:30 - 16:30 for same agent -> must fail with DomainException
        $anotherBuyer = $this->createUser();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Scheduling conflict');

        $scheduler->execute(
            buyer: $anotherBuyer,
            sellerOrAgentUserId: $this->agentUser->id,
            payload: [
                'listing_id' => $this->listing->id,
                'property_id' => $this->property->id,
                'scheduled_date' => $date,
                'scheduled_time_slot' => '15:30 - 16:30',
            ]
        );
    }

    public function test_commission_precision_tiny_amounts_fractional_rates_and_large_values(): void
    {
        $calculator = app(CalculateAgentCommissionAction::class);

        // 1. Tiny valid amount: ETB 10.00 at 2.0% -> 0.20 ETB gross, 0.10 ETB agent, 0.10 ETB company
        $res1 = $calculator->execute(
            transactionAmountEtb: 10.0,
            transactionType: 'sale'
        );
        $this->assertSame(0.20, $res1['gross_commission_etb']);
        $this->assertSame(0.10, $res1['agent_share_etb']);
        $this->assertSame(0.10, $res1['company_share_etb']);
        $this->assertSame($res1['gross_commission_etb'], round($res1['agent_share_etb'] + $res1['company_share_etb'], 2));

        // 2. Fractional rate: ETB 100,000.00 at 1.875% with 60% / 40% split
        $res2 = $calculator->execute(
            transactionAmountEtb: 100_000.0,
            transactionType: 'sale',
            candidateRules: [
                [
                    'id' => 10,
                    'name' => 'Fractional Rate Rule',
                    'transaction_type' => 'sale',
                    'commission_rate_pct' => 1.875,
                    'flat_bonus_etb' => 0.0,
                    'agent_split_pct' => 60.0,
                    'company_split_pct' => 40.0,
                    'is_active' => true,
                ],
            ]
        );
        $this->assertSame(1_875.0, $res2['gross_commission_etb']);
        $this->assertSame(1_125.0, $res2['agent_share_etb']);
        $this->assertSame(750.0, $res2['company_share_etb']);
        $this->assertSame($res2['gross_commission_etb'], round($res2['agent_share_etb'] + $res2['company_share_etb'], 2));

        // 3. Half-cent rounding: ETB 35.00 at 1.5% = 0.525 ETB -> standard half-up rounds to 0.53 ETB
        $res3 = $calculator->execute(
            transactionAmountEtb: 35.0,
            transactionType: 'sale',
            candidateRules: [
                [
                    'id' => 11,
                    'name' => 'Half Cent Rule',
                    'transaction_type' => 'sale',
                    'commission_rate_pct' => 1.5,
                    'agent_split_pct' => 50.0,
                    'company_split_pct' => 50.0,
                    'is_active' => true,
                ],
            ]
        );
        $this->assertSame(0.53, $res3['gross_commission_etb']);
        $this->assertSame(0.27, $res3['agent_share_etb']);
        $this->assertSame(0.26, $res3['company_share_etb']);
        $this->assertSame($res3['gross_commission_etb'], round($res3['agent_share_etb'] + $res3['company_share_etb'], 2));

        // 4. Large amount: ETB 100,000,000.00 at 2.5% = 2,500,000.00 ETB
        $res4 = $calculator->execute(
            transactionAmountEtb: 100_000_000.0,
            transactionType: 'sale',
            candidateRules: [
                [
                    'id' => 12,
                    'name' => 'Mega Deal Rule',
                    'transaction_type' => 'sale',
                    'commission_rate_pct' => 2.5,
                    'agent_split_pct' => 50.0,
                    'company_split_pct' => 50.0,
                    'is_active' => true,
                ],
            ]
        );
        $this->assertSame(2_500_000.0, $res4['gross_commission_etb']);
        $this->assertSame(1_250_000.0, $res4['agent_share_etb']);
        $this->assertSame(1_250_000.0, $res4['company_share_etb']);
    }

    public function test_commission_split_validation_strictly_rejects_non_100_percent_sums(): void
    {
        $calculator = app(CalculateAgentCommissionAction::class);

        // 100.01% -> must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent and company splits must total 100%');

        $calculator->execute(
            transactionAmountEtb: 100_000.0,
            transactionType: 'sale',
            candidateRules: [
                [
                    'id' => 99,
                    'name' => 'Slightly Over 100 Rule',
                    'transaction_type' => 'sale',
                    'commission_rate_pct' => 2.0,
                    'agent_split_pct' => 50.01,
                    'company_split_pct' => 50.00,
                    'is_active' => true,
                ],
            ]
        );
    }

    public function test_commission_idempotency_with_transaction_reference(): void
    {
        $recorder = app(RecordCommissionCalculationAction::class);

        $txRef1 = 'TX-SALE-2026-0001';
        $txRef2 = 'TX-RENT-2027-0002';

        // 1. First record creation for deal 1
        $calc1 = $recorder->execute(
            agentUserId: $this->agentUser->id,
            propertyId: $this->property->id,
            transactionValueEtb: 15_000_000.0,
            transactionType: 'sale',
            listingId: $this->listing->id,
            companyId: $this->company->id,
            transactionReference: $txRef1
        );

        $this->assertSame($txRef1, $calc1->transaction_reference);

        // 2. Duplicate call for exact same transaction reference -> returns existing record without duplicating
        $calc1Duplicate = $recorder->execute(
            agentUserId: $this->agentUser->id,
            propertyId: $this->property->id,
            transactionValueEtb: 15_000_000.0,
            transactionType: 'sale',
            listingId: $this->listing->id,
            companyId: $this->company->id,
            transactionReference: $txRef1
        );

        $this->assertSame($calc1->id, $calc1Duplicate->id);
        $this->assertSame(1, CommissionCalculation::query()->where('transaction_reference', $txRef1)->count());

        // 3. Legitimate subsequent transaction on SAME property (e.g. lease/rental next year) -> allowed!
        $calc2 = $recorder->execute(
            agentUserId: $this->agentUser->id,
            propertyId: $this->property->id,
            transactionValueEtb: 250_000.0,
            transactionType: 'rental',
            listingId: null,
            companyId: $this->company->id,
            transactionReference: $txRef2
        );

        $this->assertNotSame($calc1->id, $calc2->id);
        $this->assertSame($txRef2, $calc2->transaction_reference);
        $this->assertSame(2, CommissionCalculation::query()->where('property_id', $this->property->id)->count());
    }

    public function test_company_branch_model_saving_hook_enforces_single_active_head_office(): void
    {
        // Direct model save for branch 1 as head office
        $branch1 = CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'branch_code' => 'TEST-HQ1',
            'name_en' => 'Test Branch 1',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($branch1->fresh()->is_head_office);

        // Direct model save for branch 2 as head office -> branch 1 is automatically unset
        $branch2 = CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'branch_code' => 'TEST-HQ2',
            'name_en' => 'Test Branch 2',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($branch2->fresh()->is_head_office);
        $this->assertFalse($branch1->fresh()->is_head_office);
        $this->assertSame(1, CompanyBranch::query()->where('company_id', $this->company->id)->where('is_head_office', true)->count());
    }

    public function test_crm_dashboard_and_velocity_engine_strictly_scope_queries_per_agent(): void
    {
        $engine = app(\App\Services\CRM\PipelineVelocityEngine::class);

        // Create an unauthorized peer agent
        $peerAgent = $this->createUser(['name' => 'Peer Agent']);
        $peerAgent->assignRole('agent');

        // Create a lead assigned to peer agent
        CrmLead::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'agent_id' => $peerAgent->id,
            'assigned_agent_id' => $peerAgent->id,
            'client_name' => 'Confidential Client of Peer',
            'client_phone' => '+251999887766',
            'lead_source' => 'website',
            'stage' => 'new_lead',
        ]);

        // When $this->agentUser requests stats, they must NOT see peer agent's confidential lead
        $statsForAgent = $engine->computeDashboardStats(user: $this->agentUser);
        $this->assertSame(0, $statsForAgent['kpis']['total_leads']);

        // When $peerAgent requests stats, they see their 1 lead
        $statsForPeer = $engine->computeDashboardStats(user: $peerAgent);
        $this->assertSame(1, $statsForPeer['kpis']['total_leads']);
    }
}
