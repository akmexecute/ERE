<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Lease\Actions\AcknowledgePropertyInspectionAction;
use App\Domains\Lease\Actions\RecordPropertyInspectionAction;
use App\Domains\Lease\Actions\SignLeaseContractAction;
use App\Domains\RentalFinancial\Actions\GenerateRentScheduleAction;
use App\Domains\Lease\Enums\InspectionType;
use App\Domains\Lease\Enums\LeaseStatus;
use App\Domains\Lease\Enums\PaymentFrequency;
use App\Domains\Lease\Models\Lease;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\RentalFinancial\Actions\AppendRentalPaymentAdjustmentAction;
use App\Domains\RentalFinancial\Actions\ConfirmRentalPaymentAction;
use App\Domains\RentalFinancial\Actions\RecordRentalPaymentAction;
use App\Domains\Tenant\Actions\SubmitRentalApplicationAction;
use App\Domains\Tenant\Enums\RentalApplicationStatus;
use App\Domains\Tenant\Enums\TenantStatus;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\TenantProfile;
use App\Domains\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Phase4RentalDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Categories & Roles exist
        $this->call([
            RbacRolePermissionSeeder::class,
            PropertyTaxonomySeeder::class,
        ]);

        // 2. Setup Super Admin & Primary User (Abebe Bikila)
        $adminUser = User::query()->firstOrNew(['phone' => '+251911234567']);
        $adminUser->forceFill([
            'uuid' => $adminUser->uuid ?? (string) Str::uuid(),
            'name' => 'Abebe Bikila',
            'email' => 'admin@ethioproperty.et',
            'status' => 'active',
            'verification_level' => 3,
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-9876-5432-1001',
            'national_id_type' => 'fayda',
            'identity_verified_at' => now(),
        ])->save();
        $adminUser->assignRoleBySlug('super_admin');
        $adminUser->assignRoleBySlug('property_owner');
        $adminUser->assignRoleBySlug('tenant');

        // 3. Setup Dedicated Tenant User
        $tenantUser = User::query()->firstOrNew(['phone' => '+251911445566']);
        $tenantUser->forceFill([
            'uuid' => $tenantUser->uuid ?? (string) Str::uuid(),
            'name' => 'Dawit Kebede',
            'email' => 'dawit.kebede@ethioproperty.et',
            'status' => 'active',
            'verification_level' => 3,
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-9876-5432-1002',
            'national_id_type' => 'fayda',
            'identity_verified_at' => now(),
        ])->save();
        $tenantUser->assignRoleBySlug('tenant');

        // 4. Setup Dedicated Landlord User
        $landlordUser = User::query()->firstOrNew(['phone' => '+251911778899']);
        $landlordUser->forceFill([
            'uuid' => $landlordUser->uuid ?? (string) Str::uuid(),
            'name' => 'Almaz Ayele',
            'email' => 'almaz.ayele@ethioproperty.et',
            'status' => 'active',
            'verification_level' => 3,
            'phone_verified_at' => now(),
            'national_id_number' => 'FAN-9876-5432-1003',
            'national_id_type' => 'fayda',
            'identity_verified_at' => now(),
        ])->save();
        $landlordUser->assignRoleBySlug('property_owner');

        // 5. Setup Tenant Profile for Dawit
        $tenantProfile = TenantProfile::query()->firstOrCreate(
            ['user_id' => $tenantUser->id],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_code' => 'TNT-2026-0000001',
                'occupation' => 'Senior Software Architect',
                'employer_name' => 'Commercial Bank of Ethiopia',
                'monthly_income_etb' => 85000.00,
                'emergency_contact_name' => 'Helen Tadesse',
                'emergency_contact_phone' => '+251911998877',
                'emergency_contact_relationship' => 'Spouse',
                'is_identity_verified' => true,
                'status' => TenantStatus::Active,
                'verified_at' => now(),
            ]
        );

        // Also ensure Abebe has a profile so he can test tenant views
        TenantProfile::query()->firstOrCreate(
            ['user_id' => $adminUser->id],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_code' => 'TNT-2026-0000002',
                'occupation' => 'Managing Director',
                'employer_name' => 'Addis Ababa Tech Corp',
                'monthly_income_etb' => 120000.00,
                'emergency_contact_name' => 'Sara Bikila',
                'emergency_contact_phone' => '+251911001122',
                'emergency_contact_relationship' => 'Sister',
                'is_identity_verified' => true,
                'status' => TenantStatus::Active,
                'verified_at' => now(),
            ]
        );

        // 6. Seed Properties & Listings
        $resCatId = DB::table('property_categories')->where('code', 'RES')->value('id') ?? 1;
        $aptTypeId = DB::table('property_types')->where('code', 'APT')->value('id') ?? 1;
        $villaTypeId = DB::table('property_types')->where('code', 'VILLA')->value('id') ?? 1;

        $prop1 = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-BOLE-RES-00001001'],
            [
                'uuid' => (string) Str::uuid(),
                'owner_id' => $landlordUser->id,
                'category_id' => $resCatId,
                'property_type_id' => $aptTypeId,
                'title_en' => 'Bole Atlas Executive 3-Bedroom Apartment',
                'title_am' => 'ቦሌ አትላስ ዘመናዊ ባለ 3 መኝታ አፓርታማ',
                'description_en' => 'Spacious, high-floor modern apartment with standby generator and fiber internet in Bole Atlas.',
                'address_line' => 'Behind Atlas Hotel, Bole, Addis Ababa',
                'bedrooms' => 3,
                'bathrooms' => 2,
                'building_area_sqm' => 165.00,
                'status' => 'available',
                'verification_level' => 3,
            ]
        );

        $listing1 = Listing::query()->firstOrCreate(
            ['listing_code' => 'LST-2026-0000001'],
            [
                'uuid' => (string) Str::uuid(),
                'slug' => 'bole-atlas-executive-3-bedroom-apartment-for-rent',
                'property_id' => $prop1->id,
                'published_by_user_id' => $landlordUser->id,
                'listing_type_id' => 2,
                'transaction_mode' => 'RENT',
                'price_etb' => 45000.00,
                'currency' => 'ETB',
                'price_period' => 'per_month',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        $prop2 = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-KZ-RES-00001002'],
            [
                'uuid' => (string) Str::uuid(),
                'owner_id' => $landlordUser->id,
                'category_id' => $resCatId,
                'property_type_id' => $villaTypeId,
                'title_en' => 'Kazanchis Diplomatic Villa with Private Garden',
                'title_am' => 'ካዛንቺስ ዲፕሎማቲክ ቪላ ከመናፈሻ ጋር',
                'description_en' => 'Luxury 4-bedroom villa suitable for diplomatic or executive staff in Kazanchis.',
                'address_line' => 'ECA Quarter, Kazanchis, Addis Ababa',
                'bedrooms' => 4,
                'bathrooms' => 3,
                'building_area_sqm' => 320.00,
                'status' => 'available',
                'verification_level' => 3,
            ]
        );

        Listing::query()->firstOrCreate(
            ['listing_code' => 'LST-2026-0000002'],
            [
                'uuid' => (string) Str::uuid(),
                'slug' => 'kazanchis-diplomatic-villa-with-private-garden-for-rent',
                'property_id' => $prop2->id,
                'published_by_user_id' => $landlordUser->id,
                'listing_type_id' => 2,
                'transaction_mode' => 'RENT',
                'price_etb' => 85000.00,
                'currency' => 'ETB',
                'price_period' => 'per_month',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        $prop3 = Property::query()->firstOrCreate(
            ['property_code' => 'ETH-AA-CMC-RES-00001003'],
            [
                'uuid' => (string) Str::uuid(),
                'owner_id' => $adminUser->id,
                'category_id' => $resCatId,
                'property_type_id' => $aptTypeId,
                'title_en' => 'CMC Sun City Modern 2-Bedroom Flat',
                'title_am' => 'ሲኤምሲ ሰን ሲቲ ዘመናዊ ባለ 2 መኝታ ቤት',
                'description_en' => 'Well-lit flat close to Light Rail Transit in CMC residential district.',
                'address_line' => 'Near CMC Michael Church, Addis Ababa',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'building_area_sqm' => 95.00,
                'status' => 'available',
                'verification_level' => 2,
            ]
        );

        Listing::query()->firstOrCreate(
            ['listing_code' => 'LST-2026-0000003'],
            [
                'uuid' => (string) Str::uuid(),
                'slug' => 'cmc-sun-city-modern-2-bedroom-flat-for-rent',
                'property_id' => $prop3->id,
                'published_by_user_id' => $adminUser->id,
                'listing_type_id' => 2,
                'transaction_mode' => 'RENT',
                'price_etb' => 30000.00,
                'currency' => 'ETB',
                'price_period' => 'per_month',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        // 7. Seed Rental Applications
        $app1 = RentalApplication::query()->firstOrCreate(
            ['application_code' => 'APP-2026-0000001'],
            [
                'uuid' => (string) Str::uuid(),
                'property_id' => $prop1->id,
                'listing_id' => $listing1->id,
                'tenant_id' => $tenantUser->id,
                'tenant_profile_id' => $tenantProfile->id,
                'landlord_id' => $landlordUser->id,
                'desired_move_in_date' => now()->addDays(7)->toDateString(),
                'lease_duration_months' => 12,
                'proposed_monthly_rent_etb' => 45000.00,
                'payment_frequency' => PaymentFrequency::Monthly,
                'occupant_count' => 2,
                'applicant_message' => 'We are quiet professionals working nearby in Bole. We look forward to a long-term tenancy.',
                'status' => RentalApplicationStatus::Submitted,
            ]
        );

        $app2 = RentalApplication::query()->firstOrCreate(
            ['application_code' => 'APP-2026-0000002'],
            [
                'uuid' => (string) Str::uuid(),
                'property_id' => $prop2->id,
                'tenant_id' => $tenantUser->id,
                'tenant_profile_id' => $tenantProfile->id,
                'landlord_id' => $landlordUser->id,
                'desired_move_in_date' => now()->addDays(14)->toDateString(),
                'lease_duration_months' => 24,
                'proposed_monthly_rent_etb' => 85000.00,
                'payment_frequency' => PaymentFrequency::Quarterly,
                'occupant_count' => 3,
                'applicant_message' => 'Approved application ready for conversion to formal contract.',
                'status' => RentalApplicationStatus::Approved,
                'reviewed_by' => $landlordUser->id,
                'reviewed_at' => now(),
            ]
        );

        // Also add an application submitted by Abebe Bikila so he can see it directly
        RentalApplication::query()->firstOrCreate(
            ['application_code' => 'APP-2026-0000003'],
            [
                'uuid' => (string) Str::uuid(),
                'property_id' => $prop1->id,
                'listing_id' => $listing1->id,
                'tenant_id' => $adminUser->id,
                'tenant_profile_id' => TenantProfile::where('user_id', $adminUser->id)->value('id'),
                'landlord_id' => $landlordUser->id,
                'desired_move_in_date' => now()->addDays(10)->toDateString(),
                'lease_duration_months' => 12,
                'proposed_monthly_rent_etb' => 45000.00,
                'payment_frequency' => PaymentFrequency::Monthly,
                'occupant_count' => 1,
                'applicant_message' => 'Direct tenancy request for executive accommodation.',
                'status' => RentalApplicationStatus::Submitted,
            ]
        );

        // 8. Seed Active Lease with Bilateral Signing, Inspections, Schedule, Payment & Receipt
        $lease = Lease::query()->firstOrCreate(
            ['lease_code' => 'LSE-2026-0000001'],
            [
                'uuid' => (string) Str::uuid(),
                'rental_application_id' => $app1->id,
                'property_id' => $prop1->id,
                'tenant_id' => $tenantUser->id,
                'landlord_id' => $landlordUser->id,
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->startOfMonth()->addYear()->subDay()->toDateString(),
                'monthly_rent_etb' => 45000.00,
                'security_deposit_etb' => 90000.00,
                'payment_frequency' => PaymentFrequency::Monthly,
                'due_day_of_month' => 1,
                'contract_terms' => [
                    'standard_rules' => 'Tenant agrees to keep property in clean condition and pay utilities.',
                    'notice_period_days' => 60,
                    'maintenance_responsibility' => 'Landlord covers major structural and plumbing repairs.',
                ],
                'status' => LeaseStatus::Active,
                'tenant_signed_at' => now()->subDays(5),
                'tenant_signature_ip' => '197.156.120.34',
                'landlord_signed_at' => now()->subDays(5),
                'landlord_signature_ip' => '197.156.120.12',
                'move_in_at' => now()->startOfMonth(),
            ]
        );

        // Move-In Inspection
        $recordInspection = app(RecordPropertyInspectionAction::class);
        $ackInspection = app(AcknowledgePropertyInspectionAction::class);

        $inspection = $lease->inspections()->where('inspection_type', 'move_in')->first();
        if (! $inspection) {
            $inspection = $recordInspection->execute($landlordUser, $lease, InspectionType::MoveIn, [
                'inspection_type' => 'move_in',
                'inspection_date' => now()->startOfMonth()->toDateString(),
                'electricity_meter_reading' => 14250.50,
                'water_meter_reading' => 340.20,
                'notes' => 'All rooms fresh painted, 2 keys delivered, electricity and water meters verified in working order.',
            ]);

            $ackInspection->execute($tenantUser, $inspection);
            $ackInspection->execute($landlordUser, $inspection);
        }

        // Generate Rent Schedules
        $scheduleGen = app(GenerateRentScheduleAction::class);
        $schedules = $scheduleGen->execute($lease);

        // Record & Confirm First Month Payment
        $firstPeriod = $schedules->first();
        if ($firstPeriod && $firstPeriod->payments()->count() === 0) {
            $recordAction = app(RecordRentalPaymentAction::class);
            $payment = $recordAction->execute($tenantUser, [
                'lease_id' => $lease->id,
                'rent_schedule_id' => $firstPeriod->id,
                'amount_etb' => 45000.00,
                'payment_method' => 'telebirr',
                'provider_reference' => 'TLB9481920491',
                'notes' => 'Initial monthly rent settlement via Telebirr merchant checkout',
            ]);

            $confirmAction = app(ConfirmRentalPaymentAction::class);
            $confirmAction->execute($landlordUser, $payment);

            // Append-Only Adjustment (SRS Section 189)
            $adjustAction = app(AppendRentalPaymentAdjustmentAction::class);
            $adjustAction->execute(
                $landlordUser,
                $payment,
                500.00,
                'Compound private security and waste disposal monthly contribution'
            );
        }

        // Also create a lease where Abebe Bikila is the Landlord and Tenant Dawit is the tenant,
        // so Abebe can directly view and interact with everything when logged in!
        $adminLease = Lease::query()->firstOrCreate(
            ['lease_code' => 'LSE-2026-0000002'],
            [
                'uuid' => (string) Str::uuid(),
                'property_id' => $prop3->id,
                'tenant_id' => $tenantUser->id,
                'landlord_id' => $adminUser->id,
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->startOfMonth()->addYear()->subDay()->toDateString(),
                'monthly_rent_etb' => 30000.00,
                'security_deposit_etb' => 60000.00,
                'payment_frequency' => PaymentFrequency::Monthly,
                'due_day_of_month' => 5,
                'contract_terms' => [
                    'standard_rules' => 'Residential occupancy only. No commercial subletting.',
                ],
                'status' => LeaseStatus::Active,
                'tenant_signed_at' => now()->subDays(2),
                'tenant_signature_ip' => '197.156.120.34',
                'landlord_signed_at' => now()->subDays(2),
                'landlord_signature_ip' => '127.0.0.1',
            ]
        );

        $adminSchedules = $scheduleGen->execute($adminLease);
        $adminFirstPeriod = $adminSchedules->first();
        if ($adminFirstPeriod && $adminFirstPeriod->payments()->count() === 0) {
            $recordAction = app(RecordRentalPaymentAction::class);
            $payment = $recordAction->execute($tenantUser, [
                'lease_id' => $adminLease->id,
                'rent_schedule_id' => $adminFirstPeriod->id,
                'amount_etb' => 30000.00,
                'payment_method' => 'cbe_birr',
                'provider_reference' => 'CBE7788990011',
                'notes' => 'Settled via CBE Birr mobile banking',
            ]);

            $confirmAction = app(ConfirmRentalPaymentAction::class);
            $confirmAction->execute($adminUser, $payment);
        }

        // 9. Sync Sequence Watermarks to prevent any collision
        $year = date('Y');
        foreach (['APP', 'TNT', 'LSE', 'PAY', 'RCT'] as $prefix) {
            $prefixKey = "{$prefix}-{$year}";
            $maxSeq = match ($prefix) {
                'APP' => RentalApplication::count(),
                'TNT' => TenantProfile::count(),
                'LSE' => Lease::count(),
                'PAY' => \App\Domains\RentalFinancial\Models\RentalPayment::count(),
                'RCT' => \App\Domains\RentalFinancial\Models\RentalReceipt::count(),
            };
            DB::table('property_code_sequences')->updateOrInsert(
                ['prefix_key' => $prefixKey],
                ['last_value' => max($maxSeq, 10), 'updated_at' => now()]
            );
        }
    }
}
