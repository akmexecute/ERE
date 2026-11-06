<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Booking\Models\Booking;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\Developer\Models\UnitReservation;
use App\Domains\Financial\Actions\HoldOrReleasePayoutAction;
use App\Domains\Financial\Actions\ProcessHostPayoutAction;
use App\Domains\Financial\Actions\ScheduleHostPayoutAction;
use App\Domains\Financial\Enums\LedgerAccountCode;
use App\Domains\Financial\Enums\PayoutSchedule;
use App\Domains\Financial\Enums\PayoutStatus;
use App\Domains\Financial\Models\HostPayout;
use App\Domains\Financial\Models\HostPayoutDestination;
use App\Domains\Financial\Models\JournalPostingGroup;
use App\Domains\Financial\Models\LedgerAccount;
use App\Domains\Financial\Models\LedgerEntry;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Financial\Services\HostPayoutCalculator;
use App\Domains\Host\Models\Host;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Actions\InitiatePaymentAction;
use App\Domains\Payment\Actions\ProcessWebhookEventAction;
use App\Domains\Payment\Actions\ReviewBankTransferAction;
use App\Domains\Payment\Actions\SubmitBankTransferSlipAction;
use App\Domains\Payment\Enums\BankTransferVerificationStatus;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Models\BankTransferProof;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\PaymentIdempotency;
use App\Domains\Payment\Models\WebhookEvent;
use App\Domains\Payment\Services\PaymentManager;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType as PropType;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\Company;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Phase5PaymentsWebhooksAndHostPayoutsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $seller;
    private User $hostUser;
    private User $officer;
    private Host $host;
    private Property $property;
    private Booking $booking;
    private PropertyOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->customer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abebe Bikila',
            'phone' => '+251911000111',
            'status' => 'active',
        ]);
        $this->customer->assignRoleBySlug('buyer');

        $this->seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tirunesh Dibaba',
            'phone' => '+251911000222',
            'status' => 'active',
        ]);
        $this->seller->assignRoleBySlug('seller');

        $this->hostUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Haile Gebrselassie',
            'phone' => '+251911000333',
            'status' => 'active',
        ]);
        $this->hostUser->assignRoleBySlug('host');

        $this->officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Finance Officer Aster',
            'phone' => '+251911000444',
            'status' => 'active',
        ]);
        $this->officer->assignRoleBySlug('super_admin');

        $this->host = Host::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->hostUser->id,
            'display_name' => 'Haile Hospitality',
            'payout_schedule' => 'after_checkin',
            'status' => 'active',
        ]);

        $catId = PropertyCategory::query()->value('id');
        $typeId = PropType::query()->value('id');

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-2026-99001',
            'owner_id' => $this->seller->id,
            'category_id' => $catId,
            'property_type_id' => $typeId,
            'title_en' => 'Entoto Mountain Luxury Retreat',
            'status' => 'available',
            'verification_level' => 3,
        ]);

        // Create Stay Listing Profile & Accommodation Unit
        $listingType = \App\Domains\Listing\Models\ListingType::query()->first();
        $listing = \App\Domains\Listing\Models\Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-99001',
            'property_id' => $this->property->id,
            'published_by_user_id' => $this->seller->id,
            'listing_type_id' => $listingType?->id ?? 1,
            'transaction_mode' => 'STAY',
            'title_en' => 'Entoto Luxury Lodge Stay',
            'slug' => 'entoto-luxury-lodge-stay-' . Str::random(6),
            'price_etb' => 5000.00,
            'status' => 'published',
        ]);

        $stayProfile = StayListingProfile::query()->create([
            'listing_id' => $listing->id,
            'property_id' => $this->property->id,
            'host_id' => $this->host->id,
            'min_stay_nights' => 1,
            'max_stay_nights' => 30,
            'base_nightly_rate_etb' => 5000.00,
        ]);

        $unitType = \App\Domains\Stay\Models\AccommodationUnitType::query()->create([
            'stay_listing_profile_id' => $stayProfile->id,
            'property_id' => $this->property->id,
            'name_en' => 'Entire Chalet',
            'base_nightly_rate_etb' => 5000.00,
            'is_active' => true,
        ]);

        $unit = AccommodationUnit::query()->create([
            'unit_type_id' => $unitType->id,
            'property_id' => $this->property->id,
            'unit_number_or_code' => 'VILLA-1',
            'status' => 'ready',
        ]);

        // Create a Short-Stay Booking
        $this->booking = Booking::query()->create([
            'uuid' => (string) Str::uuid(),
            'booking_code' => 'BK-2026-0008888',
            'idempotency_key' => 'IDEMP-BK-8888',
            'listing_id' => $listing->id,
            'property_id' => $this->property->id,
            'stay_listing_profile_id' => $stayProfile->id,
            'accommodation_unit_id' => $unit->id,
            'host_id' => $this->host->id,
            'guest_user_id' => $this->customer->id,
            'booking_mode' => 'instant_book',
            'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-15',
            'nights_count' => 5,
            'accommodation_raw_subtotal_etb' => 25000.00,
            'discount_amount_etb' => 0.00,
            'accommodation_net_subtotal_etb' => 25000.00,
            'cleaning_fee_etb' => 1000.00,
            'platform_fee_etb' => 2500.00,
            'tax_amount_etb' => 3750.00,
            'security_deposit_etb' => 5000.00,
            'total_amount_etb' => 37250.00,
            'currency' => 'ETB',
            'status' => 'awaiting_payment',
        ]);

        // Create an Accepted Property Offer for Land / Property Purchase
        $this->offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-0009999',
            'listing_id' => $listing->id,
            'property_id' => $this->property->id,
            'buyer_user_id' => $this->customer->id,
            'seller_user_id' => $this->seller->id,
            'listed_asking_price_etb' => 15000000.00,
            'initial_offer_amount_etb' => 14000000.00,
            'latest_amount_etb' => 14500000.00,
            'status' => 'accepted',
            'expires_at' => now()->addDays(30),
            'accepted_at' => now(),
            'metadata_json' => [
                'total_paid_etb' => 0.00,
                'remaining_balance_etb' => 14500000.00,
            ],
        ]);
    }

    // =========================================================================
    // SECTION 13.1: ELEVEN PAYMENT TYPES VERIFICATION
    // =========================================================================

    public function test_eleven_payment_types_enum_and_property_purchase_support(): void
    {
        $cases = PaymentType::cases();
        $this->assertCount(11, $cases, 'Platform must support exactly eleven canonical payment types.');

        $expectedValues = [
            'booking',
            'reservation',
            'listing',
            'premium_listing',
            'subscription',
            'rent',
            'lease',
            'developer_installment',
            'service',
            'commission',
            'property_purchase',
        ];

        $actualValues = array_map(fn($case) => $case->value, $cases);
        $this->assertEquals($expectedValues, $actualValues);

        $this->assertEquals(PaymentType::PropertyPurchase, PaymentType::from('property_purchase'));
        $this->assertEquals('Property / Land Purchase', PaymentType::PropertyPurchase->label());
    }

    // =========================================================================
    // SECTION 13.2: PAYMENT WORKFLOW & IDEMPOTENCY TESTS
    // =========================================================================

    public function test_payment_initiation_calculates_server_amounts_and_creates_intent(): void
    {
        $initiateAction = app(InitiatePaymentAction::class);

        $result = $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-TEST-001',
        ]);

        $payment = $result['payment'];
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
        $this->assertEquals(37250.00, (float) $payment->total_amount_etb);
        $this->assertEquals('ETB', $payment->currency);
        $this->assertFalse($result['is_duplicate_replay']);
        $this->assertStringStartsWith('TXN-', $payment->transaction_id);
        $this->assertStringStartsWith('PAY-', $payment->reference_code);
    }

    public function test_payment_initiation_duplicate_replay_is_idempotent(): void
    {
        $initiateAction = app(InitiatePaymentAction::class);

        // First call
        $first = $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-REPLAY-99',
        ]);

        // Second call with identical payload & idempotency key
        $second = $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-REPLAY-99',
        ]);

        $this->assertTrue($second['is_duplicate_replay']);
        $this->assertEquals($first['payment']->id, $second['payment']->id);
        $this->assertEquals(1, Payment::query()->where('idempotency_key', 'IDEMP-REPLAY-99')->count());
    }

    public function test_payment_initiation_idempotency_conflict_is_rejected(): void
    {
        $initiateAction = app(InitiatePaymentAction::class);

        $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-CONFLICT-01',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Idempotency conflict');

        // Reuse same key with different payment method
        $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::CbeBirr,
            'idempotency_key' => 'IDEMP-CONFLICT-01',
        ]);
    }

    public function test_unauthorized_customer_cannot_initiate_payment_for_other_users_payable(): void
    {
        $unauthorizedUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sneaky Intruder',
            'phone' => '+251911999000',
            'status' => 'active',
        ]);

        $initiateAction = app(InitiatePaymentAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unauthorized payment attempt');

        $initiateAction->execute($unauthorizedUser, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::Telebirr,
        ]);
    }

    // =========================================================================
    // SECTION 13.3: PROPERTY & LAND PURCHASE PAYMENT INTEGRATION
    // =========================================================================

    public function test_property_purchase_supports_deposit_installments_and_tracks_balance(): void
    {
        $initiateAction = app(InitiatePaymentAction::class);
        $webhookAction = app(ProcessWebhookEventAction::class);

        // Stage 1: Initial Deposit Payment of 2,000,000 ETB against 14,500,000 ETB agreement
        $depositResult = $initiateAction->execute($this->customer, [
            'payable' => $this->offer,
            'payment_method' => PaymentMethod::BankTransfer,
            'custom_amount_etb' => 2000000.00,
            'payment_type' => PaymentType::PropertyPurchase,
            'idempotency_key' => 'IDEMP-PROP-DEP-01',
        ]);

        $payment = $depositResult['payment'];
        $this->assertEquals(PaymentType::PropertyPurchase, $payment->payment_type);
        $this->assertEquals(2000000.00, (float) $payment->total_amount_etb);

        // Stage 2: Simulate bank deposit verification or webhook
        Config::set('services.chapa.webhook_secret', 'test-secret');
        $rawPayload = [
            'event' => 'charge.complete',
            'event_id' => 'evt_chp_deposit_01',
            'reference' => $payment->reference_code,
            'amount' => 2000000.00,
            'currency' => 'ETB',
            'status' => 'success',
        ];
        $rawBody = (string) json_encode($rawPayload);
        $sig = hash_hmac('sha256', $rawBody, 'test-secret');

        $webhookAction->execute('chapa', [], $rawBody, $rawPayload, $sig);

        // Stage 3: Verify offer balance updated
        $this->offer->refresh();
        $this->assertEquals(2000000.00, (float) $this->offer->metadata_json['total_paid_etb']);
        $this->assertEquals(12500000.00, (float) $this->offer->metadata_json['remaining_balance_etb']);

        // Stage 4: Verify title deed was NOT automatically transferred
        $this->property->refresh();
        $this->assertEquals($this->seller->id, $this->property->owner_id, 'Payment completion must not automatically transfer title deed without notary registration.');
    }

    // =========================================================================
    // SECTION 13.4: AUTHENTICATED AND IDEMPOTENT WEBHOOKS
    // =========================================================================

    public function test_webhook_with_valid_signature_processes_and_confirms_payment(): void
    {
        Config::set('services.chapa.webhook_secret', 'secret-key-123');

        $initiateAction = app(InitiatePaymentAction::class);
        $initResult = $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::Card,
            'idempotency_key' => 'IDEMP-CARD-001',
        ]);
        $payment = $initResult['payment'];

        $webhookAction = app(ProcessWebhookEventAction::class);
        $rawPayload = [
            'event' => 'charge.complete',
            'event_id' => 'chp_evt_1001',
            'reference' => $payment->reference_code,
            'amount' => 37250.00,
            'currency' => 'ETB',
            'status' => 'success',
        ];
        $rawBody = (string) json_encode($rawPayload);
        $sig = hash_hmac('sha256', $rawBody, 'secret-key-123');

        $result = $webhookAction->execute('chapa', [], $rawBody, $rawPayload, $sig);

        $this->assertEquals('success', $result['status']);
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Successful, $payment->status);
        $this->assertNotNull($payment->confirmed_at);

        // Verify booking confirmed & host payout scheduled
        $this->booking->refresh();
        $this->assertEquals('confirmed', $this->booking->status instanceof \App\Domains\Booking\Enums\BookingStatus ? $this->booking->status->value : $this->booking->status);
        $this->assertDatabaseHas('host_payouts', ['booking_id' => $this->booking->id]);
    }

    public function test_webhook_with_invalid_signature_is_strictly_rejected(): void
    {
        Config::set('services.chapa.webhook_secret', 'correct-secret');

        $webhookAction = app(ProcessWebhookEventAction::class);
        $rawPayload = ['event_id' => 'fake_evt'];
        $rawBody = (string) json_encode($rawPayload);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $webhookAction->execute('chapa', [], $rawBody, $rawPayload, 'forged-signature');
    }

    public function test_duplicate_webhook_delivery_is_deduplicated_without_double_posting(): void
    {
        Config::set('services.telebirr.app_key', 'tb-key');

        $initiateAction = app(InitiatePaymentAction::class);
        $init = $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-TB-DUP',
        ]);
        $payment = $init['payment'];

        $webhookAction = app(ProcessWebhookEventAction::class);
        $rawPayload = [
            'tradeStatus' => 'COMPLETED',
            'tradeNo' => 'TB_TXN_7788',
            'outTradeNo' => $payment->reference_code,
            'totalAmount' => 37250.00,
        ];
        $rawBody = (string) json_encode($rawPayload);
        $sig = hash_hmac('sha256', $rawBody, 'tb-key');

        // First delivery
        $res1 = $webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);
        $this->assertEquals('success', $res1['status']);

        // Duplicate delivery
        $res2 = $webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);
        $this->assertEquals('duplicate_acknowledged', $res2['status']);

        // Exactly one journal posting group exists for this payment
        $postingCount = JournalPostingGroup::query()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->count();

        $this->assertEquals(1, $postingCount);
    }

    public function test_webhook_payload_sensitive_data_is_redacted(): void
    {
        $raw = [
            'order_id' => '123',
            'card_number' => '4111111111111234',
            'cvv' => '999',
            'pin' => '1234',
            'amount' => 500.00,
        ];

        $sanitized = WebhookEvent::sanitizePayload($raw);

        $this->assertEquals('[REDACTED]', $sanitized['cvv']);
        $this->assertEquals('[REDACTED]', $sanitized['pin']);
        $this->assertEquals('[REDACTED]', $sanitized['card_number']);
        $this->assertEquals(500.00, $sanitized['amount']);
    }

    // =========================================================================
    // SECTION 13.5: MANUAL BANK TRANSFER & SLIP REVIEW TESTS
    // =========================================================================

    public function test_manual_bank_transfer_workflow_requires_officer_verification(): void
    {
        $initiateAction = app(InitiatePaymentAction::class);
        $init = $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::BankTransfer,
            'idempotency_key' => 'IDEMP-SLIP-001',
        ]);
        $payment = $init['payment'];

        // Customer submits bank slip
        $submitSlipAction = app(SubmitBankTransferSlipAction::class);
        $dummySlip = UploadedFile::fake()->create('cbe_deposit.pdf', 500, 'application/pdf');

        $proof = $submitSlipAction->execute($payment, $this->customer, $dummySlip, [
            'transfer_reference' => 'CBE-FT-99887766',
            'bank_name' => 'Commercial Bank of Ethiopia',
        ]);

        $this->assertEquals(BankTransferVerificationStatus::Submitted, $proof->verification_status);
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Pending, $payment->status, 'Uploaded slip must not automatically mark payment successful.');

        // Finance Officer verifies the slip
        $reviewAction = app(ReviewBankTransferAction::class);
        $reviewAction->execute($proof, $this->officer, 'verify');

        $proof->refresh();
        $payment->refresh();
        $this->assertEquals(BankTransferVerificationStatus::Verified, $proof->verification_status);
        $this->assertEquals(PaymentStatus::Successful, $payment->status);
        $this->assertNotNull($payment->confirmed_at);
    }

    // =========================================================================
    // SECTION 13.6: DOUBLE-ENTRY APPEND-ONLY FINANCIAL LEDGER TESTS
    // =========================================================================

    public function test_double_entry_ledger_balancing_enforcement(): void
    {
        $postingService = app(DoubleEntryPostingService::class);

        // 1. Balanced posting succeeds
        $group = $postingService->post(
            $this->property,
            'manual_adjustment',
            'Balanced test posting',
            [
                ['account_code' => LedgerAccountCode::CashAndClearing, 'entry_type' => 'debit', 'amount_etb' => 1000.00],
                ['account_code' => LedgerAccountCode::PlatformServiceRevenue, 'entry_type' => 'credit', 'amount_etb' => 1000.00],
            ],
            $this->officer
        );

        $this->assertInstanceOf(JournalPostingGroup::class, $group);
        $this->assertTrue($group->is_balanced);
        $this->assertEquals(2, $group->entries()->count());

        // 2. Unbalanced posting is strictly rejected
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unbalanced journal posting rejected');

        $postingService->post(
            $this->property,
            'unbalanced_test',
            'Unbalanced test posting',
            [
                ['account_code' => LedgerAccountCode::CashAndClearing, 'entry_type' => 'debit', 'amount_etb' => 1000.00],
                ['account_code' => LedgerAccountCode::PlatformServiceRevenue, 'entry_type' => 'credit', 'amount_etb' => 950.00],
            ],
            $this->officer
        );
    }

    public function test_ledger_entries_are_strictly_append_only(): void
    {
        $postingService = app(DoubleEntryPostingService::class);
        $group = $postingService->post(
            $this->property,
            'immutability_test',
            'Testing append only',
            [
                ['account_code' => LedgerAccountCode::CashAndClearing, 'entry_type' => 'debit', 'amount_etb' => 500.00],
                ['account_code' => LedgerAccountCode::PlatformServiceRevenue, 'entry_type' => 'credit', 'amount_etb' => 500.00],
            ],
            $this->officer
        );

        $entry = $group->entries()->first();

        // Attempting to update throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Append-only financial violation');

        $entry->amount_etb = 999.00;
        $entry->save();
    }

    public function test_reversing_journal_entry_creates_inverse_postings(): void
    {
        $postingService = app(DoubleEntryPostingService::class);
        $original = $postingService->post(
            $this->property,
            'original_entry',
            'Original posting',
            [
                ['account_code' => LedgerAccountCode::CashAndClearing, 'entry_type' => 'debit', 'amount_etb' => 2000.00],
                ['account_code' => LedgerAccountCode::HostPayable, 'entry_type' => 'credit', 'amount_etb' => 2000.00],
            ],
            $this->officer
        );

        $reversal = $postingService->reverse($original, 'Correction of error', $this->officer);

        $this->assertNotNull($reversal);
        $this->assertEquals(2000.00, (float) $reversal->total_debit_amount_etb);
        $this->assertEquals(2000.00, (float) $reversal->total_credit_amount_etb);

        // Original now references reversal
        $original->refresh();
        $this->assertEquals($reversal->id, $original->reversed_posting_group_id);
    }

    // =========================================================================
    // SECTION 13.7: HOST PAYOUT WATERFALL & SCHEDULING TESTS
    // =========================================================================

    public function test_host_payout_waterfall_calculation_formula(): void
    {
        $calculator = app(HostPayoutCalculator::class);

        // Codebook test fixture:
        // Booking revenue: ETB 44,500
        // Platform deduction: ETB 1,335
        // Authorized adjustment: -ETB 500
        // Host payable: ETB 42,665
        $waterfall = $calculator->calculateWaterfall(44500.00, 1335.00, -500.00);

        $this->assertEquals('44500.00', $waterfall['gross_revenue_etb']);
        $this->assertEquals('1335.00', $waterfall['platform_deductions_etb']);
        $this->assertEquals('-500.00', $waterfall['adjustments_amount_etb']);
        $this->assertEquals('42665.00', $waterfall['net_payable_etb']);
    }

    public function test_host_payout_all_five_schedules_resolving(): void
    {
        $calculator = app(HostPayoutCalculator::class);
        $ref = CarbonImmutable::parse('2026-11-02 10:00:00', 'Africa/Addis_Ababa'); // Monday

        // 1. Immediately
        $imm = $calculator->resolveScheduledReleaseDate(PayoutSchedule::Immediately, $this->booking, $ref);
        $this->assertEquals($ref->toIso8601String(), $imm->toIso8601String());

        // 2. After Check-In (check_in is 2026-11-10)
        $aci = $calculator->resolveScheduledReleaseDate(PayoutSchedule::AfterCheckin, $this->booking, $ref);
        $this->assertEquals('2026-11-11', $aci->toDateString());

        // 3. After Check-Out (check_out is 2026-11-15)
        $aco = $calculator->resolveScheduledReleaseDate(PayoutSchedule::AfterCheckout, $this->booking, $ref);
        $this->assertEquals('2026-11-16', $aco->toDateString());

        // 4. Weekly (next Friday)
        $wk = $calculator->resolveScheduledReleaseDate(PayoutSchedule::Weekly, $this->booking, $ref);
        $this->assertEquals('2026-11-06', $wk->toDateString());

        // 5. Monthly (first of next month)
        $mo = $calculator->resolveScheduledReleaseDate(PayoutSchedule::Monthly, $this->booking, $ref);
        $this->assertEquals('2026-12-01', $mo->toDateString());
    }

    public function test_host_payout_hold_and_release_lifecycle(): void
    {
        $scheduleAction = app(ScheduleHostPayoutAction::class);
        $payout = $scheduleAction->execute($this->booking);

        $holdAction = app(HoldOrReleasePayoutAction::class);

        // Put on hold
        $held = $holdAction->hold($payout, 'Guest damage claim disputed', $this->officer);
        $this->assertEquals(PayoutStatus::OnHold, $held->status);
        $this->assertEquals('Guest damage claim disputed', $held->hold_reason);

        // Attempting to settle while on hold is blocked
        $processAction = app(ProcessHostPayoutAction::class);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payout is on hold');
        $processAction->execute($held, $this->officer);
    }

    public function test_host_payout_settlement_and_ledger_posting(): void
    {
        // 1. Setup verified payout destination
        $dest = HostPayoutDestination::query()->create([
            'uuid' => (string) Str::uuid(),
            'host_id' => $this->host->id,
            'user_id' => $this->hostUser->id,
            'channel' => 'bank_account',
            'institution_name' => 'Commercial Bank of Ethiopia',
            'account_holder_name' => 'Haile Hospitality PLC',
            'account_number_encrypted' => 'dummy',
            'account_number_masked' => '****1234',
            'is_verified' => true,
            'is_primary' => true,
            'status' => 'active',
        ]);
        $dest->setAccountNumber('1000998877665');
        $dest->save();

        $scheduleAction = app(ScheduleHostPayoutAction::class);
        $payout = $scheduleAction->execute($this->booking);
        $payout->payout_destination_id = $dest->id;
        $payout->save();

        // 2. Settle payout
        $processAction = app(ProcessHostPayoutAction::class);
        $settled = $processAction->execute($payout, $this->officer, 'CBE-PO-TR-10029');

        $this->assertEquals(PayoutStatus::Paid, $settled->status);
        $this->assertNotNull($settled->paid_at);
        $this->assertEquals('CBE-PO-TR-10029', $settled->provider_reference);

        // 3. Verify double entry posted (Dr: 2010 Host Payable, Cr: 1010 Cash & Clearing)
        $group = JournalPostingGroup::query()
            ->where('source_type', HostPayout::class)
            ->where('source_id', $settled->id)
            ->first();

        $this->assertNotNull($group);
        $this->assertTrue($group->is_balanced);
        $this->assertEquals((float) $settled->net_payable_etb, (float) $group->total_debit_amount_etb);
    }

    // =========================================================================
    // SECTION 13.8: FULL INTEGRATION WORKFLOWS A–E
    // =========================================================================

    public function test_workflow_a_short_stay_booking_to_verified_payout(): void
    {
        // Destination
        $dest = HostPayoutDestination::query()->create([
            'uuid' => (string) Str::uuid(),
            'host_id' => $this->host->id,
            'user_id' => $this->hostUser->id,
            'channel' => 'telebirr',
            'institution_name' => 'Telebirr',
            'account_holder_name' => 'Haile Gebrselassie',
            'account_number_encrypted' => 'enc',
            'account_number_masked' => '****0333',
            'is_verified' => true,
            'is_primary' => true,
            'status' => 'active',
        ]);
        $dest->setAccountNumber('0911000333');
        $dest->save();

        // 1. Customer initiates payment
        $initiateAction = app(InitiatePaymentAction::class);
        $res = $initiateAction->execute($this->customer, [
            'payable' => $this->booking,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'WF-A-KEY-01',
        ]);
        $payment = $res['payment'];

        // 2. Webhook confirms payment
        Config::set('services.telebirr.app_key', 'tb-secret');
        $webhookAction = app(ProcessWebhookEventAction::class);
        $rawPayload = [
            'tradeStatus' => 'SUCCESS',
            'tradeNo' => 'TB-WF-A-9988',
            'outTradeNo' => $payment->reference_code,
            'totalAmount' => 37250.00,
        ];
        $rawBody = (string) json_encode($rawPayload);
        $sig = hash_hmac('sha256', $rawBody, 'tb-secret');
        $webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);

        // 3. Payout is scheduled
        $payout = HostPayout::query()->where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($payout);
        $payout->payout_destination_id = $dest->id;
        $payout->save();

        // 4. Settle payout
        $processAction = app(ProcessHostPayoutAction::class);
        $settled = $processAction->execute($payout, $this->officer);
        $this->assertEquals(PayoutStatus::Paid, $settled->status);
    }
}
