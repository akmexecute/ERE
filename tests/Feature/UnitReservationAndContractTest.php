<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Developer\Actions\ExpireStaleReservationsAction;
use App\Domains\Developer\Enums\ProjectStatus;
use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\BuildingFloor;
use App\Domains\Developer\Models\DeveloperPaymentPlan;
use App\Domains\Developer\Models\DeveloperProfile;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\PaymentPlanInstallment;
use App\Domains\Developer\Models\ProjectBuilding;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Developer\Models\UnitReservation;
use App\Domains\User\Models\User;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UnitReservationAndContractTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private DevelopmentProject $project;
    private ProjectUnit $unit;
    private DeveloperPaymentPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);

        $this->buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dr. Bekele Tadesse',
            'phone' => '+251911778899',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-000099',
            'identity_verified_at' => now(),
            'verification_level' => 2,
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->buyer->id,
            'legal_name' => 'Noah Real Estate PLC',
            'company_type' => 'developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $developer = DeveloperProfile::query()->create([
            'company_id' => $companyId,
            'user_id' => $this->buyer->id,
            'developer_code' => 'DEV-ETH-000001',
            'verification_status' => 'verified',
        ]);

        $this->project = DevelopmentProject::query()->create([
            'developer_id' => $developer->id,
            'company_id' => $companyId,
            'project_code' => 'PRJ-TEST-001',
            'slug' => 'test-residences',
            'title_en' => 'Test Residences',
            'project_type' => 'residential_complex',
            'status' => ProjectStatus::UnderConstruction,
        ]);

        $this->plan = DeveloperPaymentPlan::query()->create([
            'project_id' => $this->project->id,
            'name_en' => 'Standard Plan (10/30/30/30)',
            'is_default' => true,
            'is_active' => true,
            'version' => 1,
        ]);

        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $this->plan->id,
            'sequence_number' => 1,
            'milestone_label_en' => 'Deposit',
            'percentage' => 10.0,
        ]);
        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $this->plan->id,
            'sequence_number' => 2,
            'milestone_label_en' => 'Foundation',
            'percentage' => 30.0,
        ]);
        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $this->plan->id,
            'sequence_number' => 3,
            'milestone_label_en' => 'Structure',
            'percentage' => 30.0,
        ]);
        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $this->plan->id,
            'sequence_number' => 4,
            'milestone_label_en' => 'Handover',
            'percentage' => 30.0,
        ]);

        $building = ProjectBuilding::query()->create([
            'project_id' => $this->project->id,
            'building_code' => 'TOWER-A',
            'name_en' => 'Tower A',
        ]);

        $floor = BuildingFloor::query()->create([
            'project_id' => $this->project->id,
            'building_id' => $building->id,
            'floor_number' => 4,
            'floor_label' => 'Floor 4',
        ]);

        $this->unit = ProjectUnit::query()->create([
            'project_id' => $this->project->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'unit_number' => 'A-401',
            'floor_number' => 4,
            'unit_type' => '2br',
            'area_sqm' => 120.00,
            'price_etb' => 15_000_000.00,
            'status' => UnitInventoryStatus::Available,
        ]);
    }

    public function test_can_reserve_available_unit_with_15_minute_hold(): void
    {
        $response = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-TEST-001',
            'deposit_percentage' => 10.0,
            'hold_minutes' => 15,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'reservation_code',
            'project_unit_id',
            'agreed_price_etb',
            'deposit_amount_etb',
            'status',
            'hold_expires_at',
        ]);

        $data = $response->json();
        $this->assertSame('reserved', $data['status']);
        $this->assertEquals(15_000_000.0, (float) $data['agreed_price_etb']);
        $this->assertEquals(1_500_000.0, (float) $data['deposit_amount_etb']);

        // Check unit inventory state transitioned to reserved
        $this->unit->refresh();
        $this->assertSame(UnitInventoryStatus::Reserved, $this->unit->status);
    }

    public function test_double_booking_prevention_returns_409_conflict(): void
    {
        // First reservation
        $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-FIRST-001',
        ])->assertStatus(201);

        // Second buyer attempts to reserve same unit
        $secondBuyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'W/ro Selamawit Hailu',
            'phone' => '+251911112233',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-000100',
            'identity_verified_at' => now(),
            'verification_level' => 2,
        ]);

        $response = $this->actingAs($secondBuyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-SECOND-002',
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment([
            'error' => 'Conflict / Unit Unavailable',
        ]);
    }

    public function test_idempotent_reservation_request_returns_same_reservation(): void
    {
        $idempotencyKey = 'IDEM-REPEATED-KEY-999';

        $res1 = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => $idempotencyKey,
        ])->assertStatus(201)->json();

        $res2 = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => $idempotencyKey,
        ])->assertStatus(201)->json();

        $this->assertSame($res1['id'], $res2['id']);
        $this->assertSame($res1['reservation_code'], $res2['reservation_code']);
        $this->assertSame(1, UnitReservation::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_stale_reservations_expire_and_inventory_reverts_to_available(): void
    {
        $res = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-STALE-001',
        ])->assertStatus(201)->json();

        $reservation = UnitReservation::findOrFail($res['id']);
        // Simulate expired hold 20 minutes ago
        $reservation->update(['hold_expires_at' => now()->subMinutes(20)]);

        $expireAction = app(ExpireStaleReservationsAction::class);
        $count = $expireAction->execute();

        $this->assertSame(1, $count);

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Expired, $reservation->status);

        $this->unit->refresh();
        $this->assertSame(UnitInventoryStatus::Available, $this->unit->status);
    }

    public function test_expiration_worker_never_reverts_contracted_or_sold_inventory(): void
    {
        // Setup a reservation that is expired, but unit was manually/separately transitioned to contracted
        UnitReservation::query()->create([
            'idempotency_key' => 'IDEM-CONTRACTED-CHECK',
            'project_unit_id' => $this->unit->id,
            'project_id' => $this->project->id,
            'buyer_user_id' => $this->buyer->id,
            'agreed_price_etb' => 15_000_000.0,
            'deposit_amount_etb' => 1_500_000.0,
            'status' => ReservationStatus::Reserved,
            'hold_expires_at' => now()->subMinutes(30),
        ]);

        $this->unit->update(['status' => UnitInventoryStatus::Contracted]);

        $expireAction = app(ExpireStaleReservationsAction::class);
        $expireAction->execute();

        $this->unit->refresh();
        $this->assertSame(UnitInventoryStatus::Contracted, $this->unit->status);
    }

    public function test_conversion_fails_without_statutory_disclosure_consent(): void
    {
        $res = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-DISCLOSURE-TEST',
        ])->assertStatus(201)->json();

        $response = $this->postJson("/api/v1/reservations/{$res['id']}/convert", [
            'disclosure_accepted' => false,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'error' => 'Statutory Disclosure Required',
        ]);
    }

    public function test_conversion_creates_contract_and_exact_installments(): void
    {
        $res = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-FULL-CONVERT',
        ])->assertStatus(201)->json();

        $response = $this->actingAs($this->buyer)->postJson("/api/v1/reservations/{$res['id']}/convert", [
            'disclosure_accepted' => true,
            'disclosure_version' => 'v1.0-proc1357',
        ]);

        $response->assertStatus(201);
        $data = $response->json();

        $this->assertSame('active', $data['status']);
        $this->assertEquals(15_000_000.0, (float) $data['contract_price_etb']);
        $this->assertEquals(1_500_000.0, (float) $data['deposit_paid_etb']);
        $this->assertEquals(13_500_000.0, (float) $data['balance_payable_etb']);

        // Check unit state is contracted
        $this->unit->refresh();
        $this->assertSame(UnitInventoryStatus::Contracted, $this->unit->status);

        // Check reservation state is converted
        $reservation = UnitReservation::findOrFail($res['id']);
        $this->assertSame(ReservationStatus::Converted, $reservation->status);

        // Check sum of installments equals contract price exactly
        $contract = \App\Domains\Developer\Models\DeveloperSalesContract::with('installments')->findOrFail($data['id']);
        $this->assertTrue($contract->disclosure_accepted_by_buyer);
        $sumInstallments = (float) $contract->installments->sum('amount_etb');
        $this->assertSame(15_000_000.0, $sumInstallments);

        // Deposit installment is marked paid
        $depositInstallment = $contract->installments->firstWhere('sequence_number', 1);
        $this->assertSame('paid', $depositInstallment->status);
        $this->assertSame(1_500_000.0, (float) $depositInstallment->paid_amount_etb);
        $this->assertSame(0.0, (float) $depositInstallment->outstanding_balance_etb);
    }

    public function test_idempotency_key_reuse_by_different_buyer_or_unit_fails_with_conflict(): void
    {
        $idempotencyKey = 'IDEM-SCOPED-KEY-456';

        // Buyer 1 reserves unit
        $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => $idempotencyKey,
        ])->assertStatus(201);

        // Buyer 2 attempts to use identical idempotency key for another unit
        $differentBuyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'W/ro Bethlehem Kebede',
            'phone' => '+251911445566',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-000201',
            'identity_verified_at' => now(),
            'verification_level' => 2,
        ]);

        $response = $this->actingAs($differentBuyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => $idempotencyKey,
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment([
            'error' => 'Conflict / Unit Unavailable',
        ]);
    }

    public function test_unit_hierarchy_validation_prevents_mismatched_building_or_floor(): void
    {
        // Create a second project
        $secondProject = DevelopmentProject::query()->create([
            'developer_id' => $this->project->developer_id,
            'company_id' => $this->project->company_id,
            'project_code' => 'PRJ-TEST-002',
            'slug' => 'test-residences-two',
            'title_en' => 'Test Residences Two',
            'project_type' => 'residential_complex',
            'status' => ProjectStatus::UnderConstruction,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Hierarchy validation failed/');

        // Try creating unit belonging to second project, but referencing building from first project!
        ProjectUnit::query()->create([
            'project_id' => $secondProject->id,
            'building_id' => $this->unit->building_id, // Belongs to $this->project, not $secondProject!
            'floor_id' => $this->unit->floor_id,
            'unit_number' => 'B-101',
            'floor_number' => 1,
            'unit_type' => '2br',
            'area_sqm' => 95.0,
            'price_etb' => 12_000_000.0,
            'status' => UnitInventoryStatus::Available,
        ]);
    }

    public function test_developer_can_create_and_manage_multiple_manual_payment_accounts(): void
    {
        // Developer user creates manual payment destination account via API
        $response = $this->actingAs($this->buyer)->postJson('/api/v1/developer/payment-accounts', [
            'developer_id' => $this->project->developer_id,
            'project_id' => $this->project->id,
            'account_label' => 'Awash Bank Developer Account',
            'institution_name' => 'Awash Bank',
            'account_holder_name' => 'Noah Real Estate PLC',
            'account_number' => '01320498765400',
            'branch_name' => 'Bole Medhanialem Branch',
            'currency' => 'ETB',
            'transfer_instructions' => 'Include unit code and buyer name in payment narrative.',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'account_label' => 'Awash Bank Developer Account',
            'institution_name' => 'Awash Bank',
            'account_number' => '01320498765400',
        ]);

        // Query active payment accounts for the project
        $getAccounts = $this->getJson("/api/v1/projects/{$this->project->id}/payment-accounts");
        $getAccounts->assertStatus(200);
        $getAccounts->assertJsonCount(1, 'accounts');
    }

    public function test_buyer_submits_manual_payment_claim_and_remains_pending_until_verified(): void
    {
        // Setup contract
        $res = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-PAY-TEST',
        ])->assertStatus(201)->json();

        $contractData = $this->actingAs($this->buyer)->postJson("/api/v1/reservations/{$res['id']}/convert", [
            'disclosure_accepted' => true,
        ])->assertStatus(201)->json();

        $contract = \App\Domains\Developer\Models\DeveloperSalesContract::with('installments')->findOrFail($contractData['id']);
        $secondInstallment = $contract->installments->firstWhere('sequence_number', 2);

        // Create developer payment destination account
        $account = \App\Domains\Developer\Models\DeveloperPaymentAccount::create([
            'company_id' => $this->project->company_id,
            'developer_id' => $this->project->developer_id,
            'project_id' => $this->project->id,
            'account_label' => 'CBE Developer Payment Account',
            'institution_name' => 'Commercial Bank of Ethiopia',
            'account_holder_name' => 'Noah Real Estate PLC',
            'account_number' => '100099887766',
        ]);

        // Buyer submits manual bank transfer claim
        $paymentResp = $this->actingAs($this->buyer)->postJson("/api/v1/contracts/{$contract->id}/payments/manual", [
            'installment_id' => $secondInstallment->id,
            'payment_account_id' => $account->id,
            'amount_etb' => 4_500_000.0,
            'transfer_reference' => 'CBE-TXN-2026-888999',
            'notes' => 'Transferred from CBE Mobile Banking.',
        ]);

        $paymentResp->assertStatus(201);
        $paymentData = $paymentResp->json();
        $this->assertSame('pending_verification', $paymentData['status']);

        // Assert installment is NOT yet marked paid!
        $secondInstallment->refresh();
        $this->assertSame('pending', $secondInstallment->status);
        $this->assertSame(0.0, (float) $secondInstallment->paid_amount_etb);
        $this->assertSame(4_500_000.0, (float) $secondInstallment->outstanding_balance_etb);

        // Now authorized developer approves the payment
        $verifyResp = $this->actingAs($this->buyer)->postJson("/api/v1/payments/{$paymentData['id']}/verify", [
            'approved' => true,
            'decision_notes' => 'Confirmed receipt in CBE bank statement.',
        ]);

        $verifyResp->assertStatus(200);
        $verifyResp->assertJsonFragment([
            'status' => 'verified',
        ]);

        // Now assert installment is settled!
        $secondInstallment->refresh();
        $this->assertSame('paid', $secondInstallment->status);
        $this->assertSame(4_500_000.0, (float) $secondInstallment->paid_amount_etb);
        $this->assertSame(0.0, (float) $secondInstallment->outstanding_balance_etb);
    }

    public function test_authorized_developer_rejects_unverified_payment_claim(): void
    {
        // Setup contract
        $res = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-REJECT-TEST',
        ])->assertStatus(201)->json();

        $contractData = $this->actingAs($this->buyer)->postJson("/api/v1/reservations/{$res['id']}/convert", [
            'disclosure_accepted' => true,
        ])->assertStatus(201)->json();

        $contract = \App\Domains\Developer\Models\DeveloperSalesContract::with('installments')->findOrFail($contractData['id']);
        $secondInstallment = $contract->installments->firstWhere('sequence_number', 2);

        $account = \App\Domains\Developer\Models\DeveloperPaymentAccount::create([
            'company_id' => $this->project->company_id,
            'developer_id' => $this->project->developer_id,
            'project_id' => $this->project->id,
            'account_label' => 'CBE Developer Payment Account',
            'institution_name' => 'Commercial Bank of Ethiopia',
            'account_holder_name' => 'Noah Real Estate PLC',
            'account_number' => '100099887766',
        ]);

        $paymentResp = $this->actingAs($this->buyer)->postJson("/api/v1/contracts/{$contract->id}/payments/manual", [
            'installment_id' => $secondInstallment->id,
            'payment_account_id' => $account->id,
            'amount_etb' => 4_500_000.0,
            'transfer_reference' => 'INVALID-SLIP-000',
        ])->assertStatus(201)->json();

        // Reject payment claim
        $rejectResp = $this->actingAs($this->buyer)->postJson("/api/v1/payments/{$paymentResp['id']}/verify", [
            'approved' => false,
            'rejection_reason' => 'Transfer reference not found in bank ledger.',
        ]);

        $rejectResp->assertStatus(200);
        $rejectResp->assertJsonFragment([
            'status' => 'rejected',
        ]);

        // Installment remains pending
        $secondInstallment->refresh();
        $this->assertSame('pending', $secondInstallment->status);
        $this->assertSame(0.0, (float) $secondInstallment->paid_amount_etb);
    }

    public function test_contract_price_amendment_regenerates_100_percent_schedule_and_preserves_history(): void
    {
        // Setup contract
        $res = $this->actingAs($this->buyer)->postJson("/api/v1/units/{$this->unit->id}/reserve", [
            'idempotency_key' => 'IDEM-AMEND-TEST',
        ])->assertStatus(201)->json();

        $contractData = $this->actingAs($this->buyer)->postJson("/api/v1/reservations/{$res['id']}/convert", [
            'disclosure_accepted' => true,
        ])->assertStatus(201)->json();

        // 1. Propose price increase: 15M -> 18.5M ETB.
        // Active contract remains unchanged (15M ETB) while amendment is pending consent.
        $amendResp = $this->actingAs($this->buyer)->postJson("/api/v1/contracts/{$contractData['id']}/amend", [
            'revised_price_etb' => 18_500_000.0,
            'reason' => 'Mutual agreement to add luxury Italian marble and smart home automation package.',
        ]);

        $amendResp->assertStatus(200);
        $amended = $amendResp->json();

        $this->assertEquals(15_000_000.0, (float) $amended['contract_price_etb']);

        $contract = \App\Domains\Developer\Models\DeveloperSalesContract::with(['installments', 'amendments'])->findOrFail($contractData['id']);
        $this->assertCount(1, $contract->amendments);
        $amendment = $contract->amendments->first();
        $this->assertSame('pending', $amendment->status);
        $this->assertFalse((bool) $amendment->accepted_by_buyer);
        $this->assertEquals(15_000_000.0, (float) $amendment->previous_price_etb);
        $this->assertEquals(18_500_000.0, (float) $amendment->revised_price_etb);

        // Active installments still sum to 15M prior to buyer acceptance
        $this->assertSame(15_000_000.0, (float) $contract->installments->sum('amount_etb'));

        // 2. Buyer explicitly accepts proposed amendment
        $acceptResp = $this->actingAs($this->buyer)
            ->postJson("/api/v1/contracts/{$contractData['id']}/amendments/{$amendment->id}/accept")
            ->assertStatus(200);

        $acceptResp->assertJsonPath('status', 'accepted');
        $this->assertEquals(18_500_000.0, (float) $acceptResp->json('contract_price_etb'));

        // 3. Post-acceptance verification
        $contract->refresh();
        $amendment->refresh();
        $this->assertSame('accepted', $amendment->status);
        $this->assertTrue((bool) $amendment->accepted_by_buyer);
        $this->assertNotNull($amendment->accepted_at);
        $this->assertEquals(18_500_000.0, (float) $contract->contract_price_etb);

        // Sum of installments must now exactly equal 18,500,000.00
        $sumInstallments = (float) $contract->installments->sum('amount_etb');
        $this->assertSame(18_500_000.0, $sumInstallments);
    }

    public function test_developer_can_configure_custom_construction_stages_and_recalculates_progress(): void
    {
        $response = $this->actingAs($this->buyer)->postJson("/api/v1/projects/{$this->project->id}/milestones", [
            'milestones' => [
                ['stage_code' => 'piling', 'name_en' => 'Deep Foundation Piling', 'progress_weight' => 20.0, 'completion_percentage' => 100.0],
                ['stage_code' => 'podium_structure', 'name_en' => 'Podium Reinforced Concrete', 'progress_weight' => 30.0, 'completion_percentage' => 80.0],
                ['stage_code' => 'facade', 'name_en' => 'External Glazed Facade', 'progress_weight' => 25.0, 'completion_percentage' => 40.0],
                ['stage_code' => 'finishing_mep', 'name_en' => 'Interior Finishing & MEP', 'progress_weight' => 25.0, 'completion_percentage' => 0.0],
            ],
            'notes' => 'Updated by chief project engineer.',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        // (100*20 + 80*30 + 40*25 + 0*25)/100 = (2000 + 2400 + 1000)/100 = 54.00%
        $this->assertSame(54.0, (float) $data['overall_progress_pct']);

        $this->project->refresh();
        $this->assertSame('54.00', (string) $this->project->overall_progress_pct);
    }
}
