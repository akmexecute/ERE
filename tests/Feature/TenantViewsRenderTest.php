<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Lease\Enums\InspectionType;
use App\Domains\Lease\Enums\LeaseStatus;
use App\Domains\Lease\Enums\PaymentFrequency;
use App\Domains\Lease\Models\Lease;
use App\Domains\Lease\Models\PropertyInspection;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\RentalFinancial\Actions\ConfirmRentalPaymentAction;
use App\Domains\RentalFinancial\Actions\GenerateRentScheduleAction;
use App\Domains\RentalFinancial\Actions\RecordRentalPaymentAction;
use App\Domains\Tenant\Enums\RentalApplicationStatus;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\TenantProfile;
use App\Domains\User\Models\User;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantViewsRenderTest extends TestCase
{
    use RefreshDatabase;

    private User $tenant;
    private User $landlord;
    private Property $property;
    private RentalApplication $application;
    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);

        $this->tenant = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abebe Tenant',
            'phone' => '+251911223344',
            'status' => 'active',
            'verification_level' => 2,
        ]);

        $this->landlord = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Almaz Landlord',
            'phone' => '+251922334455',
            'status' => 'active',
            'verification_level' => 3,
        ]);

        $profile = TenantProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->tenant->id,
            'tenant_code' => 'TNT-2026-0000001',
            'occupation' => 'Architect',
            'employer_name' => 'Design Studio',
            'monthly_income_etb' => 75000.00,
            'emergency_contact_name' => 'Tirunesh',
            'emergency_contact_phone' => '+251911887766',
            'emergency_contact_relationship' => 'Spouse',
            'status' => 'active',
        ]);

        $category = PropertyCategory::query()->first();
        $type = PropertyType::query()->first();

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-2026-0000001',
            'title_en' => 'Luxury Bole Apartment 4B',
            'owner_id' => $this->landlord->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'status' => 'available',
            'address_line' => 'Bole Medhanealem, Addis Ababa',
        ]);

        $this->application = RentalApplication::query()->create([
            'uuid' => (string) Str::uuid(),
            'application_code' => 'APP-2026-0000001',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'tenant_profile_id' => $profile->id,
            'landlord_id' => $this->landlord->id,
            'desired_move_in_date' => '2026-06-01',
            'lease_duration_months' => 12,
            'proposed_monthly_rent_etb' => 35000.00,
            'payment_frequency' => 'monthly',
            'occupant_count' => 2,
            'applicant_message' => 'Hello landlord',
            'status' => RentalApplicationStatus::Submitted,
        ]);

        $this->lease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-2026-0000001',
            'rental_application_id' => $this->application->id,
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'monthly_rent_etb' => 35000.00,
            'security_deposit_etb' => 70000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 5,
            'status' => LeaseStatus::Active,
        ]);
    }

    public function test_renders_tenant_applications_index_view(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rental-applications.index'));
        $response->assertOk();
        $response->assertSee('Rental Applications');
        $response->assertSee('APP-2026-0000001');
    }

    public function test_renders_tenant_applications_create_view(): void
    {
        // 1. With property_id query param
        $response = $this->actingAs($this->tenant)->get(route('rental-applications.create', ['property_id' => $this->property->id]));
        $response->assertOk();
        $response->assertSee('Apply for Tenancy');
        $response->assertSee('Luxury Bole Apartment 4B');

        // 2. Without query param (should not 404!)
        $responseWithoutQuery = $this->actingAs($this->tenant)->get(route('rental-applications.create'));
        $responseWithoutQuery->assertOk();
    }

    public function test_renders_tenant_applications_show_view(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('rental-applications.show', $this->application->uuid));
        $response->assertOk();
        $response->assertSee('Application #' . $this->application->application_code);
        $response->assertSee('Proposed Tenancy Terms');
    }

    public function test_renders_tenant_leases_index_view(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('leases.index'));
        $response->assertOk();
        $response->assertSee('Lease Contracts');
        $response->assertSee($this->lease->lease_code);
    }

    public function test_renders_tenant_leases_show_view(): void
    {
        // Add an inspection
        PropertyInspection::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_id' => $this->lease->id,
            'property_id' => $this->property->id,
            'inspection_type' => InspectionType::MoveIn,
            'inspection_date' => '2026-06-01',
            'inspector_user_id' => $this->landlord->id,
            'electricity_meter_reading' => 12000.0,
            'water_meter_reading' => 300.0,
            'notes' => 'Good condition',
        ]);

        $response = $this->actingAs($this->tenant)->get(route('leases.show', $this->lease->uuid));
        $response->assertOk();
        $response->assertSee('Contract #' . $this->lease->lease_code);
        $response->assertSee('Bilateral Execution');
    }

    public function test_renders_tenant_rent_schedule_view(): void
    {
        $scheduleGen = app(GenerateRentScheduleAction::class);
        $scheduleGen->execute($this->lease);

        $response = $this->actingAs($this->tenant)->get(route('rent.schedule', $this->lease->uuid));
        $response->assertOk();
        $response->assertSee('Rent Schedule', false);
        $response->assertSee('June 2026');
    }

    public function test_renders_tenant_rent_receipt_view(): void
    {
        $scheduleGen = app(GenerateRentScheduleAction::class);
        $schedules = $scheduleGen->execute($this->lease);
        $period = $schedules->first();

        $recordAction = app(RecordRentalPaymentAction::class);
        $payment = $recordAction->execute($this->tenant, [
            'lease_id' => $this->lease->id,
            'rent_schedule_id' => $period->id,
            'amount_etb' => 35000.00,
            'payment_method' => 'telebirr',
        ]);

        $confirmAction = app(ConfirmRentalPaymentAction::class);
        $receipt = $confirmAction->execute($this->landlord, $payment);

        $response = $this->actingAs($this->tenant)->get(route('rent.receipt', $receipt->uuid));
        $response->assertOk();
        $response->assertSee('Official Rental Payment Receipt');
        $response->assertSee($receipt->receipt_number);
    }
}
