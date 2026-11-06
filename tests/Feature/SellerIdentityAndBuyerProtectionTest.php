<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Developer\Actions\ConvertReservationToContractAction;
use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\SalesContractStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\ProjectBuilding;
use App\Domains\Developer\Models\BuildingFloor;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Developer\Models\UnitReservation;
use App\Domains\Identity\Services\IdentityGateService;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use App\Domains\Verification\Actions\ReviewVerificationRequestAction;
use App\Domains\Verification\Enums\VerificationLevel;
use App\Domains\Verification\Models\Verification;
use App\Models\Company;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SellerIdentityAndBuyerProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
    }

    public function test_unverified_seller_cannot_pass_seller_verification_level_requirements(): void
    {
        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unverified Seller Abebe',
            'phone' => '+251911000001',
            'verification_level' => 0,
            'status' => 'active',
        ]);

        $gate = app(IdentityGateService::class);
        $check = $gate->check($seller, IdentityGateService::ACTION_PUBLISH_LISTING);

        // Without phone and national ID, seller fails gate
        $this->assertFalse($check['allowed']);
        $this->assertContains('national_id_number', $check['missing']);
    }

    public function test_seller_cannot_self_approve_verification_request(): void
    {
        // An officer who also acts as a seller
        $officerSeller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Officer Seller Kassahun',
            'phone' => '+251911000002',
            'verification_level' => 0,
            'status' => 'active',
        ]);
        $officerSeller->assignRole('verification_officer');

        $verification = Verification::query()->create([
            'uuid' => (string) Str::uuid(),
            'verifiable_type' => 'user',
            'verifiable_id' => $officerSeller->id,
            'requested_by_user_id' => $officerSeller->id,
            'requested_level' => 2,
            'approved_level' => VerificationLevel::Unverified,
            'status' => 'submitted',
        ]);

        $action = app(ReviewVerificationRequestAction::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Self-approval of identity or seller verification is strictly forbidden');

        $action->execute(
            officer: $officerSeller,
            verificationId: $verification->id,
            decision: 'approved',
            grantedLevel: VerificationLevel::SellerOrCompanyVerified,
            reason: 'Self-certifying my own documents'
        );
    }

    public function test_authorized_independent_reviewer_can_verify_seller_identity(): void
    {
        $independentOfficer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Independent Compliance Officer',
            'phone' => '+251911000003',
            'status' => 'active',
        ]);
        $independentOfficer->assignRole('verification_officer');

        $seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Prospective Seller Genet',
            'phone' => '+251911000004',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-9900-1122-3344',
            'national_id_type' => 'fayda',
            'verification_level' => 0,
            'status' => 'active',
        ]);

        $verification = Verification::query()->create([
            'uuid' => (string) Str::uuid(),
            'verifiable_type' => 'user',
            'verifiable_id' => $seller->id,
            'requested_by_user_id' => $seller->id,
            'requested_level' => 2,
            'approved_level' => VerificationLevel::Unverified,
            'status' => 'submitted',
        ]);

        $action = app(ReviewVerificationRequestAction::class);
        $action->execute(
            officer: $independentOfficer,
            verificationId: $verification->id,
            decision: 'approved',
            grantedLevel: VerificationLevel::SellerOrCompanyVerified,
            reason: 'Trade license and National ID verified against commercial registry records'
        );

        $seller->refresh();
        $this->assertSame(2, $seller->verification_level);
        $this->assertNotNull($seller->identity_verified_at);

        $verification->refresh();
        $this->assertSame('approved', $verification->status);
        $this->assertSame(2, $verification->approved_level->value);
    }

    public function test_sales_contract_conversion_captures_immutable_seller_identity_snapshot(): void
    {
        $fixture = $this->createContractFixtures();
        $reservation = $fixture['reservation'];

        $action = app(ConvertReservationToContractAction::class);
        $contract = $action->execute(
            reservationId: $reservation->id,
            disclosureAccepted: true,
            clientIp: '197.156.104.22'
        );

        $this->assertSame(SalesContractStatus::Active, $contract->status);
        $this->assertNotNull($contract->seller_identity_snapshot_json);

        $snapshot = $contract->seller_identity_snapshot_json;
        $this->assertSame('developer_company', $snapshot['seller_type']);
        $this->assertSame('Sunshine Real Estate PLC', $snapshot['company_legal_name']);
        $this->assertSame('TIN-009182741', $snapshot['tax_identification_number']);
        $this->assertSame('BL/AA/2022/9901', $snapshot['business_license_number']);
        $this->assertSame('internal_manual_review', $snapshot['verification_method']);
        $this->assertFalse($snapshot['is_official_fayda_api_verified']);
        $this->assertSame('Authorized Agent Solomon', $snapshot['authorized_representative']['name']);
        $this->assertStringStartsWith('+251911', $snapshot['authorized_representative']['masked_phone']);
    }

    public function test_buyer_can_access_seller_identity_record_for_their_own_purchase(): void
    {
        $fixture = $this->createContractFixtures();
        $reservation = $fixture['reservation'];
        $buyer = $fixture['buyer'];

        $action = app(ConvertReservationToContractAction::class);
        $contract = $action->execute(
            reservationId: $reservation->id,
            disclosureAccepted: true,
            clientIp: '197.156.104.22'
        );

        $response = $this->actingAs($buyer)->get("/buyer/contracts/{$contract->contract_code}");
        $response->assertOk();
        $response->assertSee('Verified Seller &amp; Developer Identity Record', false);
        $response->assertSee('Sunshine Real Estate PLC');
        $response->assertSee('TIN-009182741');
        $response->assertSee('Authorized Agent Solomon');

        // Full National ID of signer or seller must NOT be exposed
        $response->assertDontSee('FAN-9900-1122-3344');
    }

    public function test_buyer_cannot_access_unrelated_buyers_contract_record(): void
    {
        $fixture = $this->createContractFixtures();
        $reservation = $fixture['reservation'];

        $action = app(ConvertReservationToContractAction::class);
        $contract = $action->execute(
            reservationId: $reservation->id,
            disclosureAccepted: true,
            clientIp: '197.156.104.22'
        );

        $unrelatedUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unrelated Intruder User',
            'phone' => '+251911888888',
            'status' => 'active',
        ]);

        $response = $this->actingAs($unrelatedUser)->get("/buyer/contracts/{$contract->contract_code}");
        $response->assertForbidden();
    }

    public function test_seller_identity_snapshot_remains_traceable_after_seller_profile_changes(): void
    {
        $fixture = $this->createContractFixtures();
        $reservation = $fixture['reservation'];
        $company = $fixture['company'];

        $action = app(ConvertReservationToContractAction::class);
        $contract = $action->execute(
            reservationId: $reservation->id,
            disclosureAccepted: true,
            clientIp: '197.156.104.22'
        );

        // Seller subsequently modifies company profile and phone number
        $company->update([
            'name' => 'Rebranded Stealth Holdings Inc',
            'legal_name' => 'Rebranded Stealth Holdings Inc',
            'phone' => '+251999999999',
            'tax_identification_number' => 'TIN-MUTATED-000',
        ]);

        $contract->refresh();
        $snapshot = $contract->seller_identity_snapshot_json;

        // Snapshot MUST retain historical developer name at transaction time
        $this->assertSame('Sunshine Real Estate PLC', $snapshot['company_legal_name']);
        $this->assertSame('TIN-009182741', $snapshot['tax_identification_number']);
    }

    public function test_buyer_can_file_complaint_linked_to_transaction_and_seller_snapshot(): void
    {
        $fixture = $this->createContractFixtures();
        $reservation = $fixture['reservation'];
        $buyer = $fixture['buyer'];

        $action = app(ConvertReservationToContractAction::class);
        $contract = $action->execute(
            reservationId: $reservation->id,
            disclosureAccepted: true,
            clientIp: '197.156.104.22'
        );

        $response = $this->actingAs($buyer)->post("/buyer/contracts/{$contract->contract_code}/complaints", [
            'subject' => 'Developer construction stopped without notice',
            'description' => 'Milestone 2 foundation was paid in full 3 months ago. Developer has closed the on-site office.',
            'category' => 'fraud',
            'priority' => 'high',
        ]);

        $response->assertRedirect("/buyer/contracts/{$contract->contract_code}");
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('support_tickets', [
            'contract_id' => $contract->id,
            'user_id' => $buyer->id,
            'category' => 'fraud',
            'subject' => 'Developer construction stopped without notice',
            'transaction_reference' => $contract->contract_code,
        ]);

        $ticket = SupportTicket::query()->where('contract_id', $contract->id)->firstOrFail();
        $this->assertNotNull($ticket->seller_snapshot_json);
        $this->assertSame('Sunshine Real Estate PLC', $ticket->seller_snapshot_json['company_legal_name']);
        $this->assertSame($contract->contract_code, $ticket->evidence_json['contract_code']);
    }

    public function test_unauthorized_user_cannot_file_complaint_against_another_users_transaction(): void
    {
        $fixture = $this->createContractFixtures();
        $reservation = $fixture['reservation'];

        $action = app(ConvertReservationToContractAction::class);
        $contract = $action->execute(
            reservationId: $reservation->id,
            disclosureAccepted: true,
            clientIp: '197.156.104.22'
        );

        $unrelatedUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unrelated Third Party',
            'phone' => '+251911777777',
            'status' => 'active',
        ]);

        $response = $this->actingAs($unrelatedUser)->post("/buyer/contracts/{$contract->contract_code}/complaints", [
            'subject' => 'Malicious complaint on contract',
            'description' => 'Attempting unauthorized complaint filing.',
        ]);

        $response->assertForbidden();
    }

    public function test_developer_contract_seller_snapshot_cannot_be_silently_overwritten(): void
    {
        $fixture = $this->createContractFixtures();
        $reservation = $fixture['reservation'];

        $action = app(ConvertReservationToContractAction::class);
        $contract = $action->execute(
            reservationId: $reservation->id,
            disclosureAccepted: true,
            clientIp: '197.156.104.22'
        );

        $this->assertNotNull($contract->seller_identity_snapshot_json);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Immutable transaction evidence: historical developer seller identity snapshot cannot be silently modified or overwritten.');

        // Attempt silent overwrite
        $contract->update([
            'seller_identity_snapshot_json' => [
                'company_legal_name' => 'Fake Rogue Real Estate',
            ],
        ]);
    }

    public function test_developer_contract_audited_snapshot_correction(): void
    {
        $fixture = $this->createContractFixtures();
        $reservation = $fixture['reservation'];

        $action = app(ConvertReservationToContractAction::class);
        $contract = $action->execute(
            reservationId: $reservation->id,
            disclosureAccepted: true,
            clientIp: '197.156.104.22'
        );

        $officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Compliance Officer Rahel',
            'phone' => '+251912' . random_int(100000, 999999),
            'status' => 'active',
        ]);

        $contract->applyAuditedSnapshotCorrection(
            updates: ['trade_name' => 'Sunshine Real Estate & Construction Division PLC'],
            officerUserId: $officer->id,
            reason: 'Commercial trade name amended pursuant to Ministry of Trade gazette notice'
        );

        $contract->refresh();
        $snapshot = $contract->seller_identity_snapshot_json;

        $this->assertSame('Sunshine Real Estate & Construction Division PLC', $snapshot['trade_name']);
        $this->assertSame($officer->id, $snapshot['snapshot_correction']['corrected_by_user_id']);
        $this->assertCount(1, $snapshot['snapshot_correction']['history']);
        $this->assertSame('Sunshine Developers', $snapshot['snapshot_correction']['history'][0]['state']['trade_name']);
    }

    /**
     * @return array{buyer: User, agent: User, company: Company, project: DevelopmentProject, unit: ProjectUnit, reservation: UnitReservation}
     */
    private function createContractFixtures(): array
    {
        $buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Buyer Dawit Kebede',
            'phone' => '+251911445566',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-8877-6655-4433',
            'national_id_type' => 'fayda',
            'verification_level' => 1,
            'status' => 'active',
        ]);

        $agent = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Authorized Agent Solomon',
            'phone' => '+251911998877',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-1122-3344-5566',
            'national_id_type' => 'fayda',
            'verification_level' => 2,
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $agent->id,
            'name' => 'Sunshine Real Estate PLC',
            'legal_name' => 'Sunshine Real Estate PLC',
            'trade_name' => 'Sunshine Developers',
            'tin_number' => 'TIN-009182741',
            'tax_identification_number' => 'TIN-009182741',
            'license_number' => 'BL/AA/2022/9901',
            'trade_license_number' => 'BL/AA/2022/9901',
            'phone' => '+251115510000',
            'verification_level' => 2,
            'status' => 'active',
        ]);

        $developer = \App\Domains\Developer\Models\DeveloperProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'developer_code' => 'DEV-ETH-000099',
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'license_number' => 'DEV-LIC-9901',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        $project = DevelopmentProject::query()->create([
            'uuid' => (string) Str::uuid(),
            'developer_id' => $developer->id,
            'company_id' => $company->id,
            'title_en' => 'Sunshine Heights Bole',
            'slug' => 'sunshine-heights-bole-' . Str::lower((string) Str::random(6)),
            'construction_permit_number' => 'CP-AA-2023-889',
            'status' => \App\Domains\Developer\Enums\ProjectStatus::UnderConstruction,
        ]);

        $building = ProjectBuilding::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'name_en' => 'Tower A',
            'building_code' => 'TWR-A',
        ]);

        $floor = BuildingFloor::query()->create([
            'building_id' => $building->id,
            'project_id' => $project->id,
            'floor_number' => 4,
            'floor_label' => '4th Floor',
        ]);

        $unit = ProjectUnit::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'unit_number' => 'UNT-A401',
            'floor_number' => 4,
            'unit_type' => '3br',
            'area_sqm' => 140.0,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'price_etb' => 18_000_000.00,
            'status' => UnitInventoryStatus::Reserved,
        ]);

        $reservation = UnitReservation::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_code' => 'RSV-2026-000109',
            'idempotency_key' => 'idem-' . Str::random(16),
            'project_unit_id' => $unit->id,
            'project_id' => $project->id,
            'buyer_user_id' => $buyer->id,
            'agent_user_id' => $agent->id,
            'agreed_price_etb' => 18_000_000.00,
            'deposit_amount_etb' => 1_800_000.00,
            'status' => ReservationStatus::Reserved,
            'hold_expires_at' => now()->addDays(7),
            'payment_plan_snapshot_json' => [
                'plan_name' => 'Standard Off-Plan 6-Milestone Plan',
                'installments' => [
                    ['sequence_number' => 1, 'milestone_label_en' => 'Reservation Deposit', 'percentage' => 10.0],
                    ['sequence_number' => 2, 'milestone_label_en' => 'Foundation Stage', 'percentage' => 20.0],
                    ['sequence_number' => 3, 'milestone_label_en' => 'Structure Stage', 'percentage' => 20.0],
                    ['sequence_number' => 4, 'milestone_label_en' => 'Roofing Stage', 'percentage' => 20.0],
                    ['sequence_number' => 5, 'milestone_label_en' => 'Finishing Stage', 'percentage' => 20.0],
                    ['sequence_number' => 6, 'milestone_label_en' => 'Handover', 'percentage' => 10.0],
                ],
            ],
        ]);

        return [
            'buyer' => $buyer,
            'agent' => $agent,
            'company' => $company,
            'project' => $project,
            'unit' => $unit,
            'reservation' => $reservation,
        ];
    }
}
