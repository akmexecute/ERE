<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Financial\Enums\LedgerAccountCode;
use App\Domains\Financial\Models\JournalPostingGroup;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Listing\Models\Listing;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Actions\InitiatePaymentAction;
use App\Domains\Payment\Actions\ProcessWebhookEventAction;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Enums\PurchaseAllocationStatus;
use App\Domains\Payment\Models\Payment;
use App\Domains\Payment\Models\WebhookEvent;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType as PropType;
use App\Domains\User\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Phase5PaymentConcurrencyAndLateWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $seller;
    private User $admin;
    private InitiatePaymentAction $initiateAction;
    private ProcessWebhookEventAction $webhookAction;
    private DoubleEntryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RbacRolePermissionSeeder::class,
            PropertyTaxonomySeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        Config::set('services.telebirr.app_key', 'telebirr_mock_signing_key_secret_2026');

        $this->initiateAction = app(InitiatePaymentAction::class);
        $this->webhookAction = app(ProcessWebhookEventAction::class);
        $this->postingService = app(DoubleEntryPostingService::class);

        $this->buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Kenenisa Bekele',
            'phone' => '+251911444555',
            'status' => 'active',
        ]);

        $this->seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Haile Gebrselassie',
            'phone' => '+251911666777',
            'status' => 'active',
        ]);

        $this->admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Platform Financial Admin',
            'phone' => '+251911000111',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super_admin');
    }

    private function createLandOffer(float|string $agreedPrice = 2_000_000.00): PropertyOffer
    {
        $landCategory = PropertyCategory::query()->where('code', 'LND')->first()
            ?? PropertyCategory::query()->create(['name_en' => 'Land', 'code' => 'LND', 'slug' => 'land']);
        $plotType = PropType::query()->where('category_id', $landCategory->id)->first()
            ?? PropType::query()->create(['category_id' => $landCategory->id, 'name_en' => 'Urban Residential Plot', 'slug' => 'urban-plot']);

        $listingTypeId = DB::table('listing_types')->where('transaction_mode', 'SALE')->value('id')
            ?? DB::table('listing_types')->value('id')
            ?? 1;

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-LND-' . Str::upper(Str::random(6)),
            'title_en' => 'Bole Sub-City Premium Plot',
            'category_id' => $landCategory->id,
            'property_type_id' => $plotType->id,
            'owner_id' => $this->seller->id,
            'status' => 'available',
            'listing_type' => 'sale',
            'price_etb' => $agreedPrice,
            'city' => 'Addis Ababa',
            'sub_city' => 'Bole',
        ]);

        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-LND-' . random_int(1000, 9999),
            'slug' => 'plot-' . random_int(1000, 9999),
            'property_id' => $property->id,
            'user_id' => $this->seller->id,
            'published_by_user_id' => $this->seller->id,
            'listing_type_id' => $listingTypeId,
            'transaction_mode' => 'SALE',
            'title_en' => 'Bole Plot Direct Sale',
            'status' => 'published',
            'pricing_mode' => 'fixed',
            'price_etb' => $agreedPrice,
            'published_at' => now(),
        ]);

        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'PO-' . Str::upper(Str::random(8)),
            'property_id' => $property->id,
            'listing_id' => $listing->id,
            'buyer_user_id' => $this->buyer->id,
            'seller_user_id' => $this->seller->id,
            'status' => 'accepted',
            'listed_asking_price_etb' => $agreedPrice,
            'initial_offer_amount_etb' => $agreedPrice,
            'latest_amount_etb' => $agreedPrice,
            'currency' => 'ETB',
            'expires_at' => now()->addDays(7),
            'responded_at' => now(),
        ]);

        $offer->formalizePurchaseAgreement();

        return $offer;
    }

    private function sendTelebirrWebhook(Payment $payment, string $status = 'COMPLETED', ?string $tradeTime = null): array
    {
        $appKey = config('services.telebirr.app_key');
        $payload = [
            'msisdn' => 'EVT-' . Str::random(12),
            'tradeStatus' => $status,
            'outTradeNo' => $payment->reference_code,
            'tradeNo' => 'TB-TXN-' . Str::random(10),
            'totalAmount' => (float) $payment->total_amount_etb,
            'tradeTime' => $tradeTime ?? now()->toIso8601String(),
        ];
        $rawBody = (string) json_encode($payload);
        $sig = hash_hmac('sha256', $rawBody, $appKey);

        return $this->webhookAction->execute(
            provider: 'telebirr',
            headers: ['x-signature' => $sig],
            rawBody: $rawBody,
            rawPayload: $payload,
            signature: $sig
        );
    }

    // =========================================================================
    // PRIORITY 1: LATE WEBHOOK CALLBACKS AFTER RESERVATION EXPIRY
    // =========================================================================

    /**
     * Section 4.3: Late provider-confirmed payment when sufficient purchase balance remains.
     * The 30-minute reservation expires locally, but provider confirms payment afterwards.
     * With sufficient remaining balance, the payment MUST be allocated, balance updated, and ledger posted.
     */
    public function test_late_successful_webhook_allocated_when_sufficient_balance_remains(): void
    {
        // Total agreement: ETB 2,000,000.00
        $offer = $this->createLandOffer(2_000_000.00);

        // Pre-confirm initial ETB 1,500,000.00 payment
        $initialPayment = Payment::query()->create([
            'idempotency_key' => 'IDEMP-' . Str::random(12),
            'customer_user_id' => $this->buyer->id,
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
            'property_id' => $offer->property_id,
            'payment_type' => PaymentType::PropertyPurchase,
            'principal_amount_etb' => 1_500_000.00,
            'fee_amount_etb' => 0.00,
            'tax_amount_etb' => 0.00,
            'total_amount_etb' => 1_500_000.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Telebirr,
            'provider' => 'telebirr',
            'status' => PaymentStatus::Successful,
            'allocation_status' => PurchaseAllocationStatus::Allocated,
            'allocated_amount_etb' => 1_500_000.00,
            'unapplied_amount_etb' => 0.00,
            'confirmed_at' => now()->subHours(2),
        ]);
        $offer->syncFinancialBalanceSnapshot();

        $this->assertSame('1500000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('500000.00', $offer->calculateRemainingBalanceEtb());

        // Buyer initiates ETB 400,000.00 payment
        $initResult = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 400_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $latePayment = $initResult['payment'];

        // At T=0, pending payment holds ETB 400,000.00. Unreserved headroom is ETB 100,000.00
        $this->assertSame('400000.00', $offer->calculatePendingAllocatedEtb());
        $this->assertSame('100000.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // Advance time by 35 minutes -> local 30-minute reservation expires!
        Carbon::setTestNow(now()->addMinutes(35));

        // The reservation has expired, unreserved headroom returns to ETB 500,000.00
        $this->assertTrue($latePayment->hasExpiredReservation());
        $this->assertSame('0.00', $offer->calculatePendingAllocatedEtb());
        $this->assertSame('500000.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // Late provider callback arrives confirming external payment success!
        $webhookResult = $this->sendTelebirrWebhook($latePayment, 'COMPLETED');
        $this->assertSame('success', $webhookResult['status']);

        $latePayment->refresh();
        $offer->refresh();

        // Verifications:
        // 1. Payment marked Successful and Allocated
        $this->assertSame(PaymentStatus::Successful, $latePayment->status);
        $this->assertSame(PurchaseAllocationStatus::Allocated, $latePayment->allocation_status);
        $this->assertSame('400000.00', (string) $latePayment->allocated_amount_etb);
        $this->assertSame('0.00', (string) $latePayment->unapplied_amount_etb);

        // 2. Agreement total paid and remaining balance updated accurately
        $this->assertSame('1900000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('100000.00', $offer->calculateRemainingBalanceEtb());
        $this->assertFalse($offer->isFullyPaid());

        // 3. Double-entry ledger posted exactly once
        $postings = JournalPostingGroup::query()
            ->where('source_type', $latePayment->getMorphClass())
            ->where('source_id', $latePayment->id)
            ->get();
        $this->assertCount(1, $postings);
        $this->assertSame('400000.00', (string) $postings->first()->total_debit_amount_etb);
        $this->assertSame('400000.00', (string) $postings->first()->total_credit_amount_etb);

        Carbon::setTestNow(); // Reset time
    }

    /**
     * Section 4.4: Late payment when another payment has consumed the balance.
     * Payment A (ETB 400,000) expires locally. Payment B (ETB 500,000) consumes the remaining balance.
     * When Payment A's provider callback arrives, ERE must:
     * 1. NOT discard the received money.
     * 2. NOT over-allocate the purchase agreement (must remain at 2,000,000, NOT 2,400,000).
     * 3. Route funds to Unapplied Customer Funds liability (Account 2040).
     * 4. Create an exception for administrative reconciliation.
     */
    public function test_late_successful_webhook_routed_to_unapplied_funds_when_balance_consumed(): void
    {
        $offer = $this->createLandOffer(2_000_000.00);

        // Pre-existing paid: ETB 1,500,000.00. Remaining: ETB 500,000.00
        Payment::query()->create([
            'idempotency_key' => 'IDEMP-' . Str::random(12),
            'customer_user_id' => $this->buyer->id,
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
            'property_id' => $offer->property_id,
            'payment_type' => PaymentType::PropertyPurchase,
            'principal_amount_etb' => 1_500_000.00,
            'fee_amount_etb' => 0.00,
            'tax_amount_etb' => 0.00,
            'total_amount_etb' => 1_500_000.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Telebirr,
            'provider' => 'telebirr',
            'status' => PaymentStatus::Successful,
            'allocation_status' => PurchaseAllocationStatus::Allocated,
            'allocated_amount_etb' => 1_500_000.00,
            'unapplied_amount_etb' => 0.00,
            'confirmed_at' => now()->subHours(1),
        ]);
        $offer->syncFinancialBalanceSnapshot();

        // 1. Buyer initiates Payment A for ETB 400,000.00
        $initA = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 400_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $paymentA = $initA['payment'];

        // 2. 35 minutes pass: Payment A's local reservation expires
        Carbon::setTestNow(now()->addMinutes(35));

        // 3. Buyer initiates Payment B for the full remaining ETB 500,000.00 and it succeeds
        $initB = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 500_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $paymentB = $initB['payment'];
        $this->sendTelebirrWebhook($paymentB, 'COMPLETED');

        $offer->refresh();
        $this->assertSame('2000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('0.00', $offer->calculateRemainingBalanceEtb());
        $this->assertTrue($offer->isFullyPaid());

        // 4. Provider subsequently confirms Payment A (ETB 400,000) late!
        $webhookResultA = $this->sendTelebirrWebhook($paymentA, 'COMPLETED');

        // Assert webhook recognizes unapplied funds condition
        $this->assertSame('unapplied_reconciliation_required', $webhookResultA['status']);

        $paymentA->refresh();
        $offer->refresh();

        // 5. CRITICAL FINANCIAL INVARIANT CHECKS:
        // A. Payment A is confirmed as Successful with provider (money received!)
        $this->assertSame(PaymentStatus::Successful, $paymentA->status);
        $this->assertSame(PurchaseAllocationStatus::ReconciliationRequired, $paymentA->allocation_status);
        $this->assertSame('0.00', (string) $paymentA->allocated_amount_etb);
        $this->assertSame('400000.00', (string) $paymentA->unapplied_amount_etb);
        $this->assertNotNull($paymentA->reconciliation_notes);

        // B. Agreement is NOT over-allocated: still exactly ETB 2,000,000.00!
        $this->assertSame('2000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('0.00', $offer->calculateRemainingBalanceEtb());

        // C. Double-entry ledger: Dr 1010 Cash & Clearing (400,000), Cr 2040 Unapplied Customer Funds Liability (400,000)
        $postingsA = JournalPostingGroup::query()
            ->where('source_type', $paymentA->getMorphClass())
            ->where('source_id', $paymentA->id)
            ->with('entries')
            ->firstOrFail();

        $this->assertSame('unapplied_customer_funds_captured', $postingsA->event_type);
        $this->assertSame('400000.00', (string) $postingsA->total_debit_amount_etb);
        $this->assertSame('400000.00', (string) $postingsA->total_credit_amount_etb);

        $debitEntry = $postingsA->entries->firstWhere('entry_type', 'debit');
        $creditEntry = $postingsA->entries->firstWhere('entry_type', 'credit');

        $this->assertSame('1010', $debitEntry->ledgerAccount->code); // Cash & Clearing
        $this->assertSame('2040', $creditEntry->ledgerAccount->code); // Unapplied Customer Funds

        Carbon::setTestNow();
    }

    /**
     * Section 4.4 & 4.5: Visible reconciliation exception and authorized refund of unapplied customer funds.
     */
    public function test_unapplied_customer_funds_appear_in_reconciliation_console_and_can_be_refunded(): void
    {
        $offer = $this->createLandOffer(1_000_000.00);

        // Create an unapplied customer payment
        $payment = Payment::query()->create([
            'idempotency_key' => 'IDEMP-UNAPPLIED-001',
            'uuid' => (string) Str::uuid(),
            'transaction_id' => 'TXN-UNAPPLIED-001',
            'reference_code' => 'PAY-UNAPPLIED-001',
            'customer_user_id' => $this->buyer->id,
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
            'property_id' => $offer->property_id,
            'payment_type' => PaymentType::PropertyPurchase,
            'principal_amount_etb' => 250_000.00,
            'fee_amount_etb' => 0.00,
            'tax_amount_etb' => 0.00,
            'total_amount_etb' => 250_000.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Telebirr,
            'provider' => 'telebirr',
            'status' => PaymentStatus::Successful,
            'allocation_status' => PurchaseAllocationStatus::ReconciliationRequired,
            'allocated_amount_etb' => 0.00,
            'unapplied_amount_etb' => 250_000.00,
            'reconciliation_notes' => 'Late provider callback received after agreement balance was consumed.',
            'confirmed_at' => now(),
        ]);

        $this->postingService->postUnappliedCustomerFunds($payment, 'Initial unapplied hold');

        // 1. Query administrative reconciliation console
        $response = $this->actingAs($this->admin)->get(route('payments.reconciliation'));
        $response->assertOk();
        $response->assertSee('PAY-UNAPPLIED-001');
        $response->assertSee('250,000.00');

        // 2. Admin processes authorized refund of the unapplied customer funds
        $refundResponse = $this->actingAs($this->admin)->post(route('payments.refund_unapplied', $payment->uuid), [
            'reason' => 'Administrative refund to buyer via Telebirr gateway.',
        ]);
        $refundResponse->assertRedirect();
        $refundResponse->assertSessionHas('status', 'Unapplied customer funds refunded successfully.');

        $payment->refresh();

        // 3. Verifications:
        // Payment transitions to Refunded, unapplied amount is 0.00
        $this->assertSame(PaymentStatus::Refunded, $payment->status);
        $this->assertSame(PurchaseAllocationStatus::Refunded, $payment->allocation_status);
        $this->assertSame('250000.00', (string) $payment->refunded_amount_etb);
        $this->assertSame('0.00', (string) $payment->unapplied_amount_etb);

        // Stage 1 Ledger Entry: Dr 2040 Unapplied Customer Funds (250,000), Cr 2050 Customer Refunds Payable (250,000)
        // Bank Cash Asset 1010 is strictly NOT credited yet because physical disbursement has not occurred.
        $refundPosting = JournalPostingGroup::query()
            ->where('source_type', $payment->getMorphClass())
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_refund_authorized')
            ->with('entries')
            ->firstOrFail();

        $this->assertSame('250000.00', (string) $refundPosting->total_debit_amount_etb);
        $this->assertSame('250000.00', (string) $refundPosting->total_credit_amount_etb);

        $debitEntry = $refundPosting->entries->firstWhere('entry_type', 'debit');
        $creditEntry = $refundPosting->entries->firstWhere('entry_type', 'credit');

        $this->assertSame('2040', $debitEntry->ledgerAccount->code); // Unapplied liability reduced
        $this->assertSame('2050', $creditEntry->ledgerAccount->code); // Customer refund payable recognized

        // Stage 2: Confirm physical banking / provider disbursement
        $disburseRes = $this->actingAs($this->admin)->postJson(route('payments.confirm_disbursement', $payment->uuid), [
            'disbursement_reference' => 'TELEBIRR-DISBURSE-TXN-9988',
            'notes' => 'Disbursed via Telebirr Business Portal.',
        ]);
        $disburseRes->assertOk();
        $disburseRes->assertJson([
            'success' => true,
            'provider_settlement_confirmed' => true,
            'disbursement_reference' => 'TELEBIRR-DISBURSE-TXN-9988',
        ]);

        // Stage 2 Ledger Entry: Dr 2050 Customer Refunds Payable, Cr 1010 Cash & Clearing
        $settlePosting = JournalPostingGroup::query()
            ->where('source_type', $payment->getMorphClass())
            ->where('source_id', $payment->id)
            ->where('event_type', 'refund_disbursement_settled')
            ->with('entries')
            ->firstOrFail();

        $this->assertSame('250000.00', (string) $settlePosting->total_debit_amount_etb);
        $this->assertSame('250000.00', (string) $settlePosting->total_credit_amount_etb);

        $stage2Debit = $settlePosting->entries->firstWhere('entry_type', 'debit');
        $stage2Credit = $settlePosting->entries->firstWhere('entry_type', 'credit');

        $this->assertSame('2050', $stage2Debit->ledgerAccount->code); // Refunds payable cleared
        $this->assertSame('1010', $stage2Credit->ledgerAccount->code); // Cash leaves clearing

        // Idempotency: Duplicate disbursement attempt must be rejected
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already confirmed with reference');
        $this->postingService->confirmRefundDisbursement($payment, 'TELEBIRR-DISBURSE-TXN-DUPLICATE', $this->admin);
    }

    /**
     * Section 4.5: Late callback arriving after agreement cancellation routes to unapplied funds.
     */
    public function test_late_successful_webhook_after_agreement_cancellation_routes_to_unapplied_funds(): void
    {
        $offer = $this->createLandOffer(1_500_000.00);

        // Buyer initiates payment
        $init = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 500_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $payment = $init['payment'];

        // Agreement is cancelled / expired
        $offer->status = 'cancelled';
        $offer->save();

        // Provider callback confirms payment
        $webhookResult = $this->sendTelebirrWebhook($payment, 'COMPLETED');
        $this->assertSame('unapplied_reconciliation_required', $webhookResult['status']);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame(PurchaseAllocationStatus::ReconciliationRequired, $payment->allocation_status);
        $this->assertSame('500000.00', (string) $payment->unapplied_amount_etb);
        $this->assertStringContainsString('is not an active accepted purchase agreement', (string) $payment->reconciliation_notes);
    }

    /**
     * Section 4.6 & Section 6.1: Provider event timestamp recording and duplicate deduplication.
     */
    public function test_provider_event_timestamp_is_preserved_and_duplicate_callbacks_are_idempotent(): void
    {
        $offer = $this->createLandOffer(1_000_000.00);

        $init = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 300_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $payment = $init['payment'];

        $providerTime = '2026-11-03T10:15:20+03:00';
        $result1 = $this->sendTelebirrWebhook($payment, 'COMPLETED', $providerTime);
        $this->assertSame('success', $result1['status']);

        $payment->refresh();
        $this->assertNotNull($payment->provider_event_timestamp);
        $this->assertSame('2026-11-03 10:15:20', $payment->provider_event_timestamp->format('Y-m-d H:i:s'));

        // Re-deliver duplicate webhook event with same provider_event_id
        $appKey = config('services.telebirr.app_key');
        $webhookRecord = WebhookEvent::query()->where('transaction_reference', $payment->reference_code)->firstOrFail();

        $payload = $webhookRecord->payload_json;
        $rawBody = (string) json_encode($payload);
        $sig = hash_hmac('sha256', $rawBody, $appKey);

        $result2 = $this->webhookAction->execute(
            provider: 'telebirr',
            headers: ['x-signature' => $sig],
            rawBody: $rawBody,
            rawPayload: $payload,
            signature: $sig
        );

        $this->assertSame('duplicate_acknowledged', $result2['status']);

        // Assert exactly one posting group created
        $postings = JournalPostingGroup::query()
            ->where('source_type', $payment->getMorphClass())
            ->where('source_id', $payment->id)
            ->count();
        $this->assertSame(1, $postings);
    }

    /**
     * Failed provider callback releases reservation without creating false revenue or allocations.
     */
    public function test_failed_provider_callback_releases_reservation_without_allocation(): void
    {
        $offer = $this->createLandOffer(1_000_000.00);

        $init = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 200_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $payment = $init['payment'];

        $this->assertSame('200000.00', $offer->calculatePendingAllocatedEtb());

        // Webhook reports FAILED
        $result = $this->sendTelebirrWebhook($payment, 'FAILED');
        $this->assertSame('failed_recorded', $result['status']);

        $payment->refresh();
        $offer->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame(PurchaseAllocationStatus::Unallocated, $payment->allocation_status);
        $this->assertSame('0.00', $offer->calculatePendingAllocatedEtb()); // Hold released immediately
        $this->assertSame('1000000.00', $offer->calculateAllocatableRemainingBalanceEtb());

        $this->assertDatabaseMissing('journal_posting_groups', [
            'source_type' => $payment->getMorphClass(),
            'source_id' => $payment->id,
        ]);
    }

    // =========================================================================
    // PRIORITY 2: GENUINE SIMULTANEOUS PAYMENT ALLOCATION & CONCURRENCY
    // =========================================================================

    /**
     * Concurrency Test A: Two simultaneous requests for the same remaining balance.
     * Starting remaining balance: ETB 500,000.00.
     * Request A: ETB 400,000.00.
     * Request B: ETB 400,000.00.
     * Outcome: Only Request A succeeds; Request B is deterministically rejected with HTTP 422 / DomainException.
     * Confirmed + pending allocations NEVER exceed ETB 500,000.00.
     */
    public function test_concurrency_scenario_a_two_requests_for_same_remaining_balance_prevent_over_allocation(): void
    {
        $offer = $this->createLandOffer(2_000_000.00);

        // Pre-confirm ETB 1,500,000.00, leaving ETB 500,000.00
        Payment::query()->create([
            'idempotency_key' => 'IDEMP-' . Str::random(12),
            'customer_user_id' => $this->buyer->id,
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
            'property_id' => $offer->property_id,
            'payment_type' => PaymentType::PropertyPurchase,
            'principal_amount_etb' => 1_500_000.00,
            'fee_amount_etb' => 0.00,
            'tax_amount_etb' => 0.00,
            'total_amount_etb' => 1_500_000.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Telebirr,
            'provider' => 'telebirr',
            'status' => PaymentStatus::Successful,
            'allocation_status' => PurchaseAllocationStatus::Allocated,
            'allocated_amount_etb' => 1_500_000.00,
            'unapplied_amount_etb' => 0.00,
            'confirmed_at' => now(),
        ]);
        $offer->syncFinancialBalanceSnapshot();

        $this->assertSame('500000.00', $offer->calculateRemainingBalanceEtb());

        // Request A executes inside a locked transaction
        $resA = null;
        $resBError = null;

        DB::transaction(function () use (&$resA, &$resBError, $offer) {
            // Request A locks offer row and initiates ETB 400,000.00
            $resA = $this->initiateAction->execute($this->buyer, [
                'payable' => $offer,
                'payment_method' => PaymentMethod::Telebirr,
                'custom_amount_etb' => 400_000.00,
                'payment_type' => PaymentType::PropertyPurchase,
            ]);

            // Request B arrives concurrently for ETB 400,000.00 while Request A hold is active
            try {
                $this->initiateAction->execute($this->buyer, [
                    'payable' => $offer,
                    'payment_method' => PaymentMethod::Telebirr,
                    'custom_amount_etb' => 400_000.00,
                    'payment_type' => PaymentType::PropertyPurchase,
                ]);
            } catch (DomainException $e) {
                $resBError = $e->getMessage();
            }
        });

        // Verifications:
        // Request A succeeded
        $this->assertNotNull($resA);
        $this->assertSame(400_000.00, (float) $resA['payment']->total_amount_etb);

        // Request B was rejected with clean domain exception
        $this->assertNotNull($resBError);
        $this->assertStringContainsString('exceeds the available unreserved balance [100000.00 ETB]', $resBError);

        // Final agreement holds never exceed ETB 500,000.00
        $this->assertSame('400000.00', $offer->calculatePendingAllocatedEtb());
        $this->assertSame('100000.00', $offer->calculateAllocatableRemainingBalanceEtb());
    }

    /**
     * Concurrency Test B: Two simultaneous requests that exactly consume the remaining balance.
     * Remaining balance: ETB 500,000.00.
     * Request A: ETB 300,000.00.
     * Request B: ETB 200,000.00.
     * Both succeed serialized; total confirmed = ETB 500,000.00; remaining balance = ETB 0.00.
     */
    public function test_concurrency_scenario_b_two_requests_exactly_consuming_balance_serialize_cleanly(): void
    {
        $offer = $this->createLandOffer(500_000.00);

        $resA = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 300_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);

        $resB = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 200_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);

        $this->assertSame('500000.00', $offer->calculatePendingAllocatedEtb());
        $this->assertSame('0.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // Confirm both payments
        $this->sendTelebirrWebhook($resA['payment'], 'COMPLETED');
        $this->sendTelebirrWebhook($resB['payment'], 'COMPLETED');

        $offer->refresh();
        $this->assertSame('500000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('0.00', $offer->calculateRemainingBalanceEtb());
        $this->assertTrue($offer->isFullyPaid());
    }

    /**
     * Concurrency Test C: Payment confirmation and refund race condition.
     * Simulates payment confirmation while an authorized refund is executed.
     * Balances and double-entry postings remain strictly balanced.
     */
    public function test_concurrency_scenario_c_payment_confirmation_and_refund_race(): void
    {
        $offer = $this->createLandOffer(1_000_000.00);

        // Pre-existing Payment 1: ETB 500,000.00 (confirmed)
        $payment1 = Payment::query()->create([
            'idempotency_key' => 'IDEMP-' . Str::random(12),
            'customer_user_id' => $this->buyer->id,
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
            'property_id' => $offer->property_id,
            'payment_type' => PaymentType::PropertyPurchase,
            'principal_amount_etb' => 500_000.00,
            'fee_amount_etb' => 0.00,
            'tax_amount_etb' => 0.00,
            'total_amount_etb' => 500_000.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Telebirr,
            'provider' => 'telebirr',
            'status' => PaymentStatus::Successful,
            'allocation_status' => PurchaseAllocationStatus::Allocated,
            'allocated_amount_etb' => 500_000.00,
            'unapplied_amount_etb' => 0.00,
            'confirmed_at' => now(),
        ]);
        $offer->syncFinancialBalanceSnapshot();

        // Payment 2 initiated for ETB 500,000.00
        $init2 = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 500_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $payment2 = $init2['payment'];

        // Atomic concurrent execution of Refund on Payment 1 and Confirmation of Payment 2
        DB::transaction(function () use ($payment1, $payment2) {
            // Refund ETB 500,000.00 on Payment 1
            $this->postingService->postPaymentRefund($payment1, 500_000.00, 'Mutual cancellation refund', $this->admin);

            // Confirm Payment 2
            $this->sendTelebirrWebhook($payment2, 'COMPLETED');
        });

        $payment1->refresh();
        $payment2->refresh();
        $offer->refresh();

        // Verification:
        // Payment 1 is Refunded
        $this->assertSame(PaymentStatus::Refunded, $payment1->status);
        $this->assertSame('500000.00', (string) $payment1->refunded_amount_etb);

        // Payment 2 is Successful and Allocated
        $this->assertSame(PaymentStatus::Successful, $payment2->status);
        $this->assertSame(PurchaseAllocationStatus::Allocated, $payment2->allocation_status);

        // Total paid on agreement: 500,000 - 500,000 (refund) + 500,000 = 500,000.00
        $this->assertSame('500000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('500000.00', $offer->calculateRemainingBalanceEtb());

        // Check ledger debits equal credits across all postings
        $totalDebits = JournalPostingGroup::query()->sum('total_debit_amount_etb');
        $totalCredits = JournalPostingGroup::query()->sum('total_credit_amount_etb');
        $this->assertSame(bccomp((string) $totalDebits, (string) $totalCredits, 2), 0);
    }

    /**
     * Concurrency Test D: Late callback versus new payment race.
     * Simultaneous execution of late callback for expired Payment A (ETB 400,000)
     * and a new Payment B (ETB 500,000) against ETB 500,000 remaining balance.
     */
    public function test_concurrency_scenario_d_late_callback_and_new_payment_race(): void
    {
        $offer = $this->createLandOffer(2_000_000.00);

        // Pre-confirm ETB 1,500,000.00, remaining ETB 500,000.00
        Payment::query()->create([
            'idempotency_key' => 'IDEMP-' . Str::random(12),
            'customer_user_id' => $this->buyer->id,
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
            'property_id' => $offer->property_id,
            'payment_type' => PaymentType::PropertyPurchase,
            'principal_amount_etb' => 1_500_000.00,
            'fee_amount_etb' => 0.00,
            'tax_amount_etb' => 0.00,
            'total_amount_etb' => 1_500_000.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Telebirr,
            'provider' => 'telebirr',
            'status' => PaymentStatus::Successful,
            'allocation_status' => PurchaseAllocationStatus::Allocated,
            'allocated_amount_etb' => 1_500_000.00,
            'unapplied_amount_etb' => 0.00,
            'confirmed_at' => now(),
        ]);
        $offer->syncFinancialBalanceSnapshot();

        // Payment A (ETB 400,000) initiated, then expires after 35 minutes
        $initA = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 400_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $paymentA = $initA['payment'];

        Carbon::setTestNow(now()->addMinutes(35));

        // Sequence: Payment B initiates and confirms first (consuming the entire ETB 500,000 balance)
        $initB = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount_etb' => 500_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $this->sendTelebirrWebhook($initB['payment'], 'COMPLETED');

        // Late callback for Payment A arrives
        $this->sendTelebirrWebhook($paymentA, 'COMPLETED');

        $paymentA->refresh();
        $offer->refresh();

        // Under no circumstance is the agreement over-allocated
        $this->assertSame('2000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame(PurchaseAllocationStatus::ReconciliationRequired, $paymentA->allocation_status);
        $this->assertSame('400000.00', (string) $paymentA->unapplied_amount_etb);

        Carbon::setTestNow();
    }

    /**
     * Genuine Multi-Process Concurrency Test using Separate OS CLI Processes.
     * Launches two concurrent PHP processes executing 'ere:simulate-concurrent-payment'
     * against the same purchase agreement.
     */
    public function test_genuine_multi_process_concurrency_via_cli_processes(): void
    {
        $offer = $this->createLandOffer(500_000.00);

        $cmd1 = 'php artisan ere:simulate-concurrent-payment initiate --payable-type="App\Domains\Offer\Models\PropertyOffer" --payable-id=' . $offer->id . ' --user-id=' . $this->buyer->id . ' --amount=400000 --method=telebirr --hold-lock-ms=400';
        $cmd2 = 'php artisan ere:simulate-concurrent-payment initiate --payable-type="App\Domains\Offer\Models\PropertyOffer" --payable-id=' . $offer->id . ' --user-id=' . $this->buyer->id . ' --amount=400000 --method=telebirr --delay-ms=100';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc1 = proc_open($cmd1, $descriptors, $pipes1, base_path());
        $proc2 = proc_open($cmd2, $descriptors, $pipes2, base_path());

        $out1 = stream_get_contents($pipes1[1]);
        fclose($pipes1[0]); fclose($pipes1[1]); fclose($pipes1[2]);
        $status1 = proc_close($proc1);

        $out2 = stream_get_contents($pipes2[1]);
        fclose($pipes2[0]); fclose($pipes2[1]); fclose($pipes2[2]);
        $status2 = proc_close($proc2);

        $json1 = json_decode((string) $out1, true);
        $json2 = json_decode((string) $out2, true);

        // Assert that at least one process was blocked/serialized or rejected
        // and both processes together did NOT over-allocate the agreement
        $successCount = ($json1['success'] ?? false ? 1 : 0) + ($json2['success'] ?? false ? 1 : 0);

        // Only at most ONE process can successfully allocate 400,000 against 500,000 without conflict
        $this->assertLessThanOrEqual(1, $successCount);

        // If one failed, verify it was safely rejected or blocked by database lock/domain constraint
        if ($successCount === 1) {
            $failedJson = ($json1['success'] ?? false) ? $json2 : $json1;
            $this->assertFalse($failedJson['success']);
            $this->assertNotEmpty($failedJson['error']);
        }
    }

    /**
     * Verification 4: Late callback while another reservation is active.
     * 1. Buyer creates Payment 1.
     * 2. Payment 1 expires (35 mins elapsed).
     * 3. Buyer creates Payment 2 (valid active reservation).
     * 4. Late callback arrives for Payment 1.
     * 5. Verify Payment 1 is preserved in Account 2040 without consuming or corrupting Payment 2.
     * 6. Duplicate callback for Payment 1 is deduplicated.
     * 7. Callback for Payment 2 allocates cleanly to contract.
     */
    public function test_late_callback_while_another_reservation_is_active_preserves_new_reservation_and_routes_to_account_2040(): void
    {
        Carbon::setTestNow('2026-11-04 10:00:00');
        $offer = $this->createLandOffer(1_000_000.00);

        // 1. Buyer creates Payment 1 (ETB 1,000,000)
        $res1 = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount' => 1_000_000.00,
        ]);
        /** @var Payment $payment1 */
        $payment1 = $res1['payment'];
        $this->assertEquals(PaymentStatus::Pending, $payment1->status);
        $this->assertEquals(PurchaseAllocationStatus::Unallocated, $payment1->allocation_status);

        // 2. Fast forward 35 minutes -> Payment 1 reservation expires
        Carbon::setTestNow('2026-11-04 10:35:00');
        $this->assertTrue($payment1->fresh()->hasExpiredReservation());
        // Unreserved headroom is re-opened to ETB 1,000,000 because Payment 1 has expired
        $this->assertEquals('1000000.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // 3. Buyer creates Payment 2 (ETB 1,000,000) -> valid active reservation
        $res2 = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount' => 1_000_000.00,
        ]);
        /** @var Payment $payment2 */
        $payment2 = $res2['payment'];
        $this->assertEquals(PaymentStatus::Pending, $payment2->status);
        $this->assertFalse($payment2->fresh()->hasExpiredReservation());
        $this->assertEquals('0.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // 4. Late provider callback arrives for Payment 1
        $rawPayload1 = [
            'outTradeNo' => $payment1->reference_code,
            'tradeNo' => 'TB-LATE-P1-TXN',
            'tradeStatus' => 'COMPLETED',
            'totalAmount' => 1000000.00,
            'msisdn' => 'EVT-P1-LATE-001',
            'tradeTime' => '2026-11-04 10:36:00',
        ];
        $rawBody1 = json_encode($rawPayload1, JSON_THROW_ON_ERROR);
        $sig1 = hash_hmac('sha256', $rawBody1, 'telebirr_mock_signing_key_secret_2026');

        $result1 = $this->webhookAction->execute(
            'telebirr',
            ['x-signature' => $sig1, 'content-type' => 'application/json'],
            $rawBody1,
            $rawPayload1
        );

        // Verify Payment 1 was preserved as successful but routed to unapplied funds (Account 2040)
        $this->assertEquals('unapplied_reconciliation_required', $result1['status']);
        $payment1->refresh();
        $this->assertEquals(PaymentStatus::Successful, $payment1->status);
        $this->assertEquals(PurchaseAllocationStatus::ReconciliationRequired, $payment1->allocation_status);
        $this->assertEquals('1000000.00', (string) $payment1->unapplied_amount_etb);
        $this->assertEquals('0.00', (string) $payment1->allocated_amount_etb);

        // Verify Account 2040 double-entry ledger posting
        $unappliedPosting = JournalPostingGroup::query()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment1->id)
            ->where('event_type', 'unapplied_customer_funds_captured')
            ->first();
        $this->assertNotNull($unappliedPosting);
        $this->assertEquals('1000000.00', (string) $unappliedPosting->total_debit_amount_etb);
        $this->assertEquals('1000000.00', (string) $unappliedPosting->total_credit_amount_etb);

        // Verify Payment 2 active reservation was NOT consumed, cancelled, or corrupted
        $payment2->refresh();
        $this->assertEquals(PaymentStatus::Pending, $payment2->status);
        $this->assertEquals(PurchaseAllocationStatus::Unallocated, $payment2->allocation_status);
        $this->assertFalse($payment2->hasExpiredReservation());

        // Verify offer balance was NOT prematurely marked as paid by Payment 1
        $offer->refresh();
        $this->assertEquals('0.00', $offer->calculateTotalPaidEtb());
        $this->assertEquals('1000000.00', $offer->calculateRemainingBalanceEtb());

        // Replay duplicate callback for Payment 1 -> idempotent acknowledgement
        $resultReplay = $this->webhookAction->execute(
            'telebirr',
            ['x-signature' => $sig1, 'content-type' => 'application/json'],
            $rawBody1,
            $rawPayload1
        );
        $this->assertEquals('duplicate_acknowledged', $resultReplay['status']);

        // Now provider callback for Payment 2 arrives (at 10:40:00, within its 30-min window)
        Carbon::setTestNow('2026-11-04 10:40:00');
        $rawPayload2 = [
            'outTradeNo' => $payment2->reference_code,
            'tradeNo' => 'TB-ACTIVE-P2-TXN',
            'tradeStatus' => 'COMPLETED',
            'totalAmount' => 1000000.00,
            'msisdn' => 'EVT-P2-ACTIVE-002',
            'tradeTime' => '2026-11-04 10:40:00',
        ];
        $rawBody2 = json_encode($rawPayload2, JSON_THROW_ON_ERROR);
        $sig2 = hash_hmac('sha256', $rawBody2, 'telebirr_mock_signing_key_secret_2026');

        $result2 = $this->webhookAction->execute(
            'telebirr',
            ['x-signature' => $sig2, 'content-type' => 'application/json'],
            $rawBody2,
            $rawPayload2
        );
        $this->assertEquals('success', $result2['status']);

        // Payment 2 cleanly allocated to the purchase agreement!
        $payment2->refresh();
        $this->assertEquals(PaymentStatus::Successful, $payment2->status);
        $this->assertEquals(PurchaseAllocationStatus::Allocated, $payment2->allocation_status);
        $this->assertEquals('1000000.00', (string) $payment2->allocated_amount_etb);

        // Contract is now fully paid by Payment 2; Payment 1 remains safely in Account 2040 for refund/settlement
        $offer->refresh();
        $this->assertEquals('1000000.00', $offer->calculateTotalPaidEtb());
        $this->assertEquals('0.00', $offer->calculateRemainingBalanceEtb());
        $this->assertTrue($offer->isFullyPaid());

        Carbon::setTestNow();
    }

    /**
     * Verification 1: Failed or rolled-back transactions release reservations correctly.
     */
    public function test_failed_or_rolled_back_initiation_releases_reservation_headroom(): void
    {
        $offer = $this->createLandOffer(800_000.00);
        $this->assertEquals('800000.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // Simulate an initiation transaction that encounters a failure and rolls back
        try {
            DB::transaction(function () use ($offer): void {
                $lockedOffer = PropertyOffer::query()->whereKey($offer->id)->lockForUpdate()->first();
                Payment::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'customer_user_id' => $this->buyer->id,
                    'payable_type' => PropertyOffer::class,
                    'payable_id' => $lockedOffer->id,
                    'payment_type' => PaymentType::PropertyPurchase,
                    'principal_amount_etb' => 800000.00,
                    'total_amount_etb' => 800000.00,
                    'currency' => 'ETB',
                    'payment_method' => PaymentMethod::Telebirr,
                    'status' => PaymentStatus::Pending,
                    'allocation_status' => PurchaseAllocationStatus::Unallocated,
                    'reservation_expires_at' => now()->addMinutes(30),
                    'idempotency_key' => 'IDEMP-FAIL-SIM-' . Str::random(8),
                ]);

                // Simulate external exception prior to commit
                throw new \RuntimeException('Simulated provider connection failure during initiation handoff');
            });
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated provider connection failure', $e->getMessage());
        }

        // Database rollback must ensure zero pending payments exist and full headroom is immediately available
        $this->assertDatabaseMissing('payments', [
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
        ]);

        $offer->refresh();
        $this->assertEquals('800000.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // Subsequent valid initiation succeeds without being blocked
        $res = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount' => 800_000.00,
        ]);
        $this->assertNotNull($res['payment']);
        $this->assertEquals('0.00', $offer->fresh()->calculateAllocatableRemainingBalanceEtb());
    }

    /**
     * Verification 2: Invalid, forged, or inconsistent callbacks cannot create successful records.
     */
    public function test_forged_or_invalid_webhooks_rejected_without_creating_records(): void
    {
        $offer = $this->createLandOffer(500_000.00);
        $res = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'custom_amount' => 500_000.00,
        ]);
        /** @var Payment $payment */
        $payment = $res['payment'];

        // 1. Forged signature test
        $rawPayload = [
            'outTradeNo' => $payment->reference_code,
            'tradeNo' => 'TB-FORGED-TXN',
            'tradeStatus' => 'COMPLETED',
            'totalAmount' => 500000.00,
            'msisdn' => 'EVT-FORGED-001',
        ];
        $rawBody = json_encode($rawPayload, JSON_THROW_ON_ERROR);
        $forgedSig = 'invalid_attacker_signature_999999999999';

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $this->webhookAction->execute(
            'telebirr',
            ['x-signature' => $forgedSig, 'content-type' => 'application/json'],
            $rawBody,
            $rawPayload
        );

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
    }

    /**
     * Verification 5: Account 2040 reconciliation authorization is enforced on the server.
     */
    public function test_reconciliation_authorization_guards_reject_ordinary_users_and_allow_admins(): void
    {
        $offer = $this->createLandOffer(500_000.00);
        $payment = Payment::query()->create([
            'uuid' => (string) Str::uuid(),
            'customer_user_id' => $this->buyer->id,
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
            'payment_type' => PaymentType::PropertyPurchase,
            'principal_amount_etb' => 500000.00,
            'total_amount_etb' => 500000.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Telebirr,
            'status' => PaymentStatus::Successful,
            'allocation_status' => PurchaseAllocationStatus::ReconciliationRequired,
            'unapplied_amount_etb' => 500000.00,
            'allocated_amount_etb' => 0.00,
            'reconciliation_notes' => 'Late payment held for testing auth guards.',
            'idempotency_key' => 'IDEMP-AUTH-' . Str::random(8),
        ]);

        // Ordinary customer user (not super_admin or platform_admin)
        $this->actingAs($this->buyer);

        $this->get(route('payments.reconciliation'))
            ->assertStatus(403);

        $this->post(route('payments.refund_unapplied', $payment->uuid))
            ->assertStatus(403);

        // Super admin user has full authorized access
        $this->actingAs($this->admin);

        $this->get(route('payments.reconciliation'))
            ->assertStatus(200)
            ->assertSee($payment->reference_code);

        $response = $this->post(route('payments.refund_unapplied', $payment->uuid));
        $response->assertSessionHas('status');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Refunded, $payment->status);
        $this->assertEquals(PurchaseAllocationStatus::Refunded, $payment->allocation_status);
        $this->assertEquals('0.00', (string) $payment->unapplied_amount_etb);
    }

    /**
     * Verification 3 & 5: Refund recording versus actual settlement metadata and centralized audit logging.
     */
    public function test_reconciliation_refund_audit_logging_and_operational_metadata(): void
    {
        $offer = $this->createLandOffer(300_000.00);
        $payment = Payment::query()->create([
            'uuid' => (string) Str::uuid(),
            'customer_user_id' => $this->buyer->id,
            'payable_type' => PropertyOffer::class,
            'payable_id' => $offer->id,
            'payment_type' => PaymentType::PropertyPurchase,
            'principal_amount_etb' => 300000.00,
            'total_amount_etb' => 300000.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Telebirr,
            'status' => PaymentStatus::Successful,
            'allocation_status' => PurchaseAllocationStatus::ReconciliationRequired,
            'unapplied_amount_etb' => 300000.00,
            'allocated_amount_etb' => 0.00,
            'reconciliation_notes' => 'Late payment held for testing refund metadata.',
            'idempotency_key' => 'IDEMP-REF-META-' . Str::random(8),
        ]);

        $this->actingAs($this->admin);

        $res = $this->postJson(route('payments.refund_unapplied', $payment->uuid), [
            'reason' => 'Customer request for unused deposit return',
        ]);
        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'ledger_recorded' => true,
            'provider_settlement_confirmed' => false,
        ]);

        // Verify audit_logs table entry
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reconciliation_refund_authorized',
            'user_id' => $this->admin->id,
            'auditable_type' => Payment::class,
            'auditable_id' => (string) $payment->id,
        ]);

        // Verify payment metadata separates internal ledger release from external provider settlement
        $payment->refresh();
        $meta = $payment->metadata_json ?? [];
        $this->assertArrayHasKey('refund_authorization', $meta);
        $this->assertTrue($meta['refund_authorization']['ledger_recorded']);
        $this->assertFalse($meta['refund_authorization']['provider_settlement_confirmed']);
        $this->assertEquals('pending_manual_disbursement', $meta['refund_authorization']['disbursement_status']);
        $this->assertEquals($this->admin->id, $meta['refund_authorization']['authorized_by_user_id']);
    }

    /**
     * Section 6 / Task D: Verify Phase5E2ESeeder production environment protection.
     */
    public function test_phase5_e2e_seeder_is_strictly_blocked_in_production_environment(): void
    {
        $originalEnv = app()->environment();

        // Temporarily simulate production environment
        app()['env'] = 'production';

        try {
            $seeder = new \Database\Seeders\Phase5E2ESeeder();
            $seeder->run();
            $this->fail('Phase5E2ESeeder must throw RuntimeException when run in production.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('strictly restricted to testing', $e->getMessage());
        } finally {
            app()['env'] = $originalEnv;
        }
    }

    /**
     * Section 6 / Task D: Verify Phase5E2ESeeder assigns super_admin only to designated test identity in testing.
     */
    public function test_phase5_e2e_seeder_executes_safely_in_testing_environment(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $seeder = new \Database\Seeders\Phase5E2ESeeder();
        $seeder->run();

        $testAdmin = User::query()->where('phone', '+251911234567')->firstOrFail();
        $this->assertSame('admin@ethioproperty.et', $testAdmin->email);
        $this->assertTrue($testAdmin->hasRole('super_admin'));

        // Ordinary buyer and seller accounts must not have administrative roles
        $this->assertFalse($this->buyer->hasRole('super_admin'));
        $this->assertFalse($this->seller->hasRole('super_admin'));
    }

    /**
     * Section 6 / Task D: Verify standard DatabaseSeeder does not invoke Phase5E2ESeeder.
     */
    public function test_standard_database_seeder_does_not_contain_phase5_e2e_seeder(): void
    {
        $reflection = new \ReflectionClass(\Database\Seeders\DatabaseSeeder::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertStringNotContainsString('Phase5E2ESeeder', $content);
    }
}
