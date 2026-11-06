<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Developer\Enums\ProjectStatus;
use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\SalesContractStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\BuildingFloor;
use App\Domains\Developer\Models\ContractAmendment;
use App\Domains\Developer\Models\DeveloperPaymentAccount;
use App\Domains\Developer\Models\DeveloperPaymentPlan;
use App\Domains\Developer\Models\DeveloperProfile;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\DeveloperSalesContract;
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

class Phase3Milestone2SecurityAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $developerOwnerA;
    private User $developerOwnerB;
    private User $buyer;
    private User $unrelatedBuyer;
    private User $superAdmin;
    private DevelopmentProject $projectA;
    private ProjectUnit $unitA;
    private DeveloperPaymentPlan $planA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);

        // Developer A
        $this->developerOwnerA = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Developer Alpha CEO',
            'phone' => '+251911000001',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-DEV001',
            'identity_verified_at' => now(),
            'verification_level' => 3,
        ]);

        $companyAId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->developerOwnerA->id,
            'legal_name' => 'Alpha Real Estate Development PLC',
            'company_type' => 'developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileA = DeveloperProfile::query()->create([
            'company_id' => $companyAId,
            'user_id' => $this->developerOwnerA->id,
            'developer_code' => 'DEV-ALPHA-01',
            'verification_status' => 'verified',
        ]);

        $this->projectA = DevelopmentProject::query()->create([
            'developer_id' => $profileA->id,
            'company_id' => $companyAId,
            'project_code' => 'PRJ-ALPHA-01',
            'slug' => 'alpha-heights-residences',
            'title_en' => 'Alpha Heights Residences',
            'project_type' => 'residential_complex',
            'status' => ProjectStatus::UnderConstruction,
            'overall_progress_pct' => 40.00,
        ]);

        $buildingA = ProjectBuilding::query()->create([
            'project_id' => $this->projectA->id,
            'building_code' => 'BLD-A1',
            'name_en' => 'Alpha Tower One',
        ]);

        $floorA = BuildingFloor::query()->create([
            'project_id' => $this->projectA->id,
            'building_id' => $buildingA->id,
            'floor_number' => 3,
            'floor_label' => 'Level 3',
        ]);

        $this->unitA = ProjectUnit::query()->create([
            'project_id' => $this->projectA->id,
            'building_id' => $buildingA->id,
            'floor_id' => $floorA->id,
            'unit_number' => 'A-301',
            'floor_number' => 3,
            'unit_type' => '2br',
            'area_sqm' => 125.5,
            'price_etb' => 12_000_000.00,
            'status' => UnitInventoryStatus::Available,
        ]);

        $this->planA = DeveloperPaymentPlan::query()->create([
            'project_id' => $this->projectA->id,
            'name_en' => 'Standard Alpha Plan (10/40/50)',
            'is_default' => true,
            'is_active' => true,
        ]);

        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $this->planA->id,
            'sequence_number' => 1,
            'milestone_label_en' => 'Down Payment',
            'percentage' => 10.0,
        ]);
        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $this->planA->id,
            'sequence_number' => 2,
            'milestone_label_en' => 'Structure Completion',
            'percentage' => 40.0,
        ]);
        PaymentPlanInstallment::query()->create([
            'payment_plan_id' => $this->planA->id,
            'sequence_number' => 3,
            'milestone_label_en' => 'Key Handover',
            'percentage' => 50.0,
        ]);

        // Developer B (Competitor / Unrelated)
        $this->developerOwnerB = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Developer Beta CEO',
            'phone' => '+251911000002',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-DEV002',
            'identity_verified_at' => now(),
            'verification_level' => 3,
        ]);

        $companyBId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->developerOwnerB->id,
            'legal_name' => 'Beta Real Estate PLC',
            'company_type' => 'developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DeveloperProfile::query()->create([
            'company_id' => $companyBId,
            'user_id' => $this->developerOwnerB->id,
            'developer_code' => 'DEV-BETA-02',
            'verification_status' => 'verified',
        ]);

        // Legitimate Buyer
        $this->buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Alemayehu Haile',
            'phone' => '+251911000003',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-BUY003',
            'identity_verified_at' => now(),
            'verification_level' => 2,
        ]);

        // Unrelated User / Interloper
        $this->unrelatedBuyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tigist Worku',
            'phone' => '+251911000004',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-BUY004',
            'identity_verified_at' => now(),
            'verification_level' => 2,
        ]);

        // Super Admin
        $this->superAdmin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'System Super Admin',
            'phone' => '+251911000099',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-ADM099',
            'identity_verified_at' => now(),
            'verification_level' => 3,
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_unauthenticated_requests_to_developer_and_reservation_endpoints_return_401(): void
    {
        // 1. Payment accounts store
        $this->postJson('/api/v1/developer/payment-accounts', [
            'developer_id' => $this->projectA->developer_id,
            'project_id' => $this->projectA->id,
            'institution_name' => 'Commercial Bank of Ethiopia',
            'account_label' => 'Escrow',
            'account_holder_name' => 'Alpha PLC',
            'account_number' => '1000998877',
        ])->assertStatus(401);

        // 2. Construction milestones update
        $this->postJson("/api/v1/projects/{$this->projectA->id}/milestones", [
            'milestones' => [
                ['stage_code' => 'foundation', 'name_en' => 'Foundation', 'progress_weight' => 100.0, 'completion_percentage' => 50.0],
            ],
        ])->assertStatus(401);

        // 3. Payment plans store
        $this->postJson("/api/v1/projects/{$this->projectA->id}/payment-plans", [
            'name_en' => 'Test Plan',
            'installments' => [
                ['sequence_number' => 1, 'milestone_label_en' => 'Full', 'percentage' => 100.0],
            ],
        ])->assertStatus(401);

        // 4. Contract amendment
        $this->postJson('/api/v1/contracts/1/amend', [
            'revised_price_etb' => 15000000.00,
            'reason' => 'Upgrade',
        ])->assertStatus(401);

        // 5. Amendment acceptance
        $this->postJson('/api/v1/contracts/1/amendments/1/accept')->assertStatus(401);

        // 6. Amendment rejection
        $this->postJson('/api/v1/contracts/1/amendments/1/reject')->assertStatus(401);

        // 7. Payment verification
        $this->postJson('/api/v1/payments/1/verify', [
            'decision' => 'approved',
        ])->assertStatus(401);

        // 8. Unit reservation
        $this->postJson("/api/v1/units/{$this->unitA->id}/reserve", [
            'idempotency_key' => 'UNAUTH-KEY-001',
        ])->assertStatus(401);

        // 9. Reservation conversion
        $this->postJson('/api/v1/reservations/1/convert', [
            'disclosure_accepted' => true,
        ])->assertStatus(401);

        // 10. Developer management web page redirects unauthenticated callers
        $this->get("/projects/{$this->projectA->slug}/manage")->assertRedirect('login');
    }

    public function test_cross_company_developer_isolation_enforces_strict_rbac_403(): void
    {
        // Developer B tries to configure milestones for Project A
        $this->actingAs($this->developerOwnerB)
            ->postJson("/api/v1/projects/{$this->projectA->id}/milestones", [
                'milestones' => [
                    ['stage_code' => 'foundation', 'name_en' => 'Foundation', 'progress_weight' => 100.0, 'completion_percentage' => 50.0],
                ],
            ])
            ->assertStatus(403);

        // Developer B tries to add a payment account targeting Developer A
        $this->actingAs($this->developerOwnerB)
            ->postJson('/api/v1/developer/payment-accounts', [
                'developer_id' => $this->projectA->developer_id,
                'project_id' => $this->projectA->id,
                'institution_name' => 'Awash Bank',
                'account_label' => 'Unauthorized Hijack Account',
                'account_holder_name' => 'Beta PLC',
                'account_number' => '999988887777',
            ])
            ->assertStatus(403);

        // Developer B tries to access Developer A project management page
        $this->actingAs($this->developerOwnerB)
            ->get("/projects/{$this->projectA->slug}/manage")
            ->assertStatus(403);
    }

    public function test_public_project_page_has_zero_side_effects_and_renders_safe_empty_state(): void
    {
        $this->assertSame(0, DeveloperPaymentAccount::query()->count());

        // Repeated GET calls to public project page
        for ($i = 0; $i < 3; $i++) {
            $response = $this->get("/projects/{$this->projectA->slug}");
            $response->assertStatus(200);
            $response->assertSee('Alpha Heights Residences');
            $response->assertSee('Official payment instructions have not yet been registered');
        }

        // Must still be exactly 0 payment accounts
        $this->assertSame(0, DeveloperPaymentAccount::query()->count());
    }

    public function test_complete_contract_amendment_consent_lifecycle_and_rejection(): void
    {
        // 1. Buyer reserves unit and converts to contract
        $res = $this->actingAs($this->buyer)
            ->postJson("/api/v1/units/{$this->unitA->id}/reserve", [
                'idempotency_key' => 'AMEND-CONSENT-TEST',
            ])
            ->assertStatus(201)
            ->json();

        $contractData = $this->actingAs($this->buyer)
            ->postJson("/api/v1/reservations/{$res['id']}/convert", [
                'disclosure_accepted' => true,
            ])
            ->assertStatus(201)
            ->json();

        $contractId = $contractData['id'];

        // 2. Developer proposes contract amendment (12M -> 14M ETB)
        $amendResp = $this->actingAs($this->developerOwnerA)
            ->postJson("/api/v1/contracts/{$contractId}/amend", [
                'revised_price_etb' => 14_000_000.00,
                'reason' => 'Client requested premium imported kitchen cabinetry and marble island.',
            ])
            ->assertStatus(200);

        $contract = DeveloperSalesContract::with('amendments')->findOrFail($contractId);
        $this->assertCount(1, $contract->amendments);
        $amendment = $contract->amendments->first();
        $this->assertFalse((bool) $amendment->accepted_by_buyer);

        // 3. Competing amendment attempt while one is pending is blocked (403/DomainException)
        $this->actingAs($this->developerOwnerA)
            ->postJson("/api/v1/contracts/{$contractId}/amend", [
                'revised_price_etb' => 14_500_000.00,
                'reason' => 'Competing revision while first is pending.',
            ])
            ->assertStatus(403);

        // 4. Unrelated buyer tries to accept amendment -> 403 Forbidden
        $this->actingAs($this->unrelatedBuyer)
            ->postJson("/api/v1/contracts/{$contractId}/amendments/{$amendment->id}/accept")
            ->assertStatus(403);

        // 5. Super admin impersonation is disallowed on buyer acceptance -> 403 Forbidden
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/contracts/{$contractId}/amendments/{$amendment->id}/accept")
            ->assertStatus(403);

        // 6. Legitimate contracted buyer accepts amendment -> 200 OK
        $acceptResp = $this->actingAs($this->buyer)
            ->postJson("/api/v1/contracts/{$contractId}/amendments/{$amendment->id}/accept")
            ->assertStatus(200);

        $acceptResp->assertJsonPath('accepted_by_buyer', true);
        $amendment->refresh();
        $this->assertTrue((bool) $amendment->accepted_by_buyer);
        $this->assertNotNull($amendment->accepted_at);

        // 7. Idempotent replay of acceptance returns 200 OK
        $this->actingAs($this->buyer)
            ->postJson("/api/v1/contracts/{$contractId}/amendments/{$amendment->id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Contract price amendment already accepted.');

        // 8. Rejection of already-accepted amendment fails with 422
        $this->actingAs($this->buyer)
            ->postJson("/api/v1/contracts/{$contractId}/amendments/{$amendment->id}/reject")
            ->assertStatus(422);

        // 9. Now propose a second amendment (14M -> 16M ETB) and test rejection flow
        $amendResp2 = $this->actingAs($this->developerOwnerA)
            ->postJson("/api/v1/contracts/{$contractId}/amend", [
                'revised_price_etb' => 16_000_000.00,
                'reason' => 'Second proposal to test rejection.',
            ])
            ->assertStatus(200);

        $contract->refresh();
        $this->assertCount(2, $contract->amendments);
        $amendment2 = $contract->amendments->sortByDesc('id')->first();
        $this->assertSame('pending', $amendment2->status);
        $this->assertFalse((bool) $amendment2->accepted_by_buyer);
        // Active contract remains unchanged (14M ETB) while amendment is pending
        $this->assertEquals(14_000_000.00, (float) $contract->contract_price_etb);

        // 10. Non-buyer rejection fails with 403
        $this->actingAs($this->unrelatedBuyer)
            ->postJson("/api/v1/contracts/{$contractId}/amendments/{$amendment2->id}/reject")
            ->assertStatus(403);

        // 11. Super admin impersonation rejection fails with 403 (strict buyer RBAC)
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/contracts/{$contractId}/amendments/{$amendment2->id}/reject")
            ->assertStatus(403);

        // 12. Buyer rejects second amendment -> status marked rejected, active contract remains 14M ETB, amendment record PRESERVED!
        $rejectResp = $this->actingAs($this->buyer)
            ->postJson("/api/v1/contracts/{$contractId}/amendments/{$amendment2->id}/reject", [
                'reason' => 'Buyer declines additional custom finishes.',
            ])
            ->assertStatus(200);

        $this->assertEquals(14_000_000.00, (float) $rejectResp->json('contract_price_etb'));
        $rejectResp->assertJsonPath('status', 'rejected');

        $contract->refresh();
        $this->assertEquals(14_000_000.00, (float) $contract->contract_price_etb);

        // CRITICAL DATA PRESERVATION: Rejected amendment is NEVER deleted!
        $savedAmendment2 = ContractAmendment::query()->find($amendment2->id);
        $this->assertNotNull($savedAmendment2);
        $this->assertSame('rejected', $savedAmendment2->status);
        $this->assertFalse((bool) $savedAmendment2->accepted_by_buyer);
        $this->assertNotNull($savedAmendment2->rejected_at);
        $this->assertSame($this->buyer->id, $savedAmendment2->rejected_by_user_id);
        $this->assertSame('Buyer declines additional custom finishes.', $savedAmendment2->rejection_reason);
    }

    public function test_server_authoritative_reservation_hold_expiration(): void
    {
        // 1. Buyer 1 reserves unit with 15-minute hold
        $resResp = $this->actingAs($this->buyer)
            ->postJson("/api/v1/units/{$this->unitA->id}/reserve", [
                'idempotency_key' => 'EXPIRY-TEST-HOLD-001',
                'hold_minutes' => 15,
            ])
            ->assertStatus(201);

        $reservationId = $resResp->json('id');
        $this->unitA->refresh();
        $this->assertSame(UnitInventoryStatus::Reserved, $this->unitA->status);

        // 2. Immediate competing reservation while hold is active fails with 409 Conflict
        $this->actingAs($this->unrelatedBuyer)
            ->postJson("/api/v1/units/{$this->unitA->id}/reserve", [
                'idempotency_key' => 'EXPIRY-TEST-HOLD-COMPETE',
            ])
            ->assertStatus(409);

        // 3. Fast-forward server-side timestamp past expiration
        $reservation = UnitReservation::findOrFail($reservationId);
        $reservation->update([
            'hold_expires_at' => now()->subMinutes(1),
        ]);

        // 4. Buyer 2 now attempts reservation: server atomically expires stale hold and grants new hold
        $resResp2 = $this->actingAs($this->unrelatedBuyer)
            ->postJson("/api/v1/units/{$this->unitA->id}/reserve", [
                'idempotency_key' => 'EXPIRY-TEST-HOLD-002',
            ])
            ->assertStatus(201);

        // Stale hold is now marked expired
        $reservation->refresh();
        $this->assertSame(ReservationStatus::Expired, $reservation->status);
        $this->assertNotNull($reservation->expired_at);

        // New hold is active
        $this->unitA->refresh();
        $this->assertSame(UnitInventoryStatus::Reserved, $this->unitA->status);
        $newReservation = UnitReservation::findOrFail($resResp2->json('id'));
        $this->assertSame(ReservationStatus::Reserved, $newReservation->status);
        $this->assertSame($this->unrelatedBuyer->id, $newReservation->buyer_user_id);
    }

    public function test_contracted_or_sold_unit_is_never_released_by_stale_hold_action(): void
    {
        // 1. Reserve and convert unit to sales contract
        $res = $this->actingAs($this->buyer)
            ->postJson("/api/v1/units/{$this->unitA->id}/reserve", [
                'idempotency_key' => 'LOCK-TEST-HOLD',
            ])
            ->assertStatus(201)
            ->json();

        $this->actingAs($this->buyer)
            ->postJson("/api/v1/reservations/{$res['id']}/convert", [
                'disclosure_accepted' => true,
            ])
            ->assertStatus(201);

        $this->unitA->refresh();
        $this->assertSame(UnitInventoryStatus::Contracted, $this->unitA->status);

        // 2. Even if reservation hold_expires_at is in the past, unit status is Contracted and locked
        $oldHold = UnitReservation::findOrFail($res['id']);
        $oldHold->update(['hold_expires_at' => now()->subHours(2)]);

        $conflictResp = $this->actingAs($this->unrelatedBuyer)
            ->postJson("/api/v1/units/{$this->unitA->id}/reserve", [
                'idempotency_key' => 'ATTEMPT-ON-CONTRACTED-UNIT',
            ]);

        $conflictResp->assertStatus(409);
        $conflictResp->assertJsonPath('message', 'Unit A-301 is not available for reservation (current status: contracted).');

        $this->unitA->refresh();
        $this->assertSame(UnitInventoryStatus::Contracted, $this->unitA->status);
    }

    public function test_national_id_and_identity_verification_gating_scenarios(): void
    {
        // Setup a secondary unit for gating tests
        $unitGated = ProjectUnit::query()->create([
            'project_id' => $this->projectA->id,
            'building_id' => $this->unitA->building_id,
            'floor_id' => $this->unitA->floor_id,
            'unit_number' => 'A-302',
            'floor_number' => 3,
            'unit_type' => '1br',
            'area_sqm' => 75.0,
            'price_etb' => 8_000_000.00,
            'status' => UnitInventoryStatus::Available,
        ]);

        // Case A: Anonymous / Unauthenticated buyer -> 401
        $this->postJson("/api/v1/units/{$unitGated->id}/reserve", [
            'idempotency_key' => 'GATE-CASE-A',
        ])->assertStatus(401);

        // Case B: Authenticated unverified buyer (no phone, no ID) -> 403 Forbidden with missing requirements
        $rawBuyer = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Unverified Anonymous User',
            'phone' => '+251911999888',
            'status' => 'active',
            'phone_verified_at' => null,
            'national_id_number' => null,
            'identity_verified_at' => null,
            'verification_level' => 0,
        ]);

        $this->actingAs($rawBuyer)
            ->postJson("/api/v1/units/{$unitGated->id}/reserve", [
                'idempotency_key' => 'GATE-CASE-B',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'Identity verification required.');

        // Case C: Phone verified, but missing National ID or document -> 403 Forbidden
        $phoneOnlyBuyer = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Phone Verified Only User',
            'phone' => '+251911777666',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => null,
            'identity_verified_at' => null,
            'verification_level' => 1,
        ]);

        $this->actingAs($phoneOnlyBuyer)
            ->postJson("/api/v1/units/{$unitGated->id}/reserve", [
                'idempotency_key' => 'GATE-CASE-C',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'Identity verification required.');

        // Case D: Fully verified buyer (Phone + National ID verified) -> 201 Created
        $fullyVerifiedBuyer = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Kebede Kassahun',
            'phone' => '+251911555444',
            'status' => 'active',
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-2026-KEB001',
            'identity_verified_at' => now(),
            'verification_level' => 2,
        ]);

        $this->actingAs($fullyVerifiedBuyer)
            ->postJson("/api/v1/units/{$unitGated->id}/reserve", [
                'idempotency_key' => 'GATE-CASE-D',
            ])
            ->assertStatus(201)
            ->assertJsonPath('status', 'reserved');

        // Case E: Unauthorized user attempting to alter buyer verification status directly -> 403 / 404
        $this->actingAs($phoneOnlyBuyer)
            ->postJson("/api/v1/verifications/{$phoneOnlyBuyer->id}/review", [
                'decision' => 'approved',
            ])
            ->assertStatus(404);
    }

    public function test_stateful_session_csrf_middleware_enforcement(): void
    {
        $middleware = new \App\Http\Middleware\EnsureStatefulApiCsrf();
        $sessionStore = session()->driver();

        // 1. Safe HTTP GET request passes through without CSRF
        $getRequest = \Illuminate\Http\Request::create('/api/v1/projects', 'GET');
        $getRequest->setLaravelSession($sessionStore);
        $getRequest->setUserResolver(fn () => $this->buyer);

        $response = $middleware->handle($getRequest, function ($req) {
            return response()->json(['status' => 'ok']);
        });
        $this->assertSame(200, $response->getStatusCode());

        // 2. Stateful POST request with valid CSRF token passes through
        $validToken = 'valid-test-csrf-token';
        $sessionStore->put('_token', $validToken);

        $postRequest = \Illuminate\Http\Request::create('/api/v1/contracts/1/amendments/1/accept', 'POST');
        $postRequest->setLaravelSession($sessionStore);
        $postRequest->setUserResolver(fn () => $this->buyer);
        $postRequest->headers->set('X-CSRF-TOKEN', $validToken);
        $postRequest->headers->set('X-Enforce-CSRF', '1');

        $response = $middleware->handle($postRequest, function ($req) {
            return response()->json(['status' => 'accepted']);
        });
        $this->assertSame(200, $response->getStatusCode());

        // 3. Stateful POST request with missing or invalid CSRF token throws TokenMismatchException
        $invalidPostRequest = \Illuminate\Http\Request::create('/api/v1/contracts/1/amendments/1/accept', 'POST');
        $invalidPostRequest->setLaravelSession($sessionStore);
        $invalidPostRequest->setUserResolver(fn () => $this->buyer);
        $invalidPostRequest->headers->set('X-CSRF-TOKEN', 'bad-token');
        $invalidPostRequest->headers->set('X-Enforce-CSRF', '1');

        $this->expectException(\Illuminate\Session\TokenMismatchException::class);
        $middleware->handle($invalidPostRequest, function ($req) {
            return response()->json(['status' => 'should_not_reach']);
        });
    }
}
