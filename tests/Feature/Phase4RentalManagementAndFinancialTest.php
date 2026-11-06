<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\BuildingFloor;
use App\Domains\Developer\Models\DeveloperProfile;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\ProjectBuilding;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Lease\Actions\AcknowledgePropertyInspectionAction;
use App\Domains\Lease\Actions\CreateLeaseContractAction;
use App\Domains\Lease\Actions\RecordPropertyInspectionAction;
use App\Domains\Lease\Actions\SignLeaseContractAction;
use App\Domains\Lease\Actions\SynchronizeLeaseOccupancyAction;
use App\Domains\Lease\Enums\InspectionType;
use App\Domains\Lease\Enums\LeaseStatus;
use App\Domains\Lease\Enums\PaymentFrequency;
use App\Domains\Lease\Models\Lease;
use App\Domains\Lease\Models\LeaseResourceOccupancy;
use App\Domains\Lease\Models\PropertyInspection;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\RentalFinancial\Actions\AppendRentalPaymentAdjustmentAction;
use App\Domains\RentalFinancial\Actions\ConfirmRentalPaymentAction;
use App\Domains\RentalFinancial\Actions\GenerateRentScheduleAction;
use App\Domains\RentalFinancial\Actions\RecordRentalPaymentAction;
use App\Domains\RentalFinancial\Enums\RentScheduleStatus;
use App\Domains\RentalFinancial\Enums\RentalPaymentStatus;
use App\Domains\RentalFinancial\Models\RentalPayment;
use App\Domains\RentalFinancial\Models\RentalPaymentAdjustment;
use App\Domains\RentalFinancial\Models\RentalReceipt;
use App\Domains\RentalFinancial\Models\RentSchedule;
use App\Domains\Tenant\Actions\AttachRentalApplicationDocumentAction;
use App\Domains\Tenant\Actions\ConvertApplicationToLeaseAction;
use App\Domains\Tenant\Actions\CreateOrUpdateTenantProfileAction;
use App\Domains\Tenant\Actions\ReviewRentalApplicationAction;
use App\Domains\Tenant\Actions\SubmitRentalApplicationAction;
use App\Domains\Tenant\Enums\RentalApplicationStatus;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\RentalApplicationDocument;
use App\Domains\Tenant\Models\RentalApplicationHistory;
use App\Domains\Tenant\Models\TenantProfile;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

final class Phase4RentalManagementAndFinancialTest extends TestCase
{
    use RefreshDatabase;

