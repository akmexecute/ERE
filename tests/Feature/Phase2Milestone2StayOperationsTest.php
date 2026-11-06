<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Cleaning\Models\Cleaner;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\Dispute\Actions\RecordSecurityDepositPaymentAction;
use App\Domains\Dispute\Actions\ReleaseSecurityDepositAction;
use App\Domains\Dispute\Actions\ResolveDisputeAction;
use App\Domains\Dispute\Actions\SettleSecurityDepositClaimAction;
use App\Domains\Dispute\Actions\SubmitDamageClaimAction;
use App\Domains\Dispute\Models\DamageClaim;
use App\Domains\Dispute\Models\Dispute;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\Host\Models\Host;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Listing\Models\ListingType;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Stay\Actions\ProcessMissedCheckoutFallbackAction;
use App\Domains\Stay\Actions\ProcessStayCheckInAction;
use App\Domains\Stay\Actions\ProcessStayCheckOutAction;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\AccommodationUnitType;
use App\Domains\Stay\Models\CheckIn;
use App\Domains\Stay\Models\CheckOut;
use App\Domains\Stay\Models\HouseManual;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\Stay\Services\SecurityDepositCalculator;
use App\Domains\User\Models\Role;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\EthiopianLocationsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Phase2Milestone2StayOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $hostUser;
    private User $guestUser;
    private User $staffUser;
    private Host $host;
    private Property $property;
    private Listing $listing;
    private StayListingProfile $profile;
    private AccommodationUnit $unit;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(EthiopianLocationsSeeder::class);

        $this->hostUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sara Host',
            'phone' => '+251911223344',
            'status' => 'active',
            'phone_verified_at' => now(),
            'verification_level' => 3,
            'national_id_number' => 'FAN-9876-5432-1000',
        ]);
        $this->hostUser->assignRole('host');

        $this->guestUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abebe Guest',
            'phone' => '+251922334455',
            'status' => 'active',
            'phone_verified_at' => now(),
            'verification_level' => 1,
            'national_id_number' => 'FAN-1234-5678-9000',
        ]);

        $this->staffUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Officer',
            'phone' => '+251933445566',
            'status' => 'active',
            'phone_verified_at' => now(),
            'verification_level' => 5,
        ]);
        $this->staffUser->assignRole('super_admin');

        $this->host = Host::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->hostUser->id,
            'host_type' => 'individual_owner',
            'display_name' => 'Sara Bole Luxury Suites',
            'is_superhost' => true,
        ]);

        $category = PropertyCategory::query()->where('code', 'RES')->firstOrFail();
        $type = PropertyType::query()->where('code', 'VILLA')->firstOrFail();

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-AA-BOLE-RES-00008899',
            'owner_id' => $this->hostUser->id,
            'category_id' => $category->id,
            'property_type_id' => $type->id,
            'title_en' => 'Executive Villa in Bole Medhanialem',
            'status' => 'available',
            'verification_level' => 3,
        ]);

        $listingType = ListingType::query()->firstOrCreate(
            ['code' => 'SHORT_STAY_ENTIRE'],
            ['slug' => 'short-stay-entire', 'name_en' => 'Short Stay', 'transaction_mode' => 'STAY', 'is_active' => true]
        );

        $this->listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-STAY-8899',
            'slug' => 'executive-villa-bole',
            'property_id' => $this->property->id,
            'published_by_user_id' => $this->hostUser->id,
            'listing_type_id' => $listingType->id,
            'transaction_mode' => 'STAY',
            'price_etb' => 10000.00,
            'currency' => 'ETB',
            'status' => ListingStatus::Published,
            'published_at' => now(),
        ]);

        $this->profile = StayListingProfile::query()->create([
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'host_id' => $this->host->id,
            'accommodation_model' => 'entire_place',
            'base_nightly_rate_etb' => 10000.00,
        ]);

        $unitType = AccommodationUnitType::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'property_id' => $this->property->id,
            'name_en' => 'Entire Villa',
            'max_guests' => 8,
            'base_nightly_rate_etb' => 10000.00,
        ]);

        $this->unit = AccommodationUnit::query()->create([
            'unit_type_id' => $unitType->id,
            'property_id' => $this->property->id,
            'unit_number_or_code' => 'VILLA-1',
            'status' => 'ready',
        ]);

        HouseManual::query()->create([
            'stay_listing_profile_id' => $this->profile->id,
            'wifi_network_name' => 'BoleExecutive_5G',
            'wifi_password' => 'BolePass2026!',
            'door_access_code' => '8899#',
            'access_instructions_en' => 'Keypad at front double door',
            'emergency_contact_phone' => '+251911223344',
        ]);

        $this->booking = Booking::query()->create([
            'uuid' => (string) Str::uuid(),
            'booking_code' => 'BKG-2026-000889',
            'idempotency_key' => 'IDEM-TEST-8899',
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'stay_listing_profile_id' => $this->profile->id,
            'accommodation_unit_id' => $this->unit->id,
            'host_id' => $this->host->id,
            'guest_user_id' => $this->guestUser->id,
            'check_in_date' => CarbonImmutable::now()->subDays(2)->toDateString(),
            'check_out_date' => CarbonImmutable::now()->toDateString(),
            'nights_count' => 2,
            'adults_count' => 2,
            'children_count' => 0,
            'infants_count' => 0,
            'pets_count' => 0,
            'accommodation_raw_subtotal_etb' => 20000.00,
            'discount_amount_etb' => 0.00,
            'accommodation_net_subtotal_etb' => 20000.00,
            'cleaning_fee_etb' => 1500.00,
            'platform_fee_etb' => 600.00,
            'tax_amount_etb' => 3000.00,
            'security_deposit_etb' => 6000.00,
            'total_amount_etb' => 25100.00,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now()->subDays(3),
        ]);
    }

    public function test_check_in_records_database_event_and_unlocks_house_manual(): void
    {
        $action = app(ProcessStayCheckInAction::class);

        $checkIn = $action->execute(
            booking: $this->booking,
            user: $this->guestUser,
            method: 'smart_lock_code',
            accessCodeUsed: '8899#'
        );

        $this->assertInstanceOf(CheckIn::class, $checkIn);
        $this->assertSame('smart_lock_code', $checkIn->check_in_method);
        $this->assertNotNull($checkIn->house_manual_unlocked_at);
        $this->assertSame(BookingStatus::CheckedIn, $this->booking->fresh()->status);
        $this->assertDatabaseHas('check_ins', [
            'booking_id' => $this->booking->id,
            'recorded_by_user_id' => $this->guestUser->id,
            'digital_access_code_used' => '8899#',
        ]);
    }

    public function test_unauthorized_user_cannot_record_check_in(): void
    {
        $stranger = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stranger Danger',
            'phone' => '+251999887766',
            'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $action = app(ProcessStayCheckInAction::class);
        $action->execute(booking: $this->booking, user: $stranger);
    }

    public function test_checkout_transitions_booking_and_creates_cleaning_task_exactly_once(): void
    {
        // 1. Check in first
        app(ProcessStayCheckInAction::class)->execute($this->booking, $this->guestUser);

        // 2. Perform checkout with condition report
        $action = app(ProcessStayCheckOutAction::class);
        $checkOut = $action->execute(
            booking: $this->booking,
            user: $this->guestUser,
            propertyCondition: 'good',
            conditionNotes: 'All keys returned to lockbox, windows secured.',
            conditionPhotos: ['https://storage.ere.et/checkout/photo1.jpg']
        );

        $this->assertInstanceOf(CheckOut::class, $checkOut);
        $this->assertSame('good', $checkOut->property_condition);
        $this->assertFalse($checkOut->damage_claim_required);
        $this->assertSame(BookingStatus::CheckedOut, $this->booking->fresh()->status);

        // 3. Verify exactly one cleaning task was triggered
        $tasks = CleaningTask::query()->where('booking_id', $this->booking->id)->get();
        $this->assertCount(1, $tasks);
        $this->assertSame(CleaningTaskStatus::Pending, $tasks->first()->status);

        // 4. Verify accommodation unit status is updated to cleaning
        $this->assertSame('cleaning', $this->unit->fresh()->status);
    }

    public function test_missed_checkout_does_not_forfeit_deposit_and_notifies_host_for_inspection(): void
    {
        // Guest checked in
        app(ProcessStayCheckInAction::class)->execute($this->booking, $this->guestUser);

        // Guest leaves without completing form -> scheduled fallback triggers
        $fallback = app(ProcessMissedCheckoutFallbackAction::class);
        $processed = $fallback->execute(CarbonImmutable::now()->toDateString());

        $this->assertCount(1, $processed);
        $this->assertSame($this->booking->id, $processed[0]['booking_id']);
        $this->assertTrue($processed[0]['host_notified']);

        $checkOut = CheckOut::query()->where('booking_id', $this->booking->id)->firstOrFail();
        $this->assertTrue($checkOut->is_missed_checkout_fallback);
        $this->assertSame('uninspected_by_guest', $checkOut->property_condition);

        // Crucial User Rule Assertions:
        // A missed checkout form must NOT forfeit deposit!
        $this->assertFalse($checkOut->damage_claim_required);
        // Cleaning task is triggered for turnover
        $this->assertDatabaseHas('cleaning_tasks', ['booking_id' => $this->booking->id]);
    }

    public function test_housekeeping_pipeline_cleaners_submit_for_inspection_and_only_inspector_marks_ready(): void
    {
        // 1. Checkout creates pending cleaning task
        app(ProcessStayCheckInAction::class)->execute($this->booking, $this->guestUser);
        app(ProcessStayCheckOutAction::class)->execute($this->booking, $this->guestUser);

        /** @var CleaningTask $task */
        $task = CleaningTask::query()->where('booking_id', $this->booking->id)->firstOrFail();

        $cleanerUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tigist Alemu',
            'phone' => '+251911998877',
            'status' => 'active',
        ]);
        $cleaner = Cleaner::query()->create([
            'uuid' => (string) Str::uuid(),
            'host_id' => $this->host->id,
            'name' => 'Tigist Alemu',
            'phone' => '+251911998877',
            'is_active' => true,
        ]);

        $transitionAction = app(\App\Domains\Cleaning\Actions\TransitionCleaningTaskAction::class);

        // Step 1: Assign cleaner
        $task = $transitionAction->execute(
            task: $task,
            user: $this->hostUser,
            targetStatus: CleaningTaskStatus::Assigned,
            assignCleanerId: $cleaner->id
        );
        $this->assertSame(CleaningTaskStatus::Assigned, $task->status);

        // Step 2: Cleaner begins cleaning
        $task = $transitionAction->execute(
            task: $task,
            user: $cleanerUser,
            targetStatus: CleaningTaskStatus::Cleaning
        );
        $this->assertSame(CleaningTaskStatus::Cleaning, $task->status);
        $this->assertNotNull($task->started_at);

        // Step 3: Cleaner submits checklist for inspection
        $task = $transitionAction->execute(
            task: $task,
            user: $cleanerUser,
            targetStatus: CleaningTaskStatus::Inspection,
            checklist: ['Linens replaced' => true, 'Bathrooms sanitized' => true]
        );
        $this->assertSame(CleaningTaskStatus::Inspection, $task->status);

        // Cleaner CANNOT self-approve work as ready
        try {
            $transitionAction->execute(
                task: $task,
                user: $cleanerUser,
                targetStatus: CleaningTaskStatus::Ready
            );
            $this->fail('Cleaner should not be authorized to approve cleaning task as ready.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertStringContainsString('inspector', $e->getMessage());
        }

        // Host / Authorized Inspector approves work
        $task = $transitionAction->execute(
            task: $task,
            user: $this->hostUser,
            targetStatus: CleaningTaskStatus::Ready,
            inspectionNotes: 'Passed inspection with flying colors'
        );

        $this->assertSame(CleaningTaskStatus::Ready, $task->status);
        $this->assertNotNull($task->ready_at);
        $this->assertSame($this->hostUser->id, $task->inspected_by_user_id);
        $this->assertSame('ready', $this->unit->fresh()->status);
    }

    public function test_security_deposit_default_calculator_and_host_override(): void
    {
        // 1. Entire villa calculation: 50% of 10,000 + 2 nights * 300 = 5,600 rounded to 5,600
        $computed = SecurityDepositCalculator::calculate(
            accommodationModel: 'entire_place',
            nightlyRateEtb: 10000.0,
            nightsCount: 2
        );
        $this->assertEquals(5600.0, $computed);

        // 2. Host override within allowed limits (e.g. 7,500 ETB)
        $override = SecurityDepositCalculator::calculate(
            accommodationModel: 'entire_place',
            nightlyRateEtb: 10000.0,
            nightsCount: 2,
            hostCustomDepositEtb: 7500.0
        );
        $this->assertEquals(7500.0, $override);

        // 3. Clamped if below platform minimum
        $clamped = SecurityDepositCalculator::calculate(
            accommodationModel: 'entire_place',
            nightlyRateEtb: 10000.0,
            nightsCount: 2,
            hostCustomDepositEtb: 200.0
        );
        $this->assertEquals(SecurityDepositCalculator::DEFAULT_MIN_DEPOSIT_ETB, $clamped);
    }

    public function test_system_and_manual_deposit_payment_verification(): void
    {
        $depositAction = app(RecordSecurityDepositPaymentAction::class);

        // 1. System-Managed Payment
        $systemDeposit = $depositAction->execute(
            booking: $this->booking,
            depositAmountEtb: 6000.00,
            paymentMethod: 'system_managed',
            paymentReference: 'TXN-ESCROW-992211'
        );
        $this->assertSame('held', $systemDeposit->status);
        $this->assertSame('system_managed', $systemDeposit->payment_method);
        $this->assertSame('TXN-ESCROW-992211', $systemDeposit->custody_transaction_ref);

        // 2. Manual Payment (e.g. Telebirr reference) - Pending Verification
        $manualDeposit = $depositAction->execute(
            booking: $this->booking,
            depositAmountEtb: 6000.00,
            paymentMethod: 'telebirr_manual',
            paymentReference: 'TB-SLIP-776655'
        );
        $this->assertSame('pending_verification', $manualDeposit->payment_status);

        // 3. Host / Staff confirms receipt
        $confirmedDeposit = $depositAction->execute(
            booking: $this->booking,
            depositAmountEtb: 6000.00,
            paymentMethod: 'telebirr_manual',
            paymentReference: 'TB-SLIP-776655',
            verifier: $this->hostUser
        );
        $this->assertSame('held', $confirmedDeposit->payment_status);
        $this->assertSame($this->hostUser->id, $confirmedDeposit->verified_by_user_id);
    }

    public function test_damage_claim_within_48_hours_deducts_on_acceptance_or_opens_dispute(): void
    {
        // 1. Setup checkout and held deposit
        app(ProcessStayCheckInAction::class)->execute($this->booking, $this->guestUser);
        app(ProcessStayCheckOutAction::class)->execute($this->booking, $this->guestUser);
        app(RecordSecurityDepositPaymentAction::class)->execute(
            $this->booking,
            6000.00,
            'system_managed',
            'TXN-ESCROW-1'
        );

        // 2. Host submits damage claim within 48h
        $claimAction = app(SubmitDamageClaimAction::class);
        $claim = $claimAction->execute(
            booking: $this->booking,
            hostUser: $this->hostUser,
            claimedAmountEtb: 2000.00,
            description: 'Broken hand-carved coffee table glass',
            evidencePhotos: ['https://storage.ere.et/claims/table.jpg']
        );

        $this->assertInstanceOf(DamageClaim::class, $claim);
        $this->assertSame('submitted', $claim->status);
        $this->assertEquals(2000.00, $claim->claimed_amount_etb);

        // 3. Guest Accepts Claim -> Deducts 2,000 ETB, Releases 4,000 ETB
        $settleAction = app(SettleSecurityDepositClaimAction::class);
        $result = $settleAction->execute(
            heldDepositEtb: 6000.00,
            claimedDamageEtb: 2000.00,
            guestDecision: 'accept',
            claim: $claim,
            guestUser: $this->guestUser,
            guestResponseNotes: 'Agreed, sorry for the accident.'
        );

        $this->assertSame('partially_deducted', $result['deposit_status']);
        $this->assertEquals(2000.00, $result['deducted_amount_etb']);
        $this->assertEquals(4000.00, $result['released_to_guest_etb']);
        $this->assertSame('accepted_by_guest', $claim->fresh()->status);
        $this->assertEquals(2000.00, $this->booking->securityDeposit->fresh()->deducted_amount_etb);
        $this->assertEquals(4000.00, $this->booking->securityDeposit->fresh()->released_to_guest_etb);
    }

    public function test_guest_dispute_locks_funds_and_staff_resolution(): void
    {
        app(ProcessStayCheckInAction::class)->execute($this->booking, $this->guestUser);
        app(ProcessStayCheckOutAction::class)->execute($this->booking, $this->guestUser);
        app(RecordSecurityDepositPaymentAction::class)->execute(
            $this->booking,
            6000.00,
            'system_managed',
            'TXN-ESCROW-2'
        );

        $claim = app(SubmitDamageClaimAction::class)->execute(
            booking: $this->booking,
            hostUser: $this->hostUser,
            claimedAmountEtb: 2500.00,
            description: 'Stained silk sofa fabric'
        );

        // Guest Disputes Claim
        $settleAction = app(SettleSecurityDepositClaimAction::class);
        $result = $settleAction->execute(
            heldDepositEtb: 6000.00,
            claimedDamageEtb: 2500.00,
            guestDecision: 'dispute',
            claim: $claim,
            guestUser: $this->guestUser,
            guestResponseNotes: 'Sofa was already stained upon check-in; host agreed during greeting.'
        );

        $this->assertSame('disputed', $result['deposit_status']);
        $this->assertTrue($result['dispute_opened']);
        $this->assertSame('disputed', $this->booking->securityDeposit->fresh()->status);

        // Formal dispute case exists in evidence stage
        $dispute = Dispute::query()->where('damage_claim_id', $claim->id)->firstOrFail();
        $this->assertSame('evidence', $dispute->status);

        // Staff adjudicates dispute: 1,000 ETB to host, 5,000 ETB refunded to guest
        $resolveAction = app(ResolveDisputeAction::class);
        $resolved = $resolveAction->execute(
            dispute: $dispute,
            staffUser: $this->staffUser,
            refundToGuestEtb: 5000.00,
            payoutToHostEtb: 1000.00,
            resolutionSummary: 'Pre-existing stain substantiated by check-in photos; partial 1,000 ETB cleaning fee awarded to host.'
        );

        $this->assertSame('closed', $resolved->status);
        $this->assertEquals(5000.00, $resolved->refund_to_guest_etb);
        $this->assertEquals(1000.00, $resolved->payout_to_host_etb);
        $this->assertSame('partially_deducted', $this->booking->securityDeposit->fresh()->status);
    }

    public function test_eligible_deposit_with_no_claims_automatically_releases(): void
    {
        $deposit = app(RecordSecurityDepositPaymentAction::class)->execute(
            $this->booking,
            6000.00,
            'system_managed',
            'TXN-ESCROW-3'
        );

        $releaseAction = app(ReleaseSecurityDepositAction::class);
        $released = $releaseAction->execute($deposit);

        $this->assertSame('released', $released->status);
        $this->assertEquals(6000.00, $released->released_to_guest_etb);
        $this->assertNotNull($released->released_at);
    }

    public function test_missed_checkout_canonical_deadline_anchor_delayed_scheduler_and_duplicate_prevention(): void
    {
        // 1. Check in
        app(ProcessStayCheckInAction::class)->execute($this->booking, $this->guestUser);

        // 2. Delayed scheduler execution (runs 8 hours after checkout time)
        $fallback = app(ProcessMissedCheckoutFallbackAction::class);
        $processed = $fallback->execute(CarbonImmutable::now()->toDateString());
        $this->assertCount(1, $processed);

        $checkOut = CheckOut::query()->where('booking_id', $this->booking->id)->firstOrFail();
        $this->assertTrue($checkOut->is_missed_checkout_fallback);

        // Verify deadline is anchored to scheduled departure time (11:00 AM on check_out_date)
        $expectedScheduledCheckout = CarbonImmutable::parse($this->booking->check_out_date)->setTime(11, 0, 0);
        $this->assertEquals($expectedScheduledCheckout->toDateTimeString(), $checkOut->checked_out_at->toDateTimeString());

        // 3. Duplicate scheduler execution: Must be idempotent and never duplicate checkout or cleaning task
        $secondRun = $fallback->execute(CarbonImmutable::now()->toDateString());
        $this->assertCount(0, $secondRun);
        $this->assertSame(1, CheckOut::query()->where('booking_id', $this->booking->id)->count());
        $this->assertSame(1, CleaningTask::query()->where('booking_id', $this->booking->id)->count());

        // 4. Submitting claim past the 48-hour deadline is rejected
        $claimAction = app(SubmitDamageClaimAction::class);
        $this->travelTo($expectedScheduledCheckout->addHours(49));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $claimAction->execute(
            booking: $this->booking,
            hostUser: $this->hostUser,
            claimedAmountEtb: 1500.00,
            description: 'Late submitted claim past deadline'
        );
    }

    public function test_manual_deposit_duplicate_verification_prevention(): void
    {
        $depositAction = app(RecordSecurityDepositPaymentAction::class);

        // 1. Initial unverified submission
        $deposit = $depositAction->execute(
            booking: $this->booking,
            depositAmountEtb: 5000.00,
            paymentMethod: 'cbe_birr',
            paymentReference: 'CBE-REF-112233'
        );
        $this->assertSame('pending', $deposit->status);
        $this->assertSame('pending_verification', $deposit->payment_status);
        $this->assertSame('manual_host_custody', $deposit->custody_provider);

        // 2. Host confirms receipt
        $verified = $depositAction->execute(
            booking: $this->booking,
            depositAmountEtb: 5000.00,
            paymentMethod: 'cbe_birr',
            paymentReference: 'CBE-REF-112233',
            verifier: $this->hostUser
        );
        $this->assertSame('held', $verified->status);
        $this->assertSame('held', $verified->payment_status);
        $this->assertNotNull($verified->verified_at);

        // 3. Duplicate verification attempt rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Manual deposit payment has already been verified.');
        $depositAction->execute(
            booking: $this->booking,
            depositAmountEtb: 5000.00,
            paymentMethod: 'cbe_birr',
            paymentReference: 'CBE-REF-112233',
            verifier: $this->hostUser
        );
    }

    public function test_replaying_claim_settlement_cannot_repeat_financial_effect(): void
    {
        app(ProcessStayCheckInAction::class)->execute($this->booking, $this->guestUser);
        app(ProcessStayCheckOutAction::class)->execute($this->booking, $this->guestUser);
        app(RecordSecurityDepositPaymentAction::class)->execute(
            $this->booking,
            6000.00,
            'system_managed',
            'TXN-ESCROW-REPLAY'
        );

        $claim = app(SubmitDamageClaimAction::class)->execute(
            booking: $this->booking,
            hostUser: $this->hostUser,
            claimedAmountEtb: 2000.00,
            description: 'Broken window latch'
        );

        $settleAction = app(SettleSecurityDepositClaimAction::class);
        $result = $settleAction->execute(
            heldDepositEtb: 6000.00,
            claimedDamageEtb: 2000.00,
            guestDecision: 'accept',
            claim: $claim,
            guestUser: $this->guestUser
        );
        $this->assertSame('partially_deducted', $result['deposit_status']);

        // Replaying settlement on resolved claim must fail
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has already been resolved');
        $settleAction->execute(
            heldDepositEtb: 6000.00,
            claimedDamageEtb: 2000.00,
            guestDecision: 'accept',
            claim: $claim,
            guestUser: $this->guestUser
        );
    }
}
