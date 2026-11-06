<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Invoice\Actions\CreatePersistedInvoiceAction;
use App\Domains\Invoice\Models\Invoice;
use App\Domains\Invoice\Models\TaxRule;
use App\Domains\Maintenance\Actions\CreateMaintenanceRequestAction;
use App\Domains\Maintenance\Enums\MaintenanceCategory;
use App\Domains\Maintenance\Enums\MaintenancePriority;
use App\Domains\Maintenance\Enums\MaintenanceStatus;
use App\Domains\Maintenance\Models\MaintenanceRequest;
use App\Domains\Portfolio\Enums\PropertyManagementMandateStatus;
use App\Domains\Portfolio\Models\PropertyManagementMandate;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\User\Models\User;
use Database\Seeders\EthiopianTaxRulesSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Phase4Milestone2FeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $tenantUser;
    private User $ownerUser;
    private User $technicianUser;
    private Company $company;
    private CompanyBranch $branch;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(EthiopianTaxRulesSeeder::class);

        $this->adminUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'System Administrator',
            'phone' => '+251911111111',
            'status' => 'active',
        ]);
        $this->adminUser->assignRoleBySlug('super_admin');

        $this->tenantUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant User',
            'phone' => '+251922222222',
            'status' => 'active',
        ]);
        $this->tenantUser->assignRoleBySlug('tenant');

        $this->ownerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Property Owner User',
            'phone' => '+251933333333',
            'status' => 'active',
        ]);
        $this->ownerUser->assignRoleBySlug('property_owner');

        $this->technicianUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Lead Technician',
            'phone' => '+251944444444',
            'status' => 'active',
        ]);

        $this->company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'GTP Prime Properties PLC',
            'legal_name' => 'GTP Prime Properties PLC',
            'company_type' => 'real_estate_firm',
            'tin_number' => '0098765432',
            'owner_user_id' => $this->adminUser->id,
            'is_active' => true,
        ]);

        $this->branch = CompanyBranch::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'branch_code' => 'BR-BOLE-HQ',
            'name_en' => 'Bole Main Branch',
            'is_head_office' => true,
            'is_active' => true,
        ]);

        $catId = PropertyCategory::query()->value('id');
        $typeId = PropertyType::query()->value('id');

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-2026-0000201',
            'owner_id' => $this->ownerUser->id,
            'company_id' => $this->company->id,
            'category_id' => $catId,
            'property_type_id' => $typeId,
            'title_en' => 'Sarbet Heights Executive Tower',
            'status' => 'available',
            'verification_level' => 3,
        ]);

        \App\Domains\Lease\Models\Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-2026-000001',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenantUser->id,
            'landlord_id' => $this->ownerUser->id,
            'company_id' => $this->company->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent_etb' => 25000.00,
            'security_deposit_etb' => 50000.00,
            'payment_frequency' => 'monthly',
            'due_day_of_month' => 1,
            'contract_terms' => 'Standard residential lease.',
            'status' => 'active',
        ]);
    }

    // =========================================================================
    // MODULE 28 — MAINTENANCE HTTP TESTS
    // =========================================================================

    public function test_authenticated_user_can_view_maintenance_index(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('maintenance.index'));

        $response->assertStatus(200);
        $response->assertViewIs('maintenance.index');
        $response->assertSee('Work Orders');
    }

    public function test_authenticated_user_can_view_create_maintenance_form(): void
    {
        $response = $this->actingAs($this->tenantUser)
            ->get(route('maintenance.create'));

        $response->assertStatus(200);
        $response->assertViewIs('maintenance.create');
        $response->assertSee('Submit Maintenance Ticket');
    }

    public function test_user_can_submit_maintenance_request_via_post(): void
    {
        $payload = [
            'property_id' => $this->property->id,
            'category' => MaintenanceCategory::Plumbing->value,
            'priority' => MaintenancePriority::High->value,
            'title' => 'Master bathroom pipe burst',
            'description' => 'Heavy water leakage in the master bedroom en-suite.',
            'estimated_cost_etb' => 4500.00,
        ];

        $response = $this->actingAs($this->tenantUser)
            ->post(route('maintenance.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('maintenance_requests', [
            'property_id' => $this->property->id,
            'category' => MaintenanceCategory::Plumbing->value,
            'priority' => MaintenancePriority::High->value,
            'status' => MaintenanceStatus::Open->value,
            'reported_by_user_id' => $this->tenantUser->id,
        ]);
    }

    public function test_authorized_manager_can_transition_maintenance_status_via_post(): void
    {
        $action = app(CreateMaintenanceRequestAction::class);
        $ticket = $action->execute($this->tenantUser, [
            'property_id' => $this->property->id,
            'category' => MaintenanceCategory::Electricity->value,
            'priority' => MaintenancePriority::Emergency->value,
            'title' => 'Main circuit breaker tripping',
            'description' => 'Repeated power cuts on 3rd floor.',
        ]);

        $transitionPayload = [
            'target_status' => MaintenanceStatus::Assigned->value,
            'assigned_to_user_id' => $this->technicianUser->id,
            'contractor_name' => 'Abebe Electrician',
            'contractor_phone' => '+251911444555',
            'notes' => 'Dispatched emergency electrical contractor.',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('maintenance.transition', $ticket->uuid), $transitionPayload);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertSame(MaintenanceStatus::Assigned, $ticket->status);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertSame('Abebe Electrician', $ticket->contractor_name);

        $this->assertDatabaseHas('maintenance_request_histories', [
            'maintenance_request_id' => $ticket->id,
            'from_status' => MaintenanceStatus::Open->value,
            'to_status' => MaintenanceStatus::Assigned->value,
        ]);
    }

    public function test_authorized_user_can_view_maintenance_show_page(): void
    {
        $action = app(CreateMaintenanceRequestAction::class);
        $ticket = $action->execute($this->tenantUser, [
            'property_id' => $this->property->id,
            'category' => MaintenanceCategory::Plumbing->value,
            'priority' => MaintenancePriority::Normal->value,
            'title' => 'Kitchen tap leaking water',
            'description' => 'Constant dripping under pressure.',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('maintenance.show', $ticket->uuid));

        $response->assertStatus(200);
        $response->assertViewIs('maintenance.show');
        $response->assertSee($ticket->ticket_code);
        $response->assertSee('Kitchen tap leaking water');
    }

    // =========================================================================
    // MODULE 29 — PORTFOLIO DASHBOARD & MANDATES HTTP TESTS
    // =========================================================================

    public function test_authorized_user_can_access_portfolio_dashboard(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('portfolio.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('portfolio.dashboard');
        $response->assertSee('Property Management Dashboard');
        $response->assertSee('Occupied Units');
        $response->assertSee('Rent Collected');
    }

    public function test_authorized_user_can_access_portfolio_matrix(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('portfolio.matrix'));

        $response->assertStatus(200);
        $response->assertViewIs('portfolio.matrix');
        $response->assertSee('Multi-Unit Portfolio Matrix');
    }

    public function test_authorized_user_can_view_and_create_management_mandates(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('portfolio.mandates'));

        $response->assertStatus(200);
        $response->assertViewIs('portfolio.mandates');

        $mandatePayload = [
            'property_id' => $this->property->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'management_fee_pct' => 12.50,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'terms' => 'Full leasing, tenant vetting, and maintenance management.',
        ];

        $storeResponse = $this->actingAs($this->adminUser)
            ->post(route('portfolio.mandates.store'), $mandatePayload);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('property_management_mandates', [
            'property_id' => $this->property->id,
            'company_id' => $this->company->id,
            'management_fee_pct' => 12.50,
            'status' => PropertyManagementMandateStatus::Active->value,
        ]);
    }

    public function test_mandate_creation_rejects_invalid_fee_percentage_over_100(): void
    {
        $mandatePayload = [
            'property_id' => $this->property->id,
            'company_id' => $this->company->id,
            'management_fee_pct' => 150.00, // Invalid!
            'start_date' => '2026-01-01',
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('portfolio.mandates.store'), $mandatePayload);

        $response->assertSessionHasErrors('management_fee_pct');
    }

    // =========================================================================
    // MODULE 30 — INVOICES & PRINTABLE ETHIOPIAN/GREGORIAN RECEIPT TESTS
    // =========================================================================

    public function test_authorized_user_can_access_invoices_index_and_create_form(): void
    {
        $indexResponse = $this->actingAs($this->adminUser)
            ->get(route('invoices.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertViewIs('invoices.index');
        $indexResponse->assertSee('Issue New Invoice');

        $createResponse = $this->actingAs($this->adminUser)
            ->get(route('invoices.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertViewIs('invoices.create');
        $createResponse->assertSee('Issue Official ETB Tax Invoice');
    }

    public function test_authorized_user_can_issue_persisted_invoice_via_post(): void
    {
        $invoicePayload = [
            'company_id' => $this->company->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'issue_date' => '2026-10-24',
            'due_date' => '2026-11-05',
            'items' => [
                [
                    'description' => 'Apartment 4B Monthly Rent - Tikimt 2019',
                    'quantity' => 1.00,
                    'unit_price_etb' => 35000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('invoices.store'), $invoicePayload);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'company_id' => $this->company->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'currency' => 'ETB',
        ]);
        $latestInvoice = Invoice::query()->latest('id')->firstOrFail();
        $this->assertEquals(35000.00, (float) $latestInvoice->subtotal_etb);
    }

    public function test_printable_invoice_renders_dual_gregorian_and_ethiopian_dates(): void
    {
        $invoiceAction = app(CreatePersistedInvoiceAction::class);
        $invoice = $invoiceAction->execute($this->adminUser, [
            'company_id' => $this->company->id,
            'customer_user_id' => $this->tenantUser->id,
            'service_type' => 'rent',
            'issue_date' => '2026-10-24',
            'due_date' => '2026-11-05',
            'items' => [
                [
                    'description' => 'Commercial Suite 101 Rent',
                    'quantity' => 1.00,
                    'unit_price_etb' => 50000.00,
                ],
            ],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('invoices.print', $invoice->uuid));

        $response->assertStatus(200);
        $response->assertViewIs('invoices.print');
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('ETB');
        $response->assertSee('50,000.00');

        // Verify Gregorian date
        $response->assertSee('October 24, 2026');

        // Verify Ethiopian calendar converter output (October 24, 2026 = Tikimt 14, 2019 E.C.)
        $response->assertSee('2019');
        $response->assertSee('Tikimt 14');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('maintenance.index'));
        $response->assertRedirect('/login');

        $dashResponse = $this->get(route('portfolio.dashboard'));
        $dashResponse->assertRedirect('/login');

        $invResponse = $this->get(route('invoices.index'));
        $invResponse->assertRedirect('/login');
    }
}