    private User $tenant;
    private User $landlord;
    private User $staff;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);

        $this->tenant = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abebe Bikila',
            'phone' => '+251911223344',
            'status' => 'active',
            'verification_level' => 2,
        ]);

        $this->landlord = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Almaz Ayana',
            'phone' => '+251922334455',
            'status' => 'active',
            'verification_level' => 3,
        ]);

        $this->staff = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Finance Staff User',
            'phone' => '+251933445566',
            'status' => 'active',
        ]);
        $this->staff->assignRoleBySlug('super_admin');

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
    }

    private function createTestUnit(string $unitNumber): ProjectUnit
    {
        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->landlord->id,
            'legal_name' => 'Bole Heights Development PLC',
            'company_type' => 'developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dev = DeveloperProfile::query()->firstOrCreate([
            'user_id' => $this->landlord->id,
        ], [
            'company_id' => $companyId,
            'developer_code' => 'DEV-ETH-' . rand(1000, 9999),
            'verification_status' => 'verified',
        ]);

        $project = DevelopmentProject::query()->create([
            'developer_id' => $dev->id,
            'company_id' => $companyId,
            'project_code' => 'PRJ-' . rand(1000, 9999),
            'slug' => 'proj-' . rand(1000, 9999),
            'title_en' => 'Bole Heights Project',
            'project_type' => 'residential_complex',
            'status' => 'under_construction',
        ]);

        $building = ProjectBuilding::query()->create([
            'project_id' => $project->id,
            'building_code' => 'BLD-' . rand(100, 999),
            'name_en' => 'Building A',
        ]);

        $floor = BuildingFloor::query()->create([
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_number' => 1,
            'floor_label' => 'Floor 1',
        ]);

        return ProjectUnit::query()->create([
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'unit_number' => $unitNumber,
            'floor_number' => 1,
            'unit_type' => '2br',
            'area_sqm' => 85.0,
            'price_etb' => 5000000.0,
            'status' => UnitInventoryStatus::Available,
        ]);
    }

    public function test_creates_tenant_profile_with_atomic_code_and_masks_sensitive_data(): void
    {
        $action = app(CreateOrUpdateTenantProfileAction::class);

        $profile = $action->execute($this->tenant, [
            'occupation' => 'Senior Systems Engineer',
            'employer_name' => 'Ethiopian Airlines',
            'monthly_income_etb' => 85000.00,
            'emergency_contact_name' => 'Tirunesh Dibaba',
            'emergency_contact_phone' => '+251911998877',
            'emergency_contact_relationship' => 'Spouse',
        ]);

        $this->assertNotNull($profile->tenant_code);
        $this->assertStringStartsWith('TNT-', $profile->tenant_code);
        $this->assertSame('Senior Systems Engineer', $profile->occupation);
        $this->assertSame(85000.00, (float) $profile->monthly_income_etb);

        // Emergency phone masking
        $this->assertSame('+251911***77', $profile->maskedEmergencyPhone());
    }

    public function test_submits_rental_application_and_resolves_true_landlord(): void
    {
        $action = app(SubmitRentalApplicationAction::class);

        $application = $action->execute($this->tenant, [
            'property_id' => $this->property->id,
            'desired_move_in_date' => now()->addDays(7)->toDateString(),
            'lease_duration_months' => 12,
            'proposed_monthly_rent_etb' => 45000.00,
            'payment_frequency' => 'monthly',
            'occupant_count' => 2,
            'applicant_message' => 'Interested in leasing this property for 1 year.',
        ]);

        $this->assertNotNull($application->application_code);
        $this->assertStringStartsWith('APP-', $application->application_code);
        $this->assertSame($this->property->id, $application->property_id);
        $this->assertSame($this->landlord->id, $application->landlord_id);
        $this->assertSame($this->tenant->id, $application->tenant_id);
        $this->assertSame(RentalApplicationStatus::Submitted, $application->status);

        // Submitting duplicate active application for same property and tenant is prevented
        $this->expectException(ValidationException::class);

        $action->execute($this->tenant, [
            'property_id' => $this->property->id,
            'desired_move_in_date' => now()->addDays(10)->toDateString(),
            'lease_duration_months' => 6,
            'proposed_monthly_rent_etb' => 45000.00,
            'payment_frequency' => 'monthly',
            'occupant_count' => 1,
        ]);
    }

    public function test_rental_application_review_workflow_and_rejection_reason(): void
    {
        $submitAction = app(SubmitRentalApplicationAction::class);
        $application = $submitAction->execute($this->tenant, [
            'property_id' => $this->property->id,
            'desired_move_in_date' => now()->addDays(5)->toDateString(),
            'lease_duration_months' => 12,
            'proposed_monthly_rent_etb' => 40000.00,
            'payment_frequency' => 'monthly',
            'occupant_count' => 1,
        ]);

        $reviewAction = app(ReviewRentalApplicationAction::class);

        // 1. Move to Document Review
        $reviewAction->execute($this->landlord, $application, 'document_review', 'Please upload tax clearance document.');
        $this->assertSame(RentalApplicationStatus::DocumentReview, $application->fresh()->status);

        // 2. Reject with reason
        $reviewAction->execute($this->landlord, $application, 'reject', null, 'Proposed rent is below acceptable threshold.');
        $application->refresh();

        $this->assertSame(RentalApplicationStatus::Rejected, $application->status);
        $this->assertSame('Proposed rent is below acceptable threshold.', $application->rejection_reason);
    }

    public function test_converts_approved_application_to_lease_and_prevents_duplicate_conversion(): void
    {
        $submitAction = app(SubmitRentalApplicationAction::class);
        $application = $submitAction->execute($this->tenant, [
            'property_id' => $this->property->id,
            'desired_move_in_date' => '2026-05-01',
            'lease_duration_months' => 12,
            'proposed_monthly_rent_etb' => 45000.00,
            'payment_frequency' => 'monthly',
            'occupant_count' => 2,
        ]);

        $reviewAction = app(ReviewRentalApplicationAction::class);
        $reviewAction->execute($this->landlord, $application, 'approve', 'Approved by owner.');
        $this->assertSame(RentalApplicationStatus::Approved, $application->fresh()->status);

        $convertAction = app(ConvertApplicationToLeaseAction::class);
        $lease = $convertAction->execute($this->landlord, $application);

        $this->assertNotNull($lease->lease_code);
        $this->assertStringStartsWith('LSE-', $lease->lease_code);
        $this->assertSame(LeaseStatus::PendingSignatures, $lease->status);
        $this->assertSame('2026-05-01', $lease->start_date->toDateString());
        $this->assertSame('2027-04-30', $lease->end_date->toDateString());
        $this->assertSame(45000.00, (float) $lease->monthly_rent_etb);
        $this->assertSame(RentalApplicationStatus::ConvertedToLease, $application->fresh()->status);
        $this->assertSame($lease->id, $application->fresh()->converted_lease_id);

        // Attempting to convert again throws exception
        $this->expectException(ValidationException::class);
        $convertAction->execute($this->landlord, $application->fresh());
    }

    public function test_bilateral_lease_signing_triggers_activation_and_schedule_generation(): void
    {
        $createLeaseAction = app(CreateLeaseContractAction::class);
        $lease = $createLeaseAction->execute($this->landlord, [
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'monthly_rent_etb' => 30000.00,
            'security_deposit_etb' => 60000.00,
            'payment_frequency' => 'monthly',
            'due_day_of_month' => 5,
            'status' => LeaseStatus::PendingSignatures,
        ]);

        $this->assertSame(LeaseStatus::PendingSignatures, $lease->status);

        $signAction = app(SignLeaseContractAction::class);

        // 1. Tenant signs
        $signAction->execute($this->tenant, $lease, '197.156.10.12');
        $lease->refresh();
        $this->assertNotNull($lease->tenant_signed_at);
        $this->assertSame(LeaseStatus::PendingSignatures, $lease->status); // Still pending landlord

        // 2. Landlord signs -> activates lease and generates schedules!
        $signAction->execute($this->landlord, $lease, '197.156.10.14');
        $lease->refresh();
        $this->assertNotNull($lease->landlord_signed_at);
        $this->assertSame(LeaseStatus::Active, $lease->status);

        // Schedules generated
        $schedules = RentSchedule::query()->where('lease_id', $lease->id)->get();
        $this->assertCount(12, $schedules);
        $this->assertSame('June 2026', $schedules->first()->period_label);
        $this->assertSame(30000.00, (float) $schedules->first()->net_amount_due_etb);
    }

    public function test_prevents_overlapping_leases_on_whole_property_and_unit_scopes(): void
    {
        $syncAction = app(SynchronizeLeaseOccupancyAction::class);

        $lease1 = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-TEST-0001',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent_etb' => 20000.00,
            'security_deposit_etb' => 40000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);

        // Occupy whole property (resource_id = 0)
        $syncAction->execute($lease1);

        $this->assertTrue(
            LeaseResourceOccupancy::query()
                ->where('property_id', $this->property->id)
                ->where('resource_id', 0)
                ->exists()
        );

        // Create conflicting lease 2 overlapping the same property
        $lease2 = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-TEST-0002',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'monthly_rent_etb' => 22000.00,
            'security_deposit_etb' => 44000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/overlaps an existing active or pending lease/');
        $syncAction->execute($lease2);
    }

    public function test_whole_property_lease_conflicts_with_individual_unit_leases(): void
    {
        $unit = $this->createTestUnit('Unit-101');
        $syncAction = app(SynchronizeLeaseOccupancyAction::class);

        // Lease 1 is for Unit #101 (resource_id = unit->id)
        $unitLease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-UNIT-0101',
            'property_id' => $this->property->id,
            'project_unit_id' => $unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent_etb' => 15000.00,
            'security_deposit_etb' => 30000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);

        $syncAction->execute($unitLease);

        // Now attempt to lease the WHOLE property (resource_id = 0) during overlapping dates
        $wholePropertyLease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-WHOLE-PROP',
            'property_id' => $this->property->id,
            'project_unit_id' => null, // Whole property
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'monthly_rent_etb' => 50000.00,
            'security_deposit_etb' => 100000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/overlaps an existing active or pending lease/');
        $syncAction->execute($wholePropertyLease);
    }

    public function test_independent_project_units_can_coexist_without_conflict(): void
    {
        $unit1 = $this->createTestUnit('Unit-201');
        $unit2 = $this->createTestUnit('Unit-202');
        $syncAction = app(SynchronizeLeaseOccupancyAction::class);

        // Unit 1
        $unit1Lease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-UNIT-1',
            'property_id' => $this->property->id,
            'project_unit_id' => $unit1->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent_etb' => 15000.00,
            'security_deposit_etb' => 30000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);
        $syncAction->execute($unit1Lease);

        // Unit 2 on SAME property during SAME dates should succeed!
        $unit2Lease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-UNIT-2',
            'property_id' => $this->property->id,
            'project_unit_id' => $unit2->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent_etb' => 18000.00,
            'security_deposit_etb' => 36000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);
        $syncAction->execute($unit2Lease);

        $this->assertSame(2, LeaseResourceOccupancy::query()->where('property_id', $this->property->id)->count());
    }

    public function test_move_in_and_move_out_inspections_recording_and_acknowledgements(): void
    {
        $lease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-INSPECT-01',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent_etb' => 25000.00,
            'security_deposit_etb' => 50000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);

        $recordAction = app(RecordPropertyInspectionAction::class);
        $inspection = $recordAction->execute($this->landlord, $lease, InspectionType::MoveIn, [
            'inspection_date' => '2026-01-01',
            'electricity_meter_reading' => 12540.50,
            'water_meter_reading' => 450.20,
            'condition_checklist' => ['living_room' => 'pristine', 'kitchen' => 'functional'],
            'notes' => 'Keys handed over.',
        ]);

        $this->assertSame(InspectionType::MoveIn, $inspection->inspection_type);
        $this->assertSame(12540.50, (float) $inspection->electricity_meter_reading);
        $this->assertSame(450.20, (float) $inspection->water_meter_reading);
        $this->assertFalse($inspection->isFullyAcknowledged());

        // Bilateral Acknowledgements
        $ackAction = app(AcknowledgePropertyInspectionAction::class);
        $ackAction->execute($this->tenant, $inspection, '197.156.10.12');
        $this->assertNotNull($inspection->fresh()->acknowledged_by_tenant_at);
        $this->assertFalse($inspection->fresh()->isFullyAcknowledged());

        $ackAction->execute($this->landlord, $inspection, '197.156.10.14');
        $this->assertTrue($inspection->fresh()->isFullyAcknowledged());
    }

    public function test_payment_recording_confirmation_and_official_receipt_issuance(): void
    {
        $lease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-PAY-0001',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'monthly_rent_etb' => 20000.00,
            'security_deposit_etb' => 20000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);

        $scheduleGenerator = app(GenerateRentScheduleAction::class);
        $schedules = $scheduleGenerator->execute($lease);
        $period1 = $schedules->first();

        // 1. Record payment with safe default 'pending'
        $recordPaymentAction = app(RecordRentalPaymentAction::class);
        $payment = $recordPaymentAction->execute($this->tenant, [
            'lease_id' => $lease->id,
            'rent_schedule_id' => $period1->id,
            'amount_etb' => 20000.00,
            'payment_method' => 'telebirr',
            'provider_reference' => 'TB260199482',
            'idempotency_key' => 'IDEMP-TEST-001',
        ]);

        $this->assertSame(RentalPaymentStatus::Pending, $payment->status);
        $this->assertNotNull($payment->payment_reference);
        $this->assertSame(20000.00, (float) $payment->amount_etb);
        $this->assertSame(20000.00, (float) $period1->fresh()->outstanding_balance_etb); // Still outstanding until confirmed!

        // 2. Confirm payment
        $confirmAction = app(ConfirmRentalPaymentAction::class);
        $receipt = $confirmAction->execute($this->landlord, $payment);

        $this->assertInstanceOf(RentalReceipt::class, $receipt);
        $this->assertNotNull($receipt->receipt_number);
        $this->assertStringStartsWith('RCT-', $receipt->receipt_number);
        $this->assertSame(RentalPaymentStatus::Confirmed, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->confirmed_at);

        // Schedule is reconciled
        $period1->refresh();
        $this->assertSame(20000.00, (float) $period1->confirmed_paid_etb);
        $this->assertSame(0.00, (float) $period1->outstanding_balance_etb);
        $this->assertSame(RentScheduleStatus::Paid, $period1->status);
    }

    public function test_srs_189_append_only_financial_adjustments_and_schedule_reconciliation(): void
    {
        $lease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-ADJ-0001',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'monthly_rent_etb' => 25000.00,
            'security_deposit_etb' => 25000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);

        $scheduleGenerator = app(GenerateRentScheduleAction::class);
        $schedules = $scheduleGenerator->execute($lease);
        $period = $schedules->first();

        // Tenant pays 25,000 ETB
        $recordPaymentAction = app(RecordRentalPaymentAction::class);
        $payment = $recordPaymentAction->execute($this->tenant, [
            'lease_id' => $lease->id,
            'rent_schedule_id' => $period->id,
            'amount_etb' => 25000.00,
            'payment_method' => 'cbe_birr',
        ]);

        $confirmAction = app(ConfirmRentalPaymentAction::class);
        $confirmAction->execute($this->landlord, $payment);

        $this->assertSame(0.00, (float) $period->fresh()->outstanding_balance_etb);

        // SRS Section 189: Later, landlord reimburses 3,000 ETB for plumbing repairs done by tenant.
        // The original payment of 25,000 MUST NOT be mutated. A separate adjustment of -3,000 is recorded.
        $adjustAction = app(AppendRentalPaymentAdjustmentAction::class);
        $adjustment = $adjustAction->execute(
            $this->staff,
            $payment,
            -3000.00,
            'Reimbursement credit for tenant plumbing repairs as per receipt #PLUMB-44.'
        );

        $this->assertInstanceOf(RentalPaymentAdjustment::class, $adjustment);
        $this->assertSame(-3000.00, (float) $adjustment->adjustment_amount_etb);
        $this->assertSame($this->staff->id, $adjustment->authorized_by_user_id);

        // Verification: original payment amount is completely unchanged!
        $payment->refresh();
        $this->assertSame(25000.00, (float) $payment->amount_etb, 'Original payment amount must remain unchanged');
        $this->assertSame(22000.00, (float) $payment->effectiveAmountEtb(), 'Effective amount reflects adjustments');

        // Schedule reconciliation: net paid is now 22,000, outstanding balance is 3,000, status is partially_paid
        $period->refresh();
        $this->assertSame(22000.00, (float) $period->confirmed_paid_etb);
        $this->assertSame(3000.00, (float) $period->outstanding_balance_etb);
        $this->assertSame(RentScheduleStatus::PartiallyPaid, $period->status);
    }

    public function test_adjacent_consecutive_leases_allowed_under_half_open_date_ranges(): void
    {
        $syncAction = app(SynchronizeLeaseOccupancyAction::class);

        // Lease 1: 2026-01-01 to 2026-02-01 (Jan 1 up to Feb 1)
        $lease1 = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-ADJ-001',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'monthly_rent_etb' => 25000.00,
            'security_deposit_etb' => 25000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);
        $syncAction->execute($lease1);

        // Lease 2: 2026-02-01 to 2026-03-01 (starts the exact same day Lease 1 ends, half-open [Jan 1, Feb 1) and [Feb 1, Mar 1))
        $lease2 = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-ADJ-002',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-03-01',
            'monthly_rent_etb' => 26000.00,
            'security_deposit_etb' => 26000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);
        // Under half-open interval semantics, this MUST succeed without throwing!
        $syncAction->execute($lease2);

        $this->assertSame(2, LeaseResourceOccupancy::query()->where('property_id', $this->property->id)->count());

        // Now attempt an overlapping lease (e.g. 2026-01-15 to 2026-02-15) which collides with both Lease 1 and Lease 2
        $conflictingLease = Lease::query()->create([
            'uuid' => (string) Str::uuid(),
            'lease_code' => 'LSE-ADJ-CONFLICT',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => '2026-01-15',
            'end_date' => '2026-02-15',
            'monthly_rent_etb' => 30000.00,
            'security_deposit_etb' => 30000.00,
            'payment_frequency' => PaymentFrequency::Monthly,
            'due_day_of_month' => 1,
            'status' => LeaseStatus::Active,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/overlaps an existing active or pending lease/');
        $syncAction->execute($conflictingLease);
    }

    public function test_application_lifecycle_history_audit_trail_is_recorded(): void
    {
        $submitAction = app(SubmitRentalApplicationAction::class);
        $reviewAction = app(ReviewRentalApplicationAction::class);
        $convertAction = app(ConvertApplicationToLeaseAction::class);

        // 1. Submit -> creates history record 'new' -> 'submitted'
        $app = $submitAction->execute($this->tenant, [
            'property_id' => $this->property->id,
            'desired_move_in_date' => '2026-07-01',
            'lease_duration_months' => 12,
            'proposed_monthly_rent_etb' => 35000.00,
            'payment_frequency' => 'monthly',
            'occupant_count' => 1,
        ]);

        $this->assertCount(1, $app->histories);
        $this->assertSame('new', $app->histories->first()->from_status);
        $this->assertSame('submitted', $app->histories->first()->to_status);
        $this->assertSame($this->tenant->id, $app->histories->first()->actor_id);

        // 2. Document review -> creates history record 'submitted' -> 'document_review'
        $reviewAction->execute($this->landlord, $app, 'document_review', 'Please upload employment proof.');
        $app->refresh();
        $this->assertCount(2, $app->histories);
        $this->assertSame('submitted', $app->histories[1]->from_status);
        $this->assertSame('document_review', $app->histories[1]->to_status);
        $this->assertSame($this->landlord->id, $app->histories[1]->actor_id);

        // 3. Approve -> creates history record 'document_review' -> 'approved'
        $reviewAction->execute($this->landlord, $app, 'approve', 'Approved after review.');
        $app->refresh();
        $this->assertCount(3, $app->histories);
        $this->assertSame('document_review', $app->histories[2]->from_status);
        $this->assertSame('approved', $app->histories[2]->to_status);

        // 4. Convert to lease -> creates history record 'approved' -> 'converted_to_lease'
        $lease = $convertAction->execute($this->landlord, $app);
        $app->refresh();
        $this->assertCount(4, $app->histories);
        $this->assertSame('approved', $app->histories[3]->from_status);
        $this->assertSame('converted_to_lease', $app->histories[3]->to_status);
        $this->assertStringContainsString($lease->lease_code, $app->histories[3]->notes);
    }

    public function test_attach_rental_application_document_and_temporary_signed_url(): void
    {
        Storage::fake('s3_private');

        $submitAction = app(SubmitRentalApplicationAction::class);
        $app = $submitAction->execute($this->tenant, [
            'property_id' => $this->property->id,
            'desired_move_in_date' => '2026-08-01',
            'lease_duration_months' => 12,
            'proposed_monthly_rent_etb' => 28000.00,
            'payment_frequency' => 'monthly',
            'occupant_count' => 1,
        ]);

        $file = UploadedFile::fake()->create('paystub.pdf', 250, 'application/pdf');

        $attachAction = app(AttachRentalApplicationDocumentAction::class);
        $appDoc = $attachAction->execute($this->tenant, $app, $file, 'income_proof', 'Monthly Paystub 2026');

        $this->assertInstanceOf(RentalApplicationDocument::class, $appDoc);
        $this->assertSame('income_proof', $appDoc->document_type);
        $this->assertSame('pending', $appDoc->review_status);
        $this->assertSame($app->id, $appDoc->rental_application_id);

        // Verify document relationship and temporary signed URL generation
        $document = $appDoc->document;
        $this->assertNotNull($document);
        $this->assertSame('Monthly Paystub 2026', $document->title);

        $version = $document->versions()->first();
        $this->assertNotNull($version);

        $signedUrl = $version->generateTemporarySignedUrl(5);
        $this->assertStringContainsString('signature=', $signedUrl);
    }
}
