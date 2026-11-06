<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Invoice\Actions\CreatePersistedInvoiceAction;
use App\Domains\Invoice\Actions\GenerateInvoiceNumberAction;
use App\Domains\Invoice\Actions\ResolveApplicableTaxRulesAction;
use App\Domains\Invoice\Models\TaxRule;
use App\Domains\Maintenance\Actions\CreateMaintenanceRequestAction;
use App\Domains\Maintenance\Actions\GenerateMaintenanceTicketCodeAction;
use App\Domains\Maintenance\Actions\TransitionMaintenanceTicketAction;
use App\Domains\Maintenance\Enums\MaintenanceCategory;
use App\Domains\Maintenance\Enums\MaintenancePriority;
use App\Domains\Maintenance\Enums\MaintenanceStatus;
use App\Domains\Maintenance\Models\MaintenanceRequest;
use App\Domains\Portfolio\Actions\CalculatePropertyManagementDashboardAction;
use App\Domains\Portfolio\Actions\CreatePropertyManagementMandateAction;
use App\Domains\Portfolio\Actions\GenerateMandateCodeAction;
use App\Domains\Portfolio\Enums\PropertyManagementMandateStatus;
use App\Domains\Portfolio\Models\PropertyManagementMandate;
use App\Domains\Portfolio\Services\MultiTenantOrganizationGuard;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\User\Models\User;
use App\Support\Calendar\EthiopianCalendarConverter;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class Phase4MaintenancePortfolioAndTaxInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $tenantUser;
    private User $ownerUser;
    private Company $companyA;
    private Company $companyB;
    private CompanyBranch $branchA1;
    private CompanyBranch $branchA2;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);

        $this->adminUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'phone' => '+251911000001',
            'status' => 'active',
        ]);
        $this->adminUser->assignRoleBySlug('super_admin');

        $this->tenantUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant Abebe',
            'phone' => '+251911000002',
            'status' => 'active',
        ]);
        $this->tenantUser->assignRoleBySlug('tenant');

        $this->ownerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner Kebede',
            'phone' => '+251911000003',
            'status' => 'active',
        ]);
        $this->ownerUser->assignRoleBySlug('property_owner');

        $this->companyA = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->ownerUser->id,
            'legal_name' => 'Addis Apex Realty PLC',
            'company_type' => 'property_management',
            'tin_number' => 'TIN-0012345678',
            'status' => 'active',
        ]);

        $this->companyB = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->ownerUser->id,
            'legal_name' => 'Rift Valley Properties PLC',
            'company_type' => 'property_management',
            'tin_number' => 'TIN-0087654321',
            'status' => 'active',
        ]);

        $this->branchA1 = CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->companyA->id,
            'branch_code' => 'BR-BOLE',
            'name_en' => 'Bole Sub-Branch',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        $this->branchA2 = CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->companyA->id,
            'branch_code' => 'BR-KAZANCHIS',
            'name_en' => 'Kazanchis Sub-Branch',
            'is_head_office' => false,
            'is_active' => true,
        ]);

        $catId = PropertyCategory::query()->value('id');
        $typeId = PropertyType::query()->value('id');

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-2026-0000101',
            'owner_id' => $this->ownerUser->id,
            'company_id' => $this->companyA->id,
            'category_id' => $catId,
            'property_type_id' => $typeId,
            'title_en' => 'Luxury Bole Apartment Tower',
            'status' => 'available',
            'verification_level' => 3,
        ]);
    }

    // =========================================================================
    // MODULE 28 — MAINTENANCE TESTS
    // =========================================================================

    public function test_creates_maintenance_request_with_concurrency_safe_ticket_code(): void
    {
        $action = app(CreateMaintenanceRequestAction::class);

        $request = $action->execute($this->tenantUser, [
            'property_id' => $this->property->id,
            'category' => MaintenanceCategory::Plumbing,
            'priority' => MaintenancePriority::High,
            'title' => 'Severe water pipe leak in kitchen',
            'description' => 'Water is leaking continuously under the kitchen counter sink.',
            'estimated_cost_etb' => 2500.00,
        ]);

        $this->assertInstanceOf(MaintenanceRequest::class, $request);
        $this->assertMatchesRegularExpression('/^MNT-\d{4}-\d{6}$/', $request->ticket_code);
        $this->assertSame(MaintenanceStatus::Open, $request->status);
        $this->assertSame(MaintenanceCategory::Plumbing, $request->category);
        $this->assertSame(MaintenancePriority::High, $request->priority);
        $this->assertSame('2500.00', (string) $request->estimated_cost_etb);

        // Verify initial audit history was recorded
        $this->assertDatabaseHas('maintenance_request_histories', [
            'maintenance_request_id' => $request->id,
            'to_status' => 'open',
            'actor_user_id' => $this->tenantUser->id,
        ]);
    }

    public function test_maintenance_state_machine_enforces_valid_transitions_and_validations(): void
    {
        $createAction = app(CreateMaintenanceRequestAction::class);
        $transitionAction = app(TransitionMaintenanceTicketAction::class);

        $ticket = $createAction->execute($this->tenantUser, [
            'property_id' => $this->property->id,
            'category' => 'electricity',
            'priority' => 'emergency',
            'title' => 'Main electrical breaker tripping',
            'description' => 'Breaker panel is sparking and shuts down power repeatedly.',
        ]);

        // 1. Invalid shortcut transition: Open cannot jump straight to Completed
        try {
            $transitionAction->execute($this->adminUser, $ticket, MaintenanceStatus::Completed, [
                'resolution_notes' => 'Done',
            ]);
            $this->fail('Expected ValidationException for illegal transition from open to completed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // 2. Open -> Assigned requires technician or contractor info
        try {
            $transitionAction->execute($this->adminUser, $ticket, MaintenanceStatus::Assigned, []);
            $this->fail('Expected ValidationException when assigning without technician or contractor.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assigned_to_user_id', $e->errors());
        }

        // 3. Valid transition: Open -> Assigned with contractor
        $ticket = $transitionAction->execute($this->adminUser, $ticket, MaintenanceStatus::Assigned, [
            'contractor_name' => 'Addis Power Solutions PLC',
            'contractor_phone' => '+251911998877',
            'notes' => 'Dispatched external emergency electrical contractor.',
        ]);
        $this->assertSame(MaintenanceStatus::Assigned, $ticket->status);
        $this->assertNotNull($ticket->assigned_at);

        // 4. Valid transition: Assigned -> InProgress
        $ticket = $transitionAction->execute($this->adminUser, $ticket, MaintenanceStatus::InProgress, [
            'notes' => 'Contractor arrived on site and commenced diagnostic inspection.',
        ]);
        $this->assertSame(MaintenanceStatus::InProgress, $ticket->status);
        $this->assertNotNull($ticket->started_at);

        // 5. Valid transition: InProgress -> Waiting
        $ticket = $transitionAction->execute($this->adminUser, $ticket, MaintenanceStatus::Waiting, [
            'notes' => 'Waiting for replacement 100A main circuit breaker delivery.',
        ]);
        $this->assertSame(MaintenanceStatus::Waiting, $ticket->status);

        // 6. Valid transition: Waiting -> Completed (requires resolution notes and valid cost)
        $ticket = $transitionAction->execute($this->adminUser, $ticket, MaintenanceStatus::Completed, [
            'actual_cost_etb' => 4500.00,
            'resolution_notes' => 'Replaced burnt main circuit breaker with certified ABB 100A switch.',
            'notes' => 'Repairs completed and safety tests passed.',
        ]);
        $this->assertSame(MaintenanceStatus::Completed, $ticket->status);
        $this->assertNotNull($ticket->completed_at);
        $this->assertSame('4500.00', (string) $ticket->actual_cost_etb);

        // 7. Valid transition: Completed -> Closed (final signoff)
        $ticket = $transitionAction->execute($this->adminUser, $ticket, MaintenanceStatus::Closed, [
            'notes' => 'Tenant verified power restored and approved work order closure.',
        ]);
        $this->assertSame(MaintenanceStatus::Closed, $ticket->status);
        $this->assertNotNull($ticket->closed_at);

        // 8. Terminal state check: Closed cannot transition to anything
        try {
            $transitionAction->execute($this->adminUser, $ticket, MaintenanceStatus::Open, []);
            $this->fail('Expected ValidationException when attempting transition from closed terminal state.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Verify total audit history count
        $this->assertGreaterThanOrEqual(6, $ticket->histories()->count());
    }

    // =========================================================================
    // MODULE 29 — ENTERPRISE PORTFOLIO & ORGANIZATIONAL ISOLATION TESTS
    // =========================================================================

    public function test_creates_management_mandate_with_strict_fee_and_overlap_validation(): void
    {
        $action = app(CreatePropertyManagementMandateAction::class);

        // 1. Fee percentage validation (> 100% rejected)
        try {
            $action->execute($this->adminUser, [
                'property_id' => $this->property->id,
                'company_id' => $this->companyA->id,
                'management_fee_pct' => 105.00,
                'start_date' => '2026-01-01',
            ]);
            $this->fail('Expected ValidationException for management fee > 100%.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('management_fee_pct', $e->errors());
        }

        // 2. Valid mandate creation
        $mandate = $action->execute($this->adminUser, [
            'property_id' => $this->property->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA1->id,
            'management_fee_pct' => 10.00,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'terms' => 'Full property management and rent collection services.',
        ]);

        $this->assertInstanceOf(PropertyManagementMandate::class, $mandate);
        $this->assertMatchesRegularExpression('/^MND-\d{4}-\d{7}$/', $mandate->mandate_code);
        $this->assertSame(PropertyManagementMandateStatus::Active, $mandate->status);
        $this->assertSame('10.00', (string) $mandate->management_fee_pct);

        // 3. Overlap protection: Property cannot have a second active mandate
        try {
            $action->execute($this->adminUser, [
                'property_id' => $this->property->id,
                'company_id' => $this->companyB->id,
                'management_fee_pct' => 8.00,
                'start_date' => '2026-06-01',
            ]);
            $this->fail('Expected ValidationException when assigning duplicate active mandate to same property.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('property_id', $e->errors());
        }
    }

    public function test_multi_tenant_guard_enforces_strict_fail_closed_isolation(): void
    {
        // Branch manager for Branch A1
        $branchA1Manager = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bole Manager',
            'phone' => '+251911111222',
            'status' => 'active',
        ]);
        $this->branchA1->update(['manager_user_id' => $branchA1Manager->id]);

        // Create tickets with different branch assignments
        $ticketBole = MaintenanceRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'ticket_code' => 'MNT-2026-000001',
            'property_id' => $this->property->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA1->id,
            'reported_by_user_id' => $this->tenantUser->id,
            'category' => MaintenanceCategory::Plumbing,
            'priority' => MaintenancePriority::Normal,
            'title' => 'Bole Unit Pipe Check',
            'description' => 'Test',
            'status' => MaintenanceStatus::Open,
        ]);

        $ticketKazanchis = MaintenanceRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'ticket_code' => 'MNT-2026-000002',
            'property_id' => $this->property->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA2->id,
            'reported_by_user_id' => $this->tenantUser->id,
            'category' => MaintenanceCategory::Electricity,
            'priority' => MaintenancePriority::Normal,
            'title' => 'Kazanchis Breaker',
            'description' => 'Test',
            'status' => MaintenanceStatus::Open,
        ]);

        $ticketNullBranch = MaintenanceRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'ticket_code' => 'MNT-2026-000003',
            'property_id' => $this->property->id,
            'company_id' => $this->companyA->id,
            'branch_id' => null, // Resource with null branch
            'reported_by_user_id' => $this->tenantUser->id,
            'category' => MaintenanceCategory::Cleaning,
            'priority' => MaintenancePriority::Low,
            'title' => 'Company-wide general cleaning',
            'description' => 'Test',
            'status' => MaintenanceStatus::Open,
        ]);

        $ticketCompanyB = MaintenanceRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'ticket_code' => 'MNT-2026-000004',
            'property_id' => $this->property->id,
            'company_id' => $this->companyB->id,
            'branch_id' => null,
            'reported_by_user_id' => $this->tenantUser->id,
            'category' => MaintenanceCategory::Appliance,
            'priority' => MaintenancePriority::Normal,
            'title' => 'Company B Ticket',
            'description' => 'Test',
            'status' => MaintenanceStatus::Open,
        ]);

        // Rule 1 & 2: Branch manager can access their own branch ticket
        $this->assertTrue(MultiTenantOrganizationGuard::canAccess($branchA1Manager, $ticketBole));

        // Rule 2: Branch manager CANNOT access other branch within same company
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($branchA1Manager, $ticketKazanchis));

        // Rule 4: Fail-closed rule: Resource with null branch MUST NOT be accessible to branch-scoped actor
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($branchA1Manager, $ticketNullBranch));

        // Rule 6: Branch manager cannot access another company
        $this->assertFalse(MultiTenantOrganizationGuard::canAccess($branchA1Manager, $ticketCompanyB));

        // Rule 3: Company head-office owner can access all tickets within company A (including null branch)
        $this->assertTrue(MultiTenantOrganizationGuard::canAccess($this->ownerUser, $ticketBole));
        $this->assertTrue(MultiTenantOrganizationGuard::canAccess($this->ownerUser, $ticketKazanchis));
        $this->assertTrue(MultiTenantOrganizationGuard::canAccess($this->ownerUser, $ticketNullBranch));

        // Query scope testing
        $query = MaintenanceRequest::query();
        MultiTenantOrganizationGuard::applyScope($query, $branchA1Manager);
        $visibleIds = $query->pluck('id')->all();

        $this->assertContains($ticketBole->id, $visibleIds);
        $this->assertNotContains($ticketKazanchis->id, $visibleIds);
        $this->assertNotContains($ticketNullBranch->id, $visibleIds);
        $this->assertNotContains($ticketCompanyB->id, $visibleIds);
    }

    public function test_calculates_portfolio_kpis_accurately_without_division_by_zero(): void
    {
        $action = app(CalculatePropertyManagementDashboardAction::class);

        // Test with real records
        $kpis = $action->execute($this->adminUser, []);

        $this->assertIsArray($kpis);
        $this->assertArrayHasKey('properties_managed', $kpis);
        $this->assertArrayHasKey('total_units', $kpis);
        $this->assertArrayHasKey('occupied_units', $kpis);
        $this->assertArrayHasKey('vacant_units', $kpis);
        $this->assertArrayHasKey('occupancy_rate_pct', $kpis);
        $this->assertArrayHasKey('tenants', $kpis);
        $this->assertArrayHasKey('rent_collected_etb', $kpis);
        $this->assertArrayHasKey('open_maintenance_requests', $kpis);
        $this->assertArrayHasKey('total_portfolio_revenue_etb', $kpis);

        // Test safe calculation on empty user without properties (zero division check)
        $freshUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empty User',
            'phone' => '+251911999999',
            'status' => 'active',
        ]);
        $emptyKpis = $action->execute($freshUser, []);
        $this->assertSame(0.0, $emptyKpis['occupancy_rate_pct']);
        $this->assertSame(0, $emptyKpis['total_units']);
    }

    // =========================================================================
    // MODULE 30 — DATABASE-DRIVEN TAX ENGINE & INVOICING TESTS
    // =========================================================================

    public function test_resolves_database_driven_taxes_with_geographic_override_and_stacking(): void
    {
        // 1. Create national VAT 15% rule
        TaxRule::query()->create([
            'uuid' => (string) Str::uuid(),
            'tax_code' => 'VAT',
            'name_en' => 'National VAT (15%)',
            'service_type' => 'all',
            'location_id' => null, // National fallback
            'rate_pct' => 15.000,
            'is_compound' => false,
            'calculation_order' => 10,
            'effective_from' => '2025-01-01',
            'is_active' => true,
        ]);

        // 2. Create national Withholding 2% rule (Stacks with VAT)
        TaxRule::query()->create([
            'uuid' => (string) Str::uuid(),
            'tax_code' => 'WITHHOLDING',
            'name_en' => 'Withholding Tax (2%)',
            'service_type' => 'all',
            'location_id' => null,
            'rate_pct' => 2.000,
            'is_withholding' => true,
            'calculation_order' => 20,
            'effective_from' => '2025-01-01',
            'is_active' => true,
        ]);

        // 3. Create local VAT 10% override for Special Free Trade Zone
        $zoneLocation = \App\Domains\Location\Models\Location::query()->create([
            'level' => 'city',
            'code' => 'DIR-FTZ',
            'slug' => 'dire-dawa-ftz',
            'ancestry_path' => '/ET/DIR',
            'name_en' => 'Dire Dawa Special Zone',
            'name_am' => 'ድሬዳዋ ልዩ ዞን',
            'is_active' => true,
        ]);

        TaxRule::query()->create([
            'uuid' => (string) Str::uuid(),
            'tax_code' => 'VAT',
            'name_en' => 'Dire Dawa Special Zone VAT (10%)',
            'service_type' => 'all',
            'location_id' => $zoneLocation->id, // Local override
            'rate_pct' => 10.000,
            'is_compound' => false,
            'calculation_order' => 10,
            'effective_from' => '2025-01-01',
            'is_active' => true,
        ]);

        $resolver = app(ResolveApplicableTaxRulesAction::class);

        // Test A: National fallback resolution on ETB 100,000 subtotal
        $resNational = $resolver->execute('rent', '2026-03-01', null, 100000.00);
        $this->assertSame(100000.00, $resNational['subtotal_etb']);
        $this->assertSame(15000.00, $resNational['tax_amount_etb']); // 15% VAT
        $this->assertSame(2000.00, $resNational['withholding_amount_etb']); // 2% Withholding
        $this->assertSame(115000.00, $resNational['total_amount_etb']);

        // Test B: Local location override resolution (Dire Dawa 10% VAT replaces national 15% VAT)
        $resLocal = $resolver->execute('rent', '2026-03-01', $zoneLocation->id, 100000.00);
        $this->assertSame(10000.00, $resLocal['tax_amount_etb']); // 10% VAT override applied!
        $this->assertSame(2000.00, $resLocal['withholding_amount_etb']); // National withholding still stacks
        $this->assertSame(110000.00, $resLocal['total_amount_etb']);
    }

    public function test_persists_itemized_etb_invoice_with_sequential_numbering_and_tax_snapshots(): void
    {
        // Seed standard tax rules
        TaxRule::query()->create([
            'uuid' => (string) Str::uuid(),
            'tax_code' => 'VAT',
            'name_en' => 'Value Added Tax (15%)',
            'service_type' => 'all',
            'rate_pct' => 15.000,
            'is_compound' => false,
            'calculation_order' => 10,
            'effective_from' => '2024-01-01',
            'is_active' => true,
        ]);

        $action = app(CreatePersistedInvoiceAction::class);

        $invoice = $action->execute($this->adminUser, [
            'company_id' => $this->companyA->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'issue_date' => '2026-10-24',
            'paid_amount_etb' => 11500.00, // Partial payment
            'items' => [
                [
                    'description' => 'Apartment Unit 302 Monthly Rent',
                    'quantity' => 1.00,
                    'unit_price_etb' => 20000.00,
                ],
                [
                    'description' => 'Dedicated Underground Parking Space',
                    'quantity' => 1.00,
                    'unit_price_etb' => 3000.00,
                ],
            ],
            'notes' => 'Official monthly rental billing with 15% VAT.',
        ]);

        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{7}$/', $invoice->invoice_number);
        $this->assertSame('23000.00', (string) $invoice->subtotal_amount_etb); // 20k + 3k
        $this->assertSame('3450.00', (string) $invoice->tax_amount_etb); // 15% of 23k = 3450
        $this->assertSame('26450.00', (string) $invoice->total_amount_etb); // 23k + 3450 = 26450
        $this->assertSame('11500.00', (string) $invoice->paid_amount_etb);
        $this->assertSame('14950.00', (string) $invoice->outstanding_balance_etb);
        $this->assertSame('partially_paid', $invoice->payment_status);

        // Verify line items were persisted
        $this->assertSame(2, $invoice->lineItems()->count());

        // Verify immutable tax snapshot
        $this->assertNotEmpty($invoice->tax_snapshots_json);
        $this->assertSame('VAT', $invoice->tax_snapshots_json[0]['tax_code']);
        $this->assertSame(15.0, (float) $invoice->tax_snapshots_json[0]['rate_pct']);

        // Verify dual Ethiopian calendar conversion for the invoice date
        $ethDate = EthiopianCalendarConverter::fromGregorian($invoice->issue_date)->format('en');
        $this->assertNotEmpty($ethDate);
    }
}
