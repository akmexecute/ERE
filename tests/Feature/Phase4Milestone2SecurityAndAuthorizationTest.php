<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Portfolio\Services\MultiTenantOrganizationGuard;
use App\Domains\Invoice\Actions\CreatePersistedInvoiceAction;
use App\Domains\Invoice\Models\Invoice;
use App\Domains\Lease\Models\Lease;
use App\Domains\Maintenance\Actions\CreateMaintenanceRequestAction;
use App\Domains\Maintenance\Actions\TransitionMaintenanceTicketAction;
use App\Domains\Maintenance\Enums\MaintenanceCategory;
use App\Domains\Maintenance\Enums\MaintenancePriority;
use App\Domains\Maintenance\Enums\MaintenanceStatus;
use App\Domains\Maintenance\Models\MaintenanceRequest;
use App\Domains\Maintenance\Models\MaintenanceRequestHistory;
use App\Domains\Portfolio\Actions\CalculatePropertyManagementDashboardAction;
use App\Domains\Portfolio\Enums\PropertyManagementMandateStatus;
use App\Domains\Portfolio\Models\PropertyManagementMandate;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\RentalFinancial\Models\RentSchedule;
use App\Domains\User\Models\User;
use App\Support\Calendar\EthiopianCalendarConverter;
use Carbon\CarbonImmutable;
use Database\Seeders\EthiopianTaxRulesSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class Phase4Milestone2SecurityAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $tenantUser;
    private User $unrelatedUser;
    private User $companyManagerUser;
    private User $ownerUser;
    private User $technicianUser;
    private Company $companyA;
    private Company $companyB;
    private CompanyBranch $branchA;
    private Property $propertyA;
    private Property $propertyB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(EthiopianTaxRulesSeeder::class);

        $this->adminUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'System Admin',
            'phone' => '+251911000001',
            'status' => 'active',
        ]);
        $this->adminUser->assignRoleBySlug('super_admin');

        $this->companyManagerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Company A Manager',
            'phone' => '+251911000002',
            'status' => 'active',
        ]);
        $this->companyManagerUser->assignRoleBySlug('property_admin');

        $this->companyA = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abyssinia Properties PLC',
            'legal_name' => 'Abyssinia Properties PLC',
            'company_type' => 'real_estate_firm',
            'tin_number' => '0011223344',
            'owner_user_id' => $this->companyManagerUser->id,
            'is_active' => true,
        ]);

        $this->branchA = CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->companyA->id,
            'branch_code' => 'BR-AA-HQ',
            'name_en' => 'HQ Branch',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $otherManager = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Company B Manager',
            'phone' => '+251911000099',
            'status' => 'active',
        ]);

        $this->companyB = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sheger Realty Corp',
            'legal_name' => 'Sheger Realty Corp',
            'company_type' => 'real_estate_firm',
            'tin_number' => '0099887766',
            'owner_user_id' => $otherManager->id,
            'is_active' => true,
        ]);

        $this->ownerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Almaz',
            'phone' => '+251911000003',
            'status' => 'active',
        ]);
        $this->ownerUser->assignRoleBySlug('property_owner');

        $this->tenantUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Dawit',
            'phone' => '+251911000004',
            'status' => 'active',
        ]);
        $this->tenantUser->assignRoleBySlug('tenant');

        $this->unrelatedUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Unrelated User',
            'phone' => '+251911000005',
            'status' => 'active',
        ]);

        $this->technicianUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Technician Girma',
            'phone' => '+251911000006',
            'status' => 'active',
        ]);

        $catId = PropertyCategory::query()->value('id');
        $typeId = PropertyType::query()->value('id');

        $this->propertyA = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-2026-0000701',
            'owner_id' => $this->ownerUser->id,
            'company_id' => $this->companyA->id,
            'category_id' => $catId,
            'property_type_id' => $typeId,
            'title_en' => 'Bole Medhanealem Luxury Penthouse',
            'status' => 'available',
            'verification_level' => 3,
        ]);

        $this->propertyB = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-2026-0000702',
            'owner_id' => $this->ownerUser->id,
            'company_id' => $this->companyB->id,
            'category_id' => $catId,
            'property_type_id' => $typeId,
            'title_en' => 'Sarbet Commercial Plaza',
            'status' => 'available',
            'verification_level' => 3,
        ]);

        // Active lease on Property A for Tenant Dawit
        Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-2026-000701',
            'property_id' => $this->propertyA->id,
            'tenant_id' => $this->tenantUser->id,
            'landlord_id' => $this->ownerUser->id,
            'company_id' => $this->companyA->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent_etb' => 40000.00,
            'security_deposit_etb' => 80000.00,
            'payment_frequency' => 'monthly',
            'due_day_of_month' => 1,
            'contract_terms' => 'Standard Residential Lease',
            'status' => 'active',
        ]);
    }

    // =========================================================================
    // MODULE 28 TESTS — MAINTENANCE SECURITY & WORKFLOW HARDENING
    // =========================================================================

    public function test_unauthorized_user_cannot_view_maintenance_ticket(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'category' => MaintenanceCategory::Plumbing->value,
            'priority' => MaintenancePriority::Normal->value,
            'title' => 'Bathroom leak',
            'description' => 'Water leaking around the sink trap.',
        ]);

        // Unrelated user must receive 403 Forbidden
        $response = $this->actingAs($this->unrelatedUser)
            ->get(route('maintenance.show', $ticket->uuid));

        $response->assertStatus(403);

        // Authorized reporter (tenant) can view
        $tenantResponse = $this->actingAs($this->tenantUser)
            ->get(route('maintenance.show', $ticket->uuid));
        $tenantResponse->assertOk();

        // Authorized company manager can view
        $managerResponse = $this->actingAs($this->companyManagerUser)
            ->get(route('maintenance.show', $ticket->uuid));
        $managerResponse->assertOk();
    }

    public function test_unauthorized_user_cannot_transition_maintenance_ticket(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'category' => MaintenanceCategory::Electricity->value,
            'priority' => MaintenancePriority::Normal->value,
            'title' => 'Switch sparking',
            'description' => 'Bedroom switch sparks when turned on.',
        ]);

        $response = $this->actingAs($this->unrelatedUser)
            ->post(route('maintenance.transition', $ticket->uuid), [
                'target_status' => MaintenanceStatus::Closed->value,
                'notes' => 'Arbitrary cancellation attempt',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthorized_tenant_cannot_submit_maintenance_ticket_for_arbitrary_property(): void
    {
        // Tenant Dawit has a lease on Property A, but NOT on Property B (Company B)
        $response = $this->actingAs($this->tenantUser)
            ->post(route('maintenance.store'), [
                'property_id' => $this->propertyB->id,
                'category' => MaintenanceCategory::Structural->value,
                'priority' => MaintenancePriority::Normal->value,
                'title' => 'Crack in wall',
                'description' => 'Wall cracked in building B.',
            ]);

        $response->assertStatus(403);
    }

    public function test_tenant_reporter_cannot_transition_ticket_to_operational_workflow(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'category' => MaintenanceCategory::Appliance->value,
            'title' => 'Oven heating element broken',
            'description' => 'The oven will not heat up.',
        ]);

        $transitionAction = app(TransitionMaintenanceTicketAction::class);

        // Tenant trying to advance to Assigned or InProgress must throw ValidationException
        $this->expectException(ValidationException::class);
        $transitionAction->execute($this->tenantUser, $ticket, MaintenanceStatus::Assigned, [
            'contractor_name' => 'Self-assigned',
        ]);
    }

    public function test_technician_cannot_close_or_reassign_maintenance_ticket(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'category' => MaintenanceCategory::Plumbing->value,
            'title' => 'Drain clogged',
            'description' => 'Kitchen drain is completely backed up.',
        ]);

        $transitionAction = app(TransitionMaintenanceTicketAction::class);

        // Manager assigns to technician
        $ticket = $transitionAction->execute($this->companyManagerUser, $ticket, MaintenanceStatus::Assigned, [
            'assigned_to_user_id' => $this->technicianUser->id,
            'contractor_name' => $this->technicianUser->name,
        ]);

        // Technician moves to InProgress (permitted)
        $ticket = $transitionAction->execute($this->technicianUser, $ticket, MaintenanceStatus::InProgress, [
            'notes' => 'On-site unblocking drain pipes.',
        ]);
        $this->assertSame(MaintenanceStatus::InProgress, $ticket->status);

        // Technician attempting to close ticket directly must throw ValidationException
        $this->expectException(ValidationException::class);
        $transitionAction->execute($this->technicianUser, $ticket, MaintenanceStatus::Closed, [
            'notes' => 'Technician attempting closure',
        ]);
    }

    public function test_maintenance_history_records_are_strictly_immutable(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'category' => MaintenanceCategory::Cleaning->value,
            'title' => 'Post-construction cleaning needed',
            'description' => 'Balcony dust and paint removal.',
        ]);

        /** @var MaintenanceRequestHistory $history */
        $history = $ticket->histories()->firstOrFail();

        // 1. Modifying history record must throw DomainException
        $this->expectException(DomainException::class);
        $history->update(['notes' => 'Tampered history notes']);
    }

    public function test_maintenance_history_cannot_be_deleted(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'category' => MaintenanceCategory::Other->value,
            'title' => 'Balcony railing loose',
            'description' => 'Screws loose on 4th floor railing.',
        ]);

        /** @var MaintenanceRequestHistory $history */
        $history = $ticket->histories()->firstOrFail();

        // 2. Deleting history record must throw DomainException
        $this->expectException(DomainException::class);
        $history->delete();
    }

    public function test_maintenance_request_with_actual_costs_cannot_be_deleted(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'category' => MaintenanceCategory::Plumbing->value,
            'title' => 'Pump failure',
            'description' => 'Rooftop water pump burnt out.',
        ]);

        $transitionAction = app(TransitionMaintenanceTicketAction::class);
        $ticket = $transitionAction->execute($this->companyManagerUser, $ticket, MaintenanceStatus::Assigned, [
            'assigned_to_user_id' => $this->technicianUser->id,
            'contractor_name' => $this->technicianUser->name,
        ]);
        $ticket = $transitionAction->execute($this->companyManagerUser, $ticket, MaintenanceStatus::InProgress);
        $ticket = $transitionAction->execute($this->companyManagerUser, $ticket, MaintenanceStatus::Completed, [
            'actual_cost_etb' => 8500.00,
            'resolution_notes' => 'Replaced pump impeller and electrical capacitor.',
        ]);

        $this->assertEquals(8500.00, (float) $ticket->actual_cost_etb);

        // Deleting ticket with incurred actual costs must be strictly prevented
        $this->expectException(DomainException::class);
        $ticket->delete();
    }

    public function test_failed_status_transition_rolls_back_history_and_preserves_integrity(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'category' => MaintenanceCategory::Electricity->value,
            'title' => 'Generator short circuit',
            'description' => 'Main backup generator tripped.',
        ]);

        $initialHistoryCount = $ticket->histories()->count();
        $transitionAction = app(TransitionMaintenanceTicketAction::class);

        // 1. Attempting invalid transition (Open -> Completed directly) must fail and rollback history
        try {
            $transitionAction->execute($this->companyManagerUser, $ticket, MaintenanceStatus::Completed, [
                'actual_cost_etb' => 5000.00,
                'resolution_notes' => 'Invalid direct skip',
            ]);
            $this->fail('Expected ValidationException on invalid transition.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Invalid transition', $e->getMessage());
        }

        // History count remains strictly unchanged due to atomic transaction rollback
        $this->assertEquals($initialHistoryCount, $ticket->histories()->count());
    }

    // =========================================================================
    // MODULE 29 TESTS — PORTFOLIO DASHBOARD, MANDATES & MULTI-TENANCY
    // =========================================================================

    public function test_cross_tenant_isolation_on_portfolio_dashboard(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);

        // Ticket for Company A
        $ticketA = $createAction->execute($this->tenantUser, [
            'property_id' => $this->propertyA->id,
            'company_id' => $this->companyA->id,
            'category' => MaintenanceCategory::Plumbing->value,
            'title' => 'Company A Work Order',
            'description' => 'Desc A',
        ]);

        // Ticket for Company B
        $ticketB = $createAction->execute($this->adminUser, [
            'property_id' => $this->propertyB->id,
            'company_id' => $this->companyB->id,
            'category' => MaintenanceCategory::Electricity->value,
            'title' => 'Company B Work Order',
            'description' => 'Desc B',
        ]);

        // Company A Manager visits dashboard
        $response = $this->actingAs($this->companyManagerUser)
            ->get(route('portfolio.dashboard'));

        $response->assertOk();
        $response->assertSee($ticketA->ticket_code);
        $response->assertDontSee($ticketB->ticket_code);
    }

    public function test_portfolio_matrix_fails_closed_for_unaffiliated_user(): void
    {
        // Unrelated user has no company and is not admin
        $response = $this->actingAs($this->unrelatedUser)
            ->get(route('portfolio.matrix'));

        $response->assertOk();
        // Should not see properties from Company A or Company B
        $response->assertDontSee('Bole Medhanealem Luxury Penthouse');
        $response->assertDontSee('Sarbet Commercial Plaza');
    }

    public function test_portfolio_dashboard_computes_expected_rent_and_outstanding_arrears(): void
    {
        $lease = Lease::query()->where('property_id', $this->propertyA->id)->firstOrFail();

        // Create 2 persistent rent schedules: 1 paid, 1 overdue
        RentSchedule::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_id' => $lease->id,
            'period_sequence' => 1,
            'period_label' => 'Tikimt 2019',
            'period_start_date' => '2026-10-01',
            'period_end_date' => '2026-10-31',
            'due_date' => '2026-10-05',
            'base_rent_etb' => 40000.00,
            'net_amount_due_etb' => 40000.00,
            'confirmed_paid_etb' => 40000.00,
            'outstanding_balance_etb' => 0.00,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        RentSchedule::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_id' => $lease->id,
            'period_sequence' => 2,
            'period_label' => 'Hidar 2019',
            'period_start_date' => '2026-11-01',
            'period_end_date' => '2026-11-30',
            'due_date' => '2026-11-05',
            'base_rent_etb' => 40000.00,
            'net_amount_due_etb' => 40000.00,
            'confirmed_paid_etb' => 0.00,
            'outstanding_balance_etb' => 40000.00,
            'status' => 'overdue',
        ]);

        $dashboardAction = app(CalculatePropertyManagementDashboardAction::class);
        $kpis = $dashboardAction->execute($this->companyManagerUser);

        $this->assertEquals(80000.00, $kpis['expected_rent_etb']);
        $this->assertEquals(40000.00, $kpis['outstanding_rent_etb']);
    }

    public function test_unauthorized_user_cannot_create_property_mandate(): void
    {
        // Unrelated user cannot create a mandate on Property A
        $response = $this->actingAs($this->unrelatedUser)
            ->post(route('portfolio.mandates.store'), [
                'property_id' => $this->propertyA->id,
                'company_id' => $this->companyA->id,
                'management_fee_pct' => 10.00,
                'start_date' => '2026-01-01',
            ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // MODULE 30 TESTS — INVOICE AUTHORIZATION, VOIDING & CALENDAR TESTS
    // =========================================================================

    public function test_unauthorized_user_cannot_view_or_print_another_users_invoice(): void
    {
        $invoiceAction = app(CreatePersistedInvoiceAction::class);
        $invoice = $invoiceAction->execute($this->companyManagerUser, [
            'company_id' => $this->companyA->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'issue_date' => '2026-10-25',
            'due_date' => '2026-11-25',
            'items' => [
                [
                    'description' => 'November 2026 Rent',
                    'quantity' => 1,
                    'unit_price_etb' => 40000.00,
                ],
            ],
        ]);

        // Unrelated user must receive 403 on show and print
        $showResponse = $this->actingAs($this->unrelatedUser)
            ->get(route('invoices.show', $invoice->uuid));
        $showResponse->assertStatus(403);

        $printResponse = $this->actingAs($this->unrelatedUser)
            ->get(route('invoices.print', $invoice->uuid));
        $printResponse->assertStatus(403);

        // Customer can view and print
        $this->actingAs($this->tenantUser)
            ->get(route('invoices.show', $invoice->uuid))
            ->assertOk();

        $this->actingAs($this->tenantUser)
            ->get(route('invoices.print', $invoice->uuid))
            ->assertOk();

        // Issuer company manager can view and print
        $this->actingAs($this->companyManagerUser)
            ->get(route('invoices.show', $invoice->uuid))
            ->assertOk();
    }

    public function test_user_cannot_spoof_company_id_when_issuing_invoice(): void
    {
        // Company Manager of Company A attempts to issue invoice under Company B
        $response = $this->actingAs($this->companyManagerUser)
            ->post(route('invoices.store'), [
                'company_id' => $this->companyB->id,
                'customer_user_id' => $this->tenantUser->id,
                'service_type' => 'rent',
                'items' => [
                    [
                        'description' => 'Fraudulent invoice',
                        'quantity' => 1,
                        'unit_price_etb' => 50000.00,
                    ],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_invoice_voiding_workflow_and_immutability(): void
    {
        $invoiceAction = app(CreatePersistedInvoiceAction::class);
        $invoice = $invoiceAction->execute($this->companyManagerUser, [
            'company_id' => $this->companyA->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'issue_date' => '2026-10-25',
            'items' => [
                [
                    'description' => 'Duplicate Invoice to be voided',
                    'quantity' => 1,
                    'unit_price_etb' => 15000.00,
                ],
            ],
        ]);

        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertEquals(17250.00, (float) $invoice->outstanding_balance_etb); // 15,000 + 15% Ethiopian VAT

        // 1. Void invoice with reason
        $invoice->voidInvoice($this->companyManagerUser, 'Issued in error as duplicate.');

        $this->assertSame('voided', $invoice->payment_status);
        $this->assertEquals(0.00, (float) $invoice->outstanding_balance_etb);
        $this->assertStringContainsString('Issued in error as duplicate', $invoice->notes);

        // 2. Cannot void an already voided invoice
        try {
            $invoice->voidInvoice($this->companyManagerUser, 'Second void attempt');
            $this->fail('Expected DomainException on already voided invoice.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already voided', $e->getMessage());
        }

        // 3. Modifying ANY field of voided invoice throws DomainException
        try {
            $invoice->update(['payment_status' => 'unpaid']);
            $this->fail('Expected DomainException when modifying payment_status on voided invoice.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        try {
            $invoice->update(['total_amount_etb' => 0.00]);
            $this->fail('Expected DomainException when modifying total_amount_etb on voided invoice.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // 4. Deleting a voided invoice throws DomainException
        $invoice->refresh();
        try {
            $invoice->delete();
            $this->fail('Expected DomainException when deleting a voided invoice.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }
    }

    public function test_invoice_voiding_scenarios_partially_paid_and_fully_paid(): void
    {
        // Scenario B: Partially Paid Invoice (ETB 100,000 total, 60,000 paid, 40,000 balance)
        $invoiceAction = app(CreatePersistedInvoiceAction::class);
        $partiallyPaid = $invoiceAction->execute($this->companyManagerUser, [
            'company_id' => $this->companyA->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'lease',
            'issue_date' => '2026-10-25',
            'paid_amount_etb' => 60000.00,
            'items' => [
                [
                    'description' => 'Commercial Lease Installment',
                    'quantity' => 1,
                    'unit_price_etb' => 100000.00,
                ],
            ],
        ]);

        $this->assertEquals(60000.00, (float) $partiallyPaid->paid_amount_etb);
        $this->assertEquals(55000.00, (float) $partiallyPaid->outstanding_balance_etb); // 115,000 total - 60,000 paid

        // Voiding partially paid invoice preserves recorded collection and zeroes balance
        $partiallyPaid->voidInvoice($this->companyManagerUser, 'Lease terminated prematurely by mutual consent.');
        $this->assertSame('voided', $partiallyPaid->payment_status);
        $this->assertEquals(60000.00, (float) $partiallyPaid->paid_amount_etb); // Preserved!
        $this->assertEquals(0.00, (float) $partiallyPaid->outstanding_balance_etb);
        $this->assertStringContainsString('Previously recorded payment of ETB 60,000.00 remains preserved', $partiallyPaid->notes);

        // Scenario C: Fully Paid Invoice (ETB 100,000 total, 115,000 paid, 0 balance)
        $fullyPaid = $invoiceAction->execute($this->companyManagerUser, [
            'company_id' => $this->companyA->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'issue_date' => '2026-10-25',
            'paid_amount_etb' => 115000.00,
            'items' => [
                [
                    'description' => 'Residential Rent Full Payment',
                    'quantity' => 1,
                    'unit_price_etb' => 100000.00,
                ],
            ],
        ]);

        $this->assertSame('paid', $fullyPaid->payment_status);

        // Direct voiding of fully paid invoice must be rejected
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Fully paid invoices cannot be voided directly; a formal refund or credit note is required.');
        $fullyPaid->voidInvoice($this->companyManagerUser, 'Mistaken payment refund attempt');
    }

    public function test_invoice_voiding_requires_meaningful_reason_and_proper_authorization(): void
    {
        $invoiceAction = app(CreatePersistedInvoiceAction::class);
        $invoice = $invoiceAction->execute($this->companyManagerUser, [
            'company_id' => $this->companyA->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'items' => [
                ['description' => 'Test Item', 'quantity' => 1, 'unit_price_etb' => 20000.00],
            ],
        ]);

        // 1. Empty reason throws InvalidArgumentException
        try {
            $invoice->voidInvoice($this->companyManagerUser, '   ');
            $this->fail('Expected InvalidArgumentException for empty void reason.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('meaningful void reason is required', $e->getMessage());
        }

        // 2. Unauthorized user cannot void invoice via model method
        try {
            $invoice->voidInvoice($this->unrelatedUser, 'Unauthorized void attempt');
            $this->fail('Expected AuthorizationException for unrelated user.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertStringContainsString('not authorized', $e->getMessage());
        }

        // 3. Unauthorized user cannot void invoice via HTTP endpoint
        $this->actingAs($this->unrelatedUser)
            ->post(route('invoices.void', $invoice->uuid), ['reason' => 'Hacker void attempt'])
            ->assertStatus(403);

        // 4. Authorized company manager can void invoice via HTTP endpoint
        $this->actingAs($this->companyManagerUser)
            ->post(route('invoices.void', $invoice->uuid), ['reason' => 'Legitimate administrative cancellation'])
            ->assertRedirect(route('invoices.show', $invoice->uuid))
            ->assertSessionHas('status');

        $invoice->refresh();
        $this->assertSame('voided', $invoice->payment_status);
        $this->assertEquals(0.00, (float) $invoice->outstanding_balance_etb);
    }

    public function test_portfolio_mandates_strict_multi_tenant_and_branch_isolation(): void
    {
        // Create Mandate for Company A (Branch A)
        $mandateA = \App\Domains\Portfolio\Models\PropertyManagementMandate::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'mandate_code' => 'MND-A-001',
            'property_id' => $this->propertyA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'owner_user_id' => $this->ownerUser->id,
            'management_fee_pct' => 10.00,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        // Create Mandate for Company B
        $branchB = \App\Domains\Company\Models\CompanyBranch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => $this->companyB->id,
            'branch_code' => 'BR-SH-HQ',
            'name_en' => 'Sheger HQ',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $mandateB = \App\Domains\Portfolio\Models\PropertyManagementMandate::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'mandate_code' => 'MND-B-001',
            'property_id' => $this->propertyB->id,
            'company_id' => $this->companyB->id,
            'branch_id' => $branchB->id,
            'owner_user_id' => $this->ownerUser->id,
            'management_fee_pct' => 12.00,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        // 1. Unaffiliated user visits /portfolio/mandates -> sees 0 mandates (fail closed)
        $unaffiliatedResponse = $this->actingAs($this->unrelatedUser)
            ->get(route('portfolio.mandates'));
        $unaffiliatedResponse->assertOk();
        $unaffiliatedMandates = $unaffiliatedResponse->viewData('mandates');
        $this->assertCount(0, $unaffiliatedMandates);

        // 2. Company A Manager sees only Mandate A, not Mandate B
        $managerResponse = $this->actingAs($this->companyManagerUser)
            ->get(route('portfolio.mandates'));
        $managerResponse->assertOk();
        $managerMandates = $managerResponse->viewData('mandates');
        $this->assertTrue($managerMandates->contains('id', $mandateA->id));
        $this->assertFalse($managerMandates->contains('id', $mandateB->id));

        // 3. Branch-scoped agent/manager strictly isolated to Branch A1
        $branchScopedManager = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Branch Scoped Manager',
            'phone' => '+251911888777',
            'status' => 'active',
        ]);
        $branchScopedManager->assignRoleBySlug('property_admin');

        $branchA1 = \App\Domains\Company\Models\CompanyBranch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => $this->companyA->id,
            'branch_code' => 'BR-AA-SOUTH',
            'name_en' => 'South Addis Branch',
            'manager_user_id' => $branchScopedManager->id,
            'is_head_office' => false,
            'is_active' => true,
        ]);

        $branchA2 = \App\Domains\Company\Models\CompanyBranch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'company_id' => $this->companyA->id,
            'branch_code' => 'BR-AA-NORTH',
            'name_en' => 'North Addis Branch',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        // Branch-scoped manager cannot create a mandate for Branch A2 (different branch)
        $response = $this->actingAs($branchScopedManager)
            ->post(route('portfolio.mandates.store'), [
                'property_id' => $this->propertyA->id,
                'company_id' => $this->companyA->id,
                'branch_id' => $branchA2->id,
                'management_fee_pct' => 10.00,
                'start_date' => '2026-01-01',
            ]);
        $response->assertStatus(403);
    }

    public function test_ethiopian_calendar_converter_meskerem_boundary_and_leap_year(): void
    {
        // 1. Gregorian 2026-09-11 is Meskerem 1, 2019 E.C. (Ethiopian New Year)
        $newYearGreg = CarbonImmutable::parse('2026-09-11', 'Africa/Addis_Ababa');
        $ethNewYear = EthiopianCalendarConverter::fromGregorian($newYearGreg);

        $this->assertSame(2019, $ethNewYear->year);
        $this->assertSame(1, $ethNewYear->month);
        $this->assertSame(1, $ethNewYear->day);
        $this->assertSame('Meskerem', $ethNewYear->monthName('en'));
        $this->assertSame('መስከረም', $ethNewYear->monthName('am'));

        // Reverse check: Meskerem 1, 2019 converts back to 2026-09-11
        $gregBack = EthiopianCalendarConverter::toGregorian(2019, 1, 1);
        $this->assertSame('2026-09-11', $gregBack->toDateString());

        // 2. Ethiopian Leap Year Test:
        // In Ethiopian calendar, leap year is when (year + 1) % 4 === 0.
        // 2019 + 1 = 2020 (divisible by 4) -> 2019 E.C. is an Ethiopian Leap Year!
        $this->assertTrue(EthiopianCalendarConverter::isEthiopianLeapYear(2019));
        // 2018 E.C. is not a leap year:
        $this->assertFalse(EthiopianCalendarConverter::isEthiopianLeapYear(2018));

        // Pagume in a leap year has 6 days:
        $leapPagume6 = EthiopianCalendarConverter::toGregorian(2019, 13, 6);
        $this->assertNotNull($leapPagume6);

        // In a non-leap year (2018), Pagume 6 is invalid:
        $this->expectException(\InvalidArgumentException::class);
        EthiopianCalendarConverter::toGregorian(2018, 13, 6);
    }

    public function test_record_payment_and_payment_rejection_on_voided_invoices(): void
    {
        $invoiceAction = app(CreatePersistedInvoiceAction::class);
        $invoice = $invoiceAction->execute($this->companyManagerUser, [
            'company_id' => $this->companyA->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'items' => [
                ['description' => 'Apartment Monthly Rent', 'quantity' => 1, 'unit_price_etb' => 50000.00],
            ],
        ]);

        $this->assertEquals(57500.00, (float) $invoice->total_amount_etb); // 50,000 + 15% VAT
        $this->assertEquals(57500.00, (float) $invoice->outstanding_balance_etb);
        $this->assertSame('unpaid', $invoice->payment_status);

        // 1. Record partial payment of ETB 20,000
        $invoice->recordPayment(20000.00, 'CBE-TX-987654');
        $this->assertEquals(20000.00, (float) $invoice->paid_amount_etb);
        $this->assertEquals(37500.00, (float) $invoice->outstanding_balance_etb);
        $this->assertSame('partially_paid', $invoice->payment_status);
        $this->assertStringContainsString('CBE-TX-987654', $invoice->notes);

        // 2. Void partially paid invoice
        $invoice->voidInvoice($this->companyManagerUser, 'Mutual tenancy termination agreement.');
        $this->assertSame('voided', $invoice->payment_status);
        $this->assertEquals(0.00, (float) $invoice->outstanding_balance_etb);
        $this->assertEquals(20000.00, (float) $invoice->paid_amount_etb); // Preserved!

        // 3. Attempting to record subsequent payment on voided invoice must throw DomainException
        try {
            $invoice->recordPayment(5000.00, 'ILLEGAL-PAYMENT-AFTER-VOID');
            $this->fail('Expected DomainException when recording payment on voided invoice.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Cannot record payment on a voided invoice', $e->getMessage());
        }

        // 4. Zero or negative payment amounts rejected
        $freshInvoice = $invoiceAction->execute($this->companyManagerUser, [
            'company_id' => $this->companyA->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'items' => [['description' => 'Rent', 'quantity' => 1, 'unit_price_etb' => 10000.00]],
        ]);

        try {
            $freshInvoice->recordPayment(0.00);
            $this->fail('Expected InvalidArgumentException for zero payment amount.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('greater than zero', $e->getMessage());
        }
    }

    public function test_comprehensive_eight_case_organization_access_control_matrix(): void
    {
        // Setup Company A with Branch A1 and Branch A2
        $branchA1 = \App\Domains\Company\Models\CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->companyA->id,
            'branch_code' => 'BR-A1',
            'name_en' => 'Branch A1',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        $branchA2 = \App\Domains\Company\Models\CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->companyA->id,
            'branch_code' => 'BR-A2',
            'name_en' => 'Branch A2',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        // Setup Company B with Branch B1
        $branchB1 = \App\Domains\Company\Models\CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->companyB->id,
            'branch_code' => 'BR-B1',
            'name_en' => 'Branch B1',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        // Actor 1: Branch A1 Manager
        $managerA1 = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manager A1',
            'phone' => '+251911111101',
            'status' => 'active',
        ]);
        $branchA1->update(['manager_user_id' => $managerA1->id]);

        // Actor 2: Branch A2 Manager
        $managerA2 = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manager A2',
            'phone' => '+251911111102',
            'status' => 'active',
        ]);
        $branchA2->update(['manager_user_id' => $managerA2->id]);

        // Actor 3: Branch B1 Manager
        $managerB1 = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manager B1',
            'phone' => '+251911111103',
            'status' => 'active',
        ]);
        $branchB1->update(['manager_user_id' => $managerB1->id]);

        // Actor 4: Head-Office Admin of Company A
        $headOfficeAdminA = $this->companyManagerUser; // Owner of Company A

        // Actor 5: Unaffiliated user
        $unaffiliatedUser = $this->unrelatedUser;

        // Actor 6: User with Company A but NULL branch (and not head-office admin)
        $nullBranchUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Null Branch Agent',
            'phone' => '+251911111106',
            'status' => 'active',
        ]);
        DB::table('agent_profiles')->insert([
            'uuid' => (string) Str::uuid(),
            'agent_code' => 'AGT-NULL-BR',
            'user_id' => $nullBranchUser->id,
            'company_id' => $this->companyA->id,
            'branch_id' => null, // Explicit null branch!
            'license_number' => 'LIC-NULL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Resources
        $resourceA1 = ['company_id' => $this->companyA->id, 'branch_id' => $branchA1->id];
        $resourceA2 = ['company_id' => $this->companyA->id, 'branch_id' => $branchA2->id];
        $resourceB1 = ['company_id' => $this->companyB->id, 'branch_id' => $branchB1->id];

        // Case 1: Company A / Branch A1 user -> Branch A1 data: ALLOW
        $this->assertTrue(MultiTenantOrganizationGuard::canAccess($managerA1, $resourceA1));

        // Case 2: Company A / Branch A1 user -> Branch A2 data: DENY
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($managerA1, $resourceA2));

        // Case 3: Company A / Branch A1 user -> Company B data: DENY
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($managerA1, $resourceB1));

        // Case 4: Company A / Branch A2 user -> Branch A1 data: DENY
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($managerA2, $resourceA1));

        // Case 5: Company B / Branch B1 user -> Company A data: DENY
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($managerB1, $resourceA1));

        // Case 6: Head-office administrator -> Company A-wide data: ALLOW
        $this->assertTrue(MultiTenantOrganizationGuard::canAccess($headOfficeAdminA, $resourceA1));
        $this->assertTrue(MultiTenantOrganizationGuard::canAccess($headOfficeAdminA, $resourceA2));
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($headOfficeAdminA, $resourceB1));

        // Case 7: Unaffiliated user -> Organization data: DENY
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($unaffiliatedUser, $resourceA1));

        // Case 8: User with null branch (non-head-office) -> Branch-specific data: DENY (Default-Deny)
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($nullBranchUser, $resourceA1));
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($nullBranchUser, $resourceA2));
    }

    public function test_company_a_and_company_b_strict_dashboard_kpi_isolation(): void
    {
        $dashboardAction = app(CalculatePropertyManagementDashboardAction::class);

        // 1. Company A Manager KPIs
        $kpisA = $dashboardAction->execute($this->companyManagerUser);
        $this->assertEquals(1, $kpisA['properties_managed']);

        // 2. Company B Owner / Manager
        $ownerB = User::query()->where('id', $this->companyB->owner_user_id)->firstOrFail();
        $kpisB = $dashboardAction->execute($ownerB);

        // Property count for Company B must strictly isolate Company B properties (Property B only, not Property A)
        $this->assertEquals(1, $kpisB['properties_managed']);

        // 3. User with Company affiliation but NULL branch (and not head-office) must fail closed
        $nullBranchUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Null Branch Employee',
            'phone' => '+251911999888',
            'status' => 'active',
        ]);
        DB::table('agent_profiles')->insert([
            'uuid' => (string) Str::uuid(),
            'agent_code' => 'AGT-NULL-EMP',
            'user_id' => $nullBranchUser->id,
            'company_id' => $this->companyA->id,
            'branch_id' => null,
            'license_number' => 'LIC-NULL-2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kpisNullBranch = $dashboardAction->execute($nullBranchUser);
        $this->assertEquals(0, $kpisNullBranch['properties_managed']);
        $this->assertEquals(0, $kpisNullBranch['total_units']);
        $this->assertEquals(0.00, $kpisNullBranch['total_portfolio_revenue_etb']);
    }
}
