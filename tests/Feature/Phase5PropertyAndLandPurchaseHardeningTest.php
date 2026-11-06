<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Developer\Enums\ProjectStatus;
use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\SalesContractStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\BuildingFloor;
use App\Domains\Developer\Models\ContractInstallment;
use App\Domains\Developer\Models\DeveloperPaymentPlan;
use App\Domains\Developer\Models\DeveloperProfile;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\Developer\Models\DevelopmentProject;
use App\Domains\Developer\Models\PaymentPlanInstallment;
use App\Domains\Developer\Models\ProjectBuilding;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Developer\Models\UnitReservation;
use App\Domains\Financial\Enums\LedgerAccountCode;
use App\Domains\Financial\Models\JournalPostingGroup;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Listing\Models\Listing;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Actions\InitiatePaymentAction;
use App\Domains\Payment\Actions\ProcessWebhookEventAction;
use App\Domains\Payment\Actions\ReviewBankTransferAction;
use App\Domains\Payment\Actions\SubmitBankTransferSlipAction;
use App\Domains\Payment\Enums\BankTransferVerificationStatus;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Models\Payment;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType as PropType;
use App\Domains\User\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Phase5PropertyAndLandPurchaseHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $seller;
    private User $admin;
    private InitiatePaymentAction $initiateAction;
    private ProcessWebhookEventAction $webhookAction;
    private ReviewBankTransferAction $reviewBankAction;
    private SubmitBankTransferSlipAction $submitSlipAction;
    private DoubleEntryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Buyer Abebe Bikila',
            'phone' => '+251911000111',
            'status' => 'active',
        ]);
        $this->buyer->assignRoleBySlug('buyer');

        $this->seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Tirunesh Dibaba',
            'phone' => '+251911000222',
            'status' => 'active',
        ]);
        $this->seller->assignRoleBySlug('seller');

        $this->admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Officer Aster',
            'phone' => '+251911000333',
            'status' => 'active',
        ]);
        $this->admin->assignRoleBySlug('super_admin');

        $this->initiateAction = app(InitiatePaymentAction::class);
        $this->webhookAction = app(ProcessWebhookEventAction::class);
        $this->reviewBankAction = app(ReviewBankTransferAction::class);
        $this->submitSlipAction = app(SubmitBankTransferSlipAction::class);
        $this->postingService = app(DoubleEntryPostingService::class);
    }

    private function createLandPropertyAndOffer(float $price = 5_000_000.00): array
    {
        $landCategory = PropertyCategory::query()->where('code', 'LND')->first()
            ?? PropertyCategory::query()->create(['name_en' => 'Land', 'code' => 'LND', 'slug' => 'land']);

        $landType = PropType::query()->where('category_id', $landCategory->id)->first()
            ?? PropType::query()->create(['category_id' => $landCategory->id, 'name_en' => 'Urban Residential Plot', 'slug' => 'urban-plot']);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-LAND-' . random_int(1000, 9999),
            'owner_id' => $this->seller->id,
            'category_id' => $landCategory->id,
            'property_type_id' => $landType->id,
            'title_en' => 'Prime 500sqm Urban Plot in Bole Bulbula',
            'status' => 'available',
            'verification_level' => 3,
        ]);

        $listingTypeId = DB::table('listing_types')->where('transaction_mode', 'SALE')->value('id')
            ?? DB::table('listing_types')->value('id')
            ?? 1;

        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-LAND-' . random_int(1000, 9999),
            'slug' => 'land-plot-' . random_int(1000, 9999),
            'property_id' => $property->id,
            'user_id' => $this->seller->id,
            'published_by_user_id' => $this->seller->id,
            'listing_type_id' => $listingTypeId,
            'transaction_mode' => 'SALE',
            'title_en' => 'Prime 500sqm Urban Plot in Bole Bulbula',
            'price_etb' => $price,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-LAND-' . random_int(1000, 9999),
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'buyer_user_id' => $this->buyer->id,
            'seller_user_id' => $this->seller->id,
            'listed_asking_price_etb' => $price,
            'initial_offer_amount_etb' => $price,
            'latest_amount_etb' => $price,
            'status' => 'accepted',
            'expires_at' => now()->addDays(30),
            'accepted_at' => now(),
        ]);

        $offer->syncFinancialBalanceSnapshot();

        return [$property, $listing, $offer];
    }

    public function test_ordinary_land_purchase_complete_lifecycle_with_deposit_installments_and_title_invariance(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(5_000_000.00);

        // Pre-payment baseline assertions
        $this->assertSame('0.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('5000000.00', $offer->calculateRemainingBalanceEtb());
        $this->assertFalse($offer->isFullyPaid());
        $this->assertSame($this->seller->id, $property->owner_id);

        // 1. Initial Deposit Payment: 1,000,000 ETB via Telebirr
        $result1 = $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $offer,
                'payment_method' => PaymentMethod::Telebirr,
                'idempotency_key' => 'IDEMP-LAND-DEP-001',
                'custom_amount_etb' => 1_000_000.00,
                'payment_type' => PaymentType::PropertyPurchase,
            ]
        );

        $payment1 = $result1['payment'];
        $this->assertSame(1_000_000.00, (float) $payment1->total_amount_etb);
        $this->assertSame(PaymentStatus::Pending, $payment1->status);

        // Simulate Telebirr webhook callback confirmation
        Config::set('services.telebirr.app_key', 'test_key');
        $rawPayload1 = [
            'outTradeNo' => $payment1->reference_code,
            'tradeNo' => 'TEL-TRD-DEP-001',
            'totalAmount' => 1000000.00,
            'tradeStatus' => 'COMPLETED',
        ];
        $rawBody1 = (string) json_encode($rawPayload1);
        $sig1 = hash_hmac('sha256', $rawBody1, 'test_key');

        $this->webhookAction->execute('telebirr', [], $rawBody1, $rawPayload1, $sig1);

        $payment1->refresh();
        $offer->refresh();

        $this->assertSame(PaymentStatus::Successful, $payment1->status);
        $this->assertSame('1000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('4000000.00', $offer->calculateRemainingBalanceEtb());
        $this->assertFalse($offer->isFullyPaid());

        // Verify Double-Entry Ledger: Dr 1010 Cash, Cr 2030 Escrow
        $postingGroup1 = JournalPostingGroup::query()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment1->id)
            ->where('event_type', 'property_purchase_captured')
            ->first();
        $this->assertNotNull($postingGroup1);
        $this->assertTrue($postingGroup1->is_balanced);
        $this->assertDatabaseHas('ledger_entries', [
            'posting_group_id' => $postingGroup1->id,
            'entry_type' => 'debit',
            'amount_etb' => '1000000.00',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'posting_group_id' => $postingGroup1->id,
            'entry_type' => 'credit',
            'amount_etb' => '1000000.00',
        ]);

        // 2. Second Installment Payment: 2,000,000 ETB via CBE Birr
        $result2 = $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $offer,
                'payment_method' => PaymentMethod::CbeBirr,
                'idempotency_key' => 'IDEMP-LAND-INST-002',
                'custom_amount_etb' => 2_000_000.00,
                'payment_type' => PaymentType::PropertyPurchase,
            ]
        );

        $payment2 = $result2['payment'];
        Config::set('services.cbe_birr.app_secret', 'test_key');
        $rawPayload2 = [
            'order_id' => $payment2->reference_code,
            'cbe_txn_id' => 'CBE-TXN-INST-002',
            'amount' => 2000000.00,
            'status' => 'SUCCESS',
        ];
        $rawBody2 = (string) json_encode($rawPayload2);
        $sig2 = hash_hmac('sha256', $rawBody2, 'test_key');

        $this->webhookAction->execute('cbe_birr', [], $rawBody2, $rawPayload2, $sig2);

        $offer->refresh();
        $this->assertSame('3000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('2000000.00', $offer->calculateRemainingBalanceEtb());
        $this->assertFalse($offer->isFullyPaid());

        // 3. Final Settlement: 2,000,000 ETB via Bank Transfer with Deposit Slip Review
        $result3 = $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $offer,
                'payment_method' => PaymentMethod::BankTransfer,
                'idempotency_key' => 'IDEMP-LAND-FINAL-003',
                'custom_amount_etb' => 2_000_000.00,
                'payment_type' => PaymentType::PropertyPurchase,
            ]
        );

        $payment3 = $result3['payment'];
        $slipFile = UploadedFile::fake()->create('bank_slip_final.pdf', 500, 'application/pdf');

        $proof = $this->submitSlipAction->execute(
            payment: $payment3,
            customer: $this->buyer,
            slipFile: $slipFile,
            details: [
                'account_holder_name' => 'Abebe Bikila',
                'transfer_reference' => 'CBE-DEPOSIT-999888',
                'bank_name' => 'Commercial Bank of Ethiopia',
                'deposited_amount_etb' => 2_000_000.00,
            ]
        );

        $this->reviewBankAction->execute(
            proof: $proof,
            officer: $this->admin,
            decision: 'verify',
            reason: 'Verified against Commercial Bank of Ethiopia bank statement.'
        );

        $offer->refresh();
        $property->refresh();

        // Financial settlement assertions
        $this->assertSame('5000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('0.00', $offer->calculateRemainingBalanceEtb());
        $this->assertTrue($offer->isFullyPaid());

        // CRITICAL INVARIANT: Title deed ownership remains untouched
        $this->assertSame($this->seller->id, $property->owner_id);
        $this->assertNotSame($this->buyer->id, $property->owner_id);
    }

    public function test_pre_payment_eligibility_guards_reject_invalid_offers(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(3_000_000.00);

        // 1. Unaccepted offer (status: pending)
        $offer->update(['status' => 'pending']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("is in 'pending' state and is not an accepted purchase agreement");

        $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $offer,
                'payment_method' => PaymentMethod::Telebirr,
                'idempotency_key' => 'IDEMP-ERR-001',
                'custom_amount_etb' => 500_000.00,
            ]
        );
    }

    public function test_pre_payment_eligibility_guards_reject_expired_offers(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(3_000_000.00);

        // Expired accepted offer
        $offer->update(['expires_at' => now()->subMinutes(10)]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Accepted purchase offer");
        $this->expectExceptionMessage("has expired");

        $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $offer,
                'payment_method' => PaymentMethod::Telebirr,
                'idempotency_key' => 'IDEMP-ERR-002',
                'custom_amount_etb' => 500_000.00,
            ]
        );
    }

    public function test_overpayment_prevention_rejects_amounts_exceeding_remaining_balance(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(2_000_000.00);

        // Attempt to pay 2,000,001.00 on a 2,000,000.00 balance
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("exceeds the remaining contractual balance");

        $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $offer,
                'payment_method' => PaymentMethod::Telebirr,
                'idempotency_key' => 'IDEMP-OVERPAY-001',
                'custom_amount_etb' => 2_000_001.00,
            ]
        );
    }

    public function test_double_entry_refund_and_reversal_resynchronizes_authoritative_balances(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(10_000_000.00);

        // 1. Pay initial deposit of 3,000,000 ETB
        $result = $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $offer,
                'payment_method' => PaymentMethod::Telebirr,
                'idempotency_key' => 'IDEMP-REFUND-TEST-001',
                'custom_amount_etb' => 3_000_000.00,
            ]
        );
        $payment = $result['payment'];

        Config::set('services.telebirr.app_key', 'test_key');
        $rawPayload = [
            'outTradeNo' => $payment->reference_code,
            'tradeNo' => 'TEL-TRD-REF-001',
            'totalAmount' => 3000000.00,
            'tradeStatus' => 'COMPLETED',
        ];
        $rawBody = (string) json_encode($rawPayload);
        $sig = hash_hmac('sha256', $rawBody, 'test_key');
        $this->webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);

        $payment->refresh();
        $offer->refresh();
        $this->assertSame('3000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('7000000.00', $offer->calculateRemainingBalanceEtb());

        // 2. Process partial refund of 1,000,000 ETB
        $refundPosting = $this->postingService->postPaymentRefund(
            payment: $payment,
            refundAmount: 1_000_000.00,
            reason: 'Buyer and Seller mutual cancellation of boundary extension.',
            actor: $this->admin
        );

        $this->assertNotNull($refundPosting);
        $this->assertTrue($refundPosting->is_balanced);
        $payment->refresh();
        $offer->refresh();

        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->status);
        $this->assertSame(1_000_000.00, (float) $payment->refunded_amount_etb);
        // Total paid reduces to 2,000,000; remaining balance increases to 8,000,000
        $this->assertSame('2000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('8000000.00', $offer->calculateRemainingBalanceEtb());

        // 3. Process complete reversal of the remaining payment
        $reversalPosting = $this->postingService->postPaymentReversal(
            payment: $payment,
            reason: 'Full dispute cancellation per SRS Section 189.',
            actor: $this->admin
        );

        $this->assertNotNull($reversalPosting);
        $payment->refresh();
        $offer->refresh();

        $this->assertSame(PaymentStatus::Reversed, $payment->status);
        // Total paid returns to 0.00; remaining balance returns to full agreed price
        $this->assertSame('0.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('10000000.00', $offer->calculateRemainingBalanceEtb());
    }

    public function test_developer_sales_contract_and_milestone_installments_workflow(): void
    {
        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->seller->id,
            'legal_name' => 'Ayat Real Estate SC',
            'company_type' => 'developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $developer = DeveloperProfile::query()->create([
            'company_id' => $companyId,
            'user_id' => $this->seller->id,
            'developer_code' => 'DEV-ETH-AYAT-01',
            'verification_status' => 'verified',
        ]);

        $project = DevelopmentProject::query()->create([
            'developer_id' => $developer->id,
            'company_id' => $companyId,
            'project_code' => 'PRJ-AYAT-01',
            'slug' => 'ayat-hilltop',
            'title_en' => 'Ayat Hilltop Heights',
            'project_type' => 'residential_complex',
            'status' => ProjectStatus::UnderConstruction,
        ]);

        $building = ProjectBuilding::query()->create([
            'project_id' => $project->id,
            'building_code' => 'AYAT-BLD-A',
            'name_en' => 'Block A',
        ]);

        $floor = BuildingFloor::query()->create([
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_number' => 3,
            'floor_label' => 'Level 3',
        ]);

        $unit = ProjectUnit::query()->create([
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'unit_number' => 'A-304',
            'floor_number' => 3,
            'unit_type' => '3br',
            'area_sqm' => 145.00,
            'price_etb' => 12_000_000.00,
            'status' => UnitInventoryStatus::Contracted,
        ]);

        $reservation = UnitReservation::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_code' => 'RES-2026-DEV-001',
            'idempotency_key' => 'IDEMP-RES-TEST-001',
            'project_unit_id' => $unit->id,
            'project_id' => $project->id,
            'buyer_user_id' => $this->buyer->id,
            'agreed_price_etb' => 12_000_000.00,
            'deposit_amount_etb' => 0.00,
            'status' => ReservationStatus::Reserved,
            'hold_expires_at' => now()->addDays(7),
        ]);

        // Create Developer Sales Contract: 12,000,000 ETB
        $contract = DeveloperSalesContract::query()->create([
            'uuid' => (string) Str::uuid(),
            'contract_code' => 'CNT-2026-DEV-001',
            'reservation_id' => $reservation->id,
            'project_unit_id' => $unit->id,
            'project_id' => $project->id,
            'buyer_user_id' => $this->buyer->id,
            'developer_company_id' => $companyId,
            'contract_price_etb' => 12_000_000.00,
            'deposit_paid_etb' => 0.00,
            'balance_payable_etb' => 12_000_000.00,
            'status' => SalesContractStatus::Active,
            'payment_plan_snapshot_json' => [
                'plan_name' => 'Standard Milestone Plan (30/70)',
                'installments' => [
                    ['sequence_number' => 1, 'percentage' => 30.0],
                    ['sequence_number' => 2, 'percentage' => 70.0],
                ],
            ],
        ]);

        // Milestone 1: 30% Foundation = 3,600,000 ETB
        $milestone1 = ContractInstallment::query()->create([
            'contract_id' => $contract->id,
            'sequence_number' => 1,
            'milestone_label_en' => 'Foundation Stage',
            'percentage' => 30.00,
            'amount_etb' => 3_600_000.00,
            'paid_amount_etb' => 0.00,
            'outstanding_balance_etb' => 3_600_000.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        // Milestone 2: 70% Structure & Handover = 8,400,000 ETB
        $milestone2 = ContractInstallment::query()->create([
            'contract_id' => $contract->id,
            'sequence_number' => 2,
            'milestone_label_en' => 'Structure & Handover Stage',
            'percentage' => 70.00,
            'amount_etb' => 8_400_000.00,
            'paid_amount_etb' => 0.00,
            'outstanding_balance_etb' => 8_400_000.00,
            'status' => 'pending',
            'due_date' => now()->addDays(90),
        ]);

        $contract->syncFinancialBalanceSnapshot();

        $this->assertSame('0.00', $contract->calculateTotalPaidEtb());
        $this->assertSame('12000000.00', $contract->calculateRemainingBalanceEtb());
        $this->assertFalse($contract->isFullyPaid());

        // 1. Pay Milestone 1 via Telebirr
        $resultInst1 = $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $milestone1,
                'payment_method' => PaymentMethod::Telebirr,
                'idempotency_key' => 'IDEMP-DEV-INST1',
                'payment_type' => PaymentType::DeveloperInstallment,
            ]
        );

        $payInst1 = $resultInst1['payment'];
        $this->assertSame(3_600_000.00, (float) $payInst1->total_amount_etb);

        Config::set('services.telebirr.app_key', 'test_key');
        $rawPayload = [
            'outTradeNo' => $payInst1->reference_code,
            'tradeNo' => 'TEL-TRD-DEV-001',
            'totalAmount' => 3600000.00,
            'tradeStatus' => 'COMPLETED',
        ];
        $rawBody = (string) json_encode($rawPayload);
        $sig = hash_hmac('sha256', $rawBody, 'test_key');
        $this->webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);

        $milestone1->refresh();
        $contract->refresh();

        $this->assertSame('paid', $milestone1->status);
        $this->assertSame(3_600_000.00, (float) $milestone1->paid_amount_etb);
        $this->assertSame(0.00, (float) $milestone1->outstanding_balance_etb);
        $this->assertTrue($milestone1->isFullyPaid());

        $this->assertSame('3600000.00', $contract->calculateTotalPaidEtb());
        $this->assertSame('8400000.00', $contract->calculateRemainingBalanceEtb());
        $this->assertFalse($contract->isFullyPaid());

        // 2. Pay Milestone 2 via CBE Birr
        $resultInst2 = $this->initiateAction->execute(
            customer: $this->buyer,
            params: [
                'payable' => $milestone2,
                'payment_method' => PaymentMethod::CbeBirr,
                'idempotency_key' => 'IDEMP-DEV-INST2',
                'payment_type' => PaymentType::DeveloperInstallment,
            ]
        );

        $payInst2 = $resultInst2['payment'];
        Config::set('services.cbe_birr.app_secret', 'test_key');
        $rawPayload2 = [
            'order_id' => $payInst2->reference_code,
            'cbe_txn_id' => 'CBE-TXN-DEV-002',
            'amount' => 8400000.00,
            'status' => 'SUCCESS',
        ];
        $rawBody2 = (string) json_encode($rawPayload2);
        $sig2 = hash_hmac('sha256', $rawBody2, 'test_key');
        $this->webhookAction->execute('cbe_birr', [], $rawBody2, $rawPayload2, $sig2);

        $milestone2->refresh();
        $contract->refresh();

        $this->assertSame('paid', $milestone2->status);
        $this->assertSame(8_400_000.00, (float) $milestone2->paid_amount_etb);
        $this->assertSame(0.00, (float) $milestone2->outstanding_balance_etb);
        $this->assertTrue($milestone2->isFullyPaid());

        // Contract is now fully paid
        $this->assertSame('12000000.00', $contract->calculateTotalPaidEtb());
        $this->assertSame('0.00', $contract->calculateRemainingBalanceEtb());
        $this->assertTrue($contract->isFullyPaid());
        $this->assertSame('fully_paid', $contract->getFinancialStatus());
        // Lifecycle Separation: Paying off contract does NOT automatically mark it completed or handed over
        $this->assertSame(SalesContractStatus::Active, $contract->status);
        $this->assertSame('pending_handover', $contract->getHandoverStatus());

        // Authorized handover transition
        $contract->completeHandover($this->admin, 'Final handover inspection approved.');
        $this->assertSame(SalesContractStatus::Completed, $contract->status);
        $this->assertSame('handed_over', $contract->getHandoverStatus());
    }

    public function test_multi_category_property_purchases_residential_commercial_industrial(): void
    {
        $categoriesToTest = [
            'RES' => ['type' => 'VILLA', 'price' => 25_000_000.00, 'title' => 'Luxury Bole Residential Villa'],
            'COM' => ['type' => 'OFFICE', 'price' => 40_000_000.00, 'title' => 'Kazanchis Commercial Office Floor'],
            'IND' => ['type' => 'WAREHOUSE', 'price' => 60_000_000.00, 'title' => 'Akaki Industrial Logistics Warehouse'],
        ];

        foreach ($categoriesToTest as $catCode => $meta) {
            $category = PropertyCategory::query()->where('code', $catCode)->firstOrFail();
            $propType = PropType::query()->where('category_id', $category->id)->first()
                ?? PropType::query()->firstOrFail();

            $property = Property::query()->create([
                'uuid' => (string) Str::uuid(),
                'property_code' => "PROP-{$catCode}-" . random_int(1000, 9999),
                'owner_id' => $this->seller->id,
                'category_id' => $category->id,
                'property_type_id' => $propType->id,
                'title_en' => $meta['title'],
                'status' => 'available',
                'verification_level' => 3,
            ]);

            $listingTypeId = DB::table('listing_types')->where('transaction_mode', 'SALE')->value('id') ?? 1;

            $listing = Listing::query()->create([
                'uuid' => (string) Str::uuid(),
                'listing_code' => "LST-{$catCode}-" . random_int(1000, 9999),
                'slug' => Str::slug($meta['title']) . '-' . random_int(100, 999),
                'property_id' => $property->id,
                'user_id' => $this->seller->id,
                'published_by_user_id' => $this->seller->id,
                'listing_type_id' => $listingTypeId,
                'transaction_mode' => 'SALE',
                'title_en' => $meta['title'],
                'price_etb' => $meta['price'],
                'status' => 'published',
                'published_at' => now(),
            ]);

            $offer = PropertyOffer::query()->create([
                'uuid' => (string) Str::uuid(),
                'offer_code' => "OFR-{$catCode}-" . random_int(1000, 9999),
                'listing_id' => $listing->id,
                'property_id' => $property->id,
                'buyer_user_id' => $this->buyer->id,
                'seller_user_id' => $this->seller->id,
                'listed_asking_price_etb' => $meta['price'],
                'initial_offer_amount_etb' => $meta['price'],
                'latest_amount_etb' => $meta['price'],
                'status' => 'accepted',
                'expires_at' => now()->addDays(30),
                'accepted_at' => now(),
            ]);

            $offer->syncFinancialBalanceSnapshot();

            // Initiate 20% deposit
            $depositAmount = round($meta['price'] * 0.20, 2);
            $result = $this->initiateAction->execute($this->buyer, [
                'payable' => $offer,
                'payment_method' => PaymentMethod::Telebirr,
                'idempotency_key' => "IDEMP-{$catCode}-DEP-" . random_int(1000, 9999),
                'custom_amount_etb' => $depositAmount,
                'payment_type' => PaymentType::PropertyPurchase,
            ]);

            $payment = $result['payment'];
            $this->assertSame($depositAmount, (float) $payment->total_amount_etb);
            $this->assertSame(PaymentType::PropertyPurchase, $payment->payment_type);
            $this->assertSame($property->id, $payment->property_id);

            // Confirm payment via webhook
            Config::set('services.telebirr.app_key', 'test_key');
            $rawPayload = [
                'outTradeNo' => $payment->reference_code,
                'tradeNo' => "TEL-{$catCode}-" . random_int(1000, 9999),
                'totalAmount' => $depositAmount,
                'tradeStatus' => 'COMPLETED',
            ];
            $rawBody = (string) json_encode($rawPayload);
            $sig = hash_hmac('sha256', $rawBody, 'test_key');
            $this->webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);

            $offer->refresh();
            $this->assertSame(number_format((float) $depositAmount, 2, '.', ''), $offer->calculateTotalPaidEtb());
            $this->assertSame(number_format((float) ($meta['price'] - $depositAmount), 2, '.', ''), $offer->calculateRemainingBalanceEtb());

            // Invariant check: seller retains legal ownership
            $property->refresh();
            $this->assertSame($this->seller->id, $property->owner_id);
        }
    }

    public function test_webhook_idempotency_and_replay_protection(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(4_000_000.00);

        $result = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-REPLAY-001',
            'custom_amount_etb' => 1_000_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);

        $payment = $result['payment'];
        Config::set('services.telebirr.app_key', 'test_key');
        $rawPayload = [
            'outTradeNo' => $payment->reference_code,
            'tradeNo' => 'TEL-TRD-REPLAY-001',
            'totalAmount' => 1000000.00,
            'tradeStatus' => 'COMPLETED',
        ];
        $rawBody = (string) json_encode($rawPayload);
        $sig = hash_hmac('sha256', $rawBody, 'test_key');

        // First delivery
        $res1 = $this->webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);
        $this->assertSame('success', $res1['status']);

        $offer->refresh();
        $this->assertSame('1000000.00', $offer->calculateTotalPaidEtb());

        // Replay of same webhook delivery
        $res2 = $this->webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);
        $this->assertSame('duplicate_acknowledged', $res2['status']);

        // Assert no double-crediting occurred
        $offer->refresh();
        $this->assertSame('1000000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('3000000.00', $offer->calculateRemainingBalanceEtb());

        // Exactly one journal posting group exists for this payment
        $postingCount = JournalPostingGroup::query()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->count();
        $this->assertSame(1, $postingCount);
    }

    public function test_payment_initiation_idempotency_and_payload_conflict(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(5_000_000.00);

        $idempotencyKey = 'IDEMP-CONFLICT-TEST-001';

        // Initial request
        $res1 = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => $idempotencyKey,
            'custom_amount_etb' => 1_000_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $this->assertFalse($res1['is_duplicate_replay']);

        // Duplicate replay with identical parameters returns cached payment
        $res2 = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => $idempotencyKey,
            'custom_amount_etb' => 1_000_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $this->assertTrue($res2['is_duplicate_replay']);
        $this->assertSame($res1['payment']->id, $res2['payment']->id);

        // Conflict: Reusing same idempotency key with different payload throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Idempotency conflict");

        $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::CbeBirr, // Changed method
            'idempotency_key' => $idempotencyKey,
            'custom_amount_etb' => 2_000_000.00, // Changed amount
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
    }

    public function test_formal_purchase_agreement_immutable_terms_and_versioned_amendments(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(5_000_000.00);

        // 1. Acceptance generates canonical purchase agreement snapshot
        $agreement = $offer->formalizePurchaseAgreement();

        $this->assertSame($offer->offer_code, $agreement['agreement_code']);
        $this->assertSame('1.0', $agreement['version']);
        $this->assertSame('5000000.00', $agreement['agreed_price_etb']);
        $this->assertSame('ETB', $agreement['currency']);
        $this->assertSame($this->buyer->id, $agreement['buyer']['user_id']);
        $this->assertSame($this->seller->id, $agreement['seller']['user_id']);
        $this->assertSame('v1.0-proc1357', $agreement['statutory_disclosures']['disclosure_version']);
        $this->assertTrue($agreement['statutory_disclosures']['accepted']);
        $this->assertStringContainsString('Ethiopian Civil Code Art. 1723 & 2877', $agreement['legal_conveyance_notice']);

        // 2. Direct silent modification of accepted agreed price throws DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Immutable purchase terms: Accepted agreement price cannot be silently modified without a formal versioned amendment.');
        $offer->latest_amount_etb = 6_000_000.00;
        $offer->save();
    }

    public function test_formal_purchase_agreement_audited_amendments_workflow(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(5_000_000.00);
        $offer->formalizePurchaseAgreement();

        // 3. Authorized amendment creates versioned history
        $offer->applyAuditedAmendment(
            revisedPriceEtb: 5_500_000.00,
            reason: 'Infrastructure upgrade: asphalt access road and dedicated transformer included.',
            authorizedUserId: $this->admin->id
        );

        $offer->refresh();
        $this->assertSame('5500000.00', number_format((float) $offer->latest_amount_etb, 2, '.', ''));
        $this->assertSame('5500000.00', $offer->calculateRemainingBalanceEtb());

        $updatedAgreement = $offer->metadata_json['formal_purchase_agreement'];
        $this->assertSame('1.1', $updatedAgreement['version']);
        $this->assertSame('5500000.00', $updatedAgreement['agreed_price_etb']);
        $this->assertCount(1, $updatedAgreement['amendments']);
        $this->assertSame('5000000.00', $updatedAgreement['amendments'][0]['previous_price_etb']);
        $this->assertSame('5500000.00', $updatedAgreement['amendments'][0]['revised_price_etb']);
        $this->assertSame($this->admin->id, $updatedAgreement['amendments'][0]['authorized_by_user_id']);
    }

    public function test_decimal_safe_financial_calculations_edge_cases_and_precision_drift(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(2_000_000.00);

        // 1. Payment of 0.01 ETB (smallest currency fraction)
        $res1 = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-DEC-001',
            'custom_amount_etb' => 0.01,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $p1 = $res1['payment'];
        $this->confirmTelebirrPayment($p1, 0.01);

        $offer->refresh();
        $this->assertSame('0.01', $offer->calculateTotalPaidEtb());
        $this->assertSame('1999999.99', $offer->calculateRemainingBalanceEtb());
        $this->assertFalse($offer->isFullyPaid());

        // 2. Payment of 199,999.99 ETB
        $res2 = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-DEC-002',
            'custom_amount_etb' => 199_999.99,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $p2 = $res2['payment'];
        $this->confirmTelebirrPayment($p2, 199999.99);

        $offer->refresh();
        $this->assertSame('200000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('1800000.00', $offer->calculateRemainingBalanceEtb());

        // 3. Payment of 333,333.33 ETB
        $res3 = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-DEC-003',
            'custom_amount_etb' => 333_333.33,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $p3 = $res3['payment'];
        $this->confirmTelebirrPayment($p3, 333333.33);

        $offer->refresh();
        $this->assertSame('533333.33', $offer->calculateTotalPaidEtb());
        $this->assertSame('1466666.67', $offer->calculateRemainingBalanceEtb());

        // 4. One-cent refund: refund 0.01 ETB
        $this->postingService->postPaymentRefund($p1, 0.01, 'One cent refund test', $this->admin);
        $offer->refresh();
        $this->assertSame('533333.32', $offer->calculateTotalPaidEtb());
        $this->assertSame('1466666.68', $offer->calculateRemainingBalanceEtb());

        // 5. One-cent overpayment attempt on remaining balance must be rejected
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('exceeds the remaining contractual balance');
        $offer->validateEligibleForPayment(1_466_666.69, false);
    }

    public function test_decimal_safe_exact_settlement_and_one_cent_underpayment(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(500_000.00);

        // 1. One-cent underpayment: 499,999.99 ETB
        $res = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-UNDER-001',
            'custom_amount_etb' => 499_999.99,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $this->confirmTelebirrPayment($res['payment'], 499999.99);

        $offer->refresh();
        $this->assertSame('499999.99', $offer->calculateTotalPaidEtb());
        $this->assertSame('0.01', $offer->calculateRemainingBalanceEtb());
        $this->assertFalse($offer->isFullyPaid());

        // 2. Settle the final 0.01 ETB cent
        $resFinal = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-EXACT-002',
            'custom_amount_etb' => 0.01,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $this->confirmTelebirrPayment($resFinal['payment'], 0.01);

        $offer->refresh();
        $this->assertSame('500000.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('0.00', $offer->calculateRemainingBalanceEtb());
        $this->assertTrue($offer->isFullyPaid());
    }

    public function test_concurrency_safe_purchase_payment_allocation_at_agreement_level(): void
    {
        [$property, $listing, $offer] = $this->createLandPropertyAndOffer(500_000.00);

        // Remaining balance: 500,000 ETB.
        // Request A arrives and initiates 400,000 ETB.
        $resA = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-CONCUR-A',
            'custom_amount_etb' => 400_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $paymentA = $resA['payment'];
        $this->assertSame(PaymentStatus::Pending, $paymentA->status);

        $offer->refresh();
        // Confirmed paid is still 0.00, but pending allocated is 400,000.00
        $this->assertSame('0.00', $offer->calculateTotalPaidEtb());
        $this->assertSame('500000.00', $offer->calculateRemainingBalanceEtb());
        $this->assertSame('400000.00', $offer->calculatePendingAllocatedEtb());
        $this->assertSame('100000.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // Request B arrives concurrently wanting to pay 400,000 ETB against the same agreement.
        // Even though confirmed payments are 0.00, available allocatable balance is only 100,000.00.
        // Request B must be rejected!
        try {
            $this->initiateAction->execute($this->buyer, [
                'payable' => $offer,
                'payment_method' => PaymentMethod::CbeBirr,
                'idempotency_key' => 'IDEMP-CONCUR-B',
                'custom_amount_etb' => 400_000.00,
                'payment_type' => PaymentType::PropertyPurchase,
            ]);
            $this->fail('Expected Request B to be rejected because active pending payments hold the agreement balance.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('exceeds the available unreserved balance', $e->getMessage());
        }

        // Request C for 100,000 ETB fits the available 100,000 headroom
        $resC = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::CbeBirr,
            'idempotency_key' => 'IDEMP-CONCUR-C',
            'custom_amount_etb' => 100_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $this->assertSame(PaymentStatus::Pending, $resC['payment']->status);

        // Now headroom is 0.00
        $offer->refresh();
        $this->assertSame('0.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // If Payment A fails or expires, its reservation is released
        $paymentA->transitionTo(PaymentStatus::Failed, 'Simulated timeout / provider cancellation');
        $offer->refresh();
        $this->assertSame('400000.00', $offer->calculateAllocatableRemainingBalanceEtb());

        // Now another 400,000 ETB can be initiated
        $resD = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-CONCUR-D',
            'custom_amount_etb' => 400_000.00,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $this->assertSame(PaymentStatus::Pending, $resD['payment']->status);
    }

    public function test_lifecycle_separation_financial_construction_delivery_and_ownership(): void
    {
        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'owner_user_id' => $this->seller->id,
            'legal_name' => 'Flintstone Engineering SC',
            'company_type' => 'developer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $developer = DeveloperProfile::query()->create([
            'company_id' => $companyId,
            'user_id' => $this->seller->id,
            'developer_code' => 'DEV-SEP-' . random_int(1000, 9999),
            'verification_status' => 'verified',
        ]);

        $project = DevelopmentProject::query()->create([
            'uuid' => (string) Str::uuid(),
            'developer_id' => $developer->id,
            'company_id' => $companyId,
            'project_code' => 'PRJ-SEP-' . random_int(1000, 9999),
            'title_en' => 'Twin Peaks Towers',
            'status' => ProjectStatus::UnderConstruction,
            'overall_progress_pct' => 60.00, // 60% construction complete
            'land_area_sqm' => 1500.00,
        ]);

        $building = ProjectBuilding::query()->create([
            'project_id' => $project->id,
            'building_code' => 'TOWER-A',
            'name_en' => 'Tower A',
            'floors_count' => 12,
            'status' => 'under_construction',
        ]);

        $floor = BuildingFloor::query()->create([
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_number' => 4,
            'floor_label' => 'Level 4',
        ]);

        $unit = ProjectUnit::query()->create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'building_id' => $building->id,
            'floor_id' => $floor->id,
            'unit_number' => '401',
            'floor_number' => 4,
            'unit_type' => '2bhk',
            'bedrooms' => 2,
            'bathrooms' => 2,
            'area_sqm' => 95.00,
            'price_etb' => 4_000_000.00,
            'status' => UnitInventoryStatus::Contracted,
        ]);

        $reservation = UnitReservation::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_code' => 'RES-SEP-' . random_int(1000, 9999),
            'idempotency_key' => 'IDEMP-RES-SEP',
            'project_unit_id' => $unit->id,
            'project_id' => $project->id,
            'buyer_user_id' => $this->buyer->id,
            'agreed_price_etb' => 4_000_000.00,
            'deposit_amount_etb' => 400_000.00,
            'status' => ReservationStatus::Reserved,
            'hold_expires_at' => now()->addDays(14),
        ]);

        $contract = DeveloperSalesContract::query()->create([
            'uuid' => (string) Str::uuid(),
            'contract_code' => 'CNT-SEP-' . random_int(1000, 9999),
            'reservation_id' => $reservation->id,
            'project_unit_id' => $unit->id,
            'project_id' => $project->id,
            'buyer_user_id' => $this->buyer->id,
            'developer_company_id' => $companyId,
            'contract_price_etb' => 4_000_000.00,
            'deposit_paid_etb' => 0.00,
            'balance_payable_etb' => 4_000_000.00,
            'status' => SalesContractStatus::Active,
            'disclosure_accepted_by_buyer' => true,
            'signed_at' => now(),
            'payment_plan_snapshot_json' => ['plan' => 'custom'],
        ]);

        $installment = ContractInstallment::query()->create([
            'contract_id' => $contract->id,
            'sequence_number' => 1,
            'milestone_label_en' => 'Full Purchase Lump Sum',
            'percentage' => 100.00,
            'amount_etb' => 4_000_000.00,
            'paid_amount_etb' => 0.00,
            'outstanding_balance_etb' => 4_000_000.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        // Buyer pays the complete 4,000,000 ETB
        $res = $this->initiateAction->execute($this->buyer, [
            'payable' => $installment,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => 'IDEMP-LIFECYCLE-FULL',
            'payment_type' => PaymentType::DeveloperInstallment,
        ]);
        $this->confirmTelebirrPayment($res['payment'], 4000000.00);

        $contract->refresh();
        $installment->refresh();
        $project->refresh();

        // 1. Financial Status Dimension: FULLY PAID
        $this->assertTrue($contract->isFullyPaid());
        $this->assertSame('fully_paid', $contract->getFinancialStatus());
        $this->assertSame('0.00', $contract->calculateRemainingBalanceEtb());
        $this->assertSame('4000000.00', $contract->calculateTotalPaidEtb());

        // 2. Construction Status Dimension: INCOMPLETE (Still 60%)
        // Crucial invariant: Full payment does NOT mark construction completed
        $this->assertSame(60.00, (float) $project->overall_progress_pct);
        $this->assertSame(60.00, $contract->getConstructionProgressPct());
        $this->assertSame(ProjectStatus::UnderConstruction, $project->status);

        // 3. Handover / Delivery Dimension: PENDING HANDOVER
        // Contract status remains Active, NOT Completed
        $this->assertSame(SalesContractStatus::Active, $contract->status);
        $this->assertSame('pending_handover', $contract->getHandoverStatus());

        // 4. Legal Ownership Dimension: NOT TRANSFERRED
        $this->assertSame('pending_cadastre_registration', $contract->getLegalOwnershipStatus());

        // 5. Authorized Handover execution after inspection
        $contract->completeHandover($this->admin, 'Construction completed and keys delivered after municipal inspection.');
        $contract->refresh();

        $this->assertSame(SalesContractStatus::Completed, $contract->status);
        $this->assertSame('handed_over', $contract->getHandoverStatus());
    }

    // =========================================================================
    // ALL SIX PROPERTY TAXONOMY CATEGORIES INDIVIDUAL WORKFLOW TESTS
    // =========================================================================

    public function test_taxonomy_category_lnd_land_purchase_workflow(): void
    {
        $this->executeCategoryPurchaseTest('LND', 'RES_LAND', 8_000_000.00, 2_000_000.00, 'Ayat Land Plot for Villa');
    }

    public function test_taxonomy_category_res_residential_purchase_workflow(): void
    {
        $this->executeCategoryPurchaseTest('RES', 'VILLA', 22_000_000.00, 5_000_000.00, 'Old Airport Residential Villa');
    }

    public function test_taxonomy_category_com_commercial_purchase_workflow(): void
    {
        $this->executeCategoryPurchaseTest('COM', 'OFFICE', 35_000_000.00, 10_000_000.00, 'Bole Commercial Office Space');
    }

    public function test_taxonomy_category_ind_industrial_purchase_workflow(): void
    {
        $this->executeCategoryPurchaseTest('IND', 'FACTORY', 55_000_000.00, 15_000_000.00, 'Dukem Industrial Manufacturing Plant');
    }

    public function test_taxonomy_category_hsp_hospitality_purchase_workflow(): void
    {
        $this->executeCategoryPurchaseTest('HSP', 'HOTEL', 80_000_000.00, 20_000_000.00, 'Bishoftu Lake Resort Hotel');
    }

    public function test_taxonomy_category_spc_special_purpose_purchase_workflow(): void
    {
        $this->executeCategoryPurchaseTest('SPC', 'HOSPITAL', 95_000_000.00, 25_000_000.00, 'Addis Ababa Medical Center Hospital');
    }

    private function executeCategoryPurchaseTest(
        string $catCode,
        string $typeCode,
        float $agreedPrice,
        float $depositAmount,
        string $title
    ): void {
        $category = PropertyCategory::query()->where('code', $catCode)->firstOrFail();
        $propertyType = PropType::query()->where('category_id', $category->id)->first()
            ?? PropType::query()->create([
                'category_id' => $category->id,
                'code' => $typeCode,
                'name_en' => $title,
            ]);

        $property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-' . $catCode . '-' . random_int(1000, 9999),
            'owner_id' => $this->seller->id,
            'category_id' => $category->id,
            'property_type_id' => $propertyType->id,
            'title_en' => $title,
            'status' => 'available',
        ]);

        $listingTypeId = DB::table('listing_types')->where('transaction_mode', 'SALE')->value('id')
            ?? DB::table('listing_types')->value('id')
            ?? 1;

        $listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-' . $catCode . '-' . random_int(1000, 9999),
            'property_id' => $property->id,
            'published_by_user_id' => $this->seller->id,
            'listing_type_id' => $listingTypeId,
            'transaction_mode' => 'SALE',
            'price_etb' => $agreedPrice,
            'currency' => 'ETB',
            'title_en' => $title,
            'slug' => Str::slug($title) . '-' . random_int(1000, 9999),
            'status' => 'published',
        ]);

        $offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-' . $catCode . '-' . random_int(1000, 9999),
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'buyer_user_id' => $this->buyer->id,
            'seller_user_id' => $this->seller->id,
            'listed_asking_price_etb' => $agreedPrice,
            'initial_offer_amount_etb' => $agreedPrice,
            'latest_amount_etb' => $agreedPrice,
            'status' => 'accepted',
            'expires_at' => now()->addDays(30),
            'accepted_at' => now(),
        ]);
        $offer->formalizePurchaseAgreement();

        // 1. Initial Deposit Payment via Telebirr
        $result = $this->initiateAction->execute($this->buyer, [
            'payable' => $offer,
            'payment_method' => PaymentMethod::Telebirr,
            'idempotency_key' => "IDEMP-CAT-{$catCode}-" . random_int(1000, 9999),
            'custom_amount_etb' => $depositAmount,
            'payment_type' => PaymentType::PropertyPurchase,
        ]);
        $payment = $result['payment'];

        // Confirm payment
        $this->confirmTelebirrPayment($payment, $depositAmount);

        $offer->refresh();
        $property->refresh();

        // Financial precision assertions
        $expectedPaid = number_format($depositAmount, 2, '.', '');
        $expectedRemaining = number_format($agreedPrice - $depositAmount, 2, '.', '');
        $this->assertSame($expectedPaid, $offer->calculateTotalPaidEtb());
        $this->assertSame($expectedRemaining, $offer->calculateRemainingBalanceEtb());

        // Double-entry ledger verification
        $postingGroup = JournalPostingGroup::query()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->first();
        $this->assertNotNull($postingGroup);
        $this->assertTrue($postingGroup->is_balanced);

        // Legal title invariance assertion: seller remains owner
        $this->assertSame($this->seller->id, $property->owner_id);
    }

    private function confirmTelebirrPayment(Payment $payment, float $amount): void
    {
        Config::set('services.telebirr.app_key', 'test_key');
        $rawPayload = [
            'outTradeNo' => $payment->reference_code,
            'tradeNo' => 'TEL-TRD-' . random_int(10000, 99999),
            'totalAmount' => $amount,
            'tradeStatus' => 'COMPLETED',
        ];
        $rawBody = (string) json_encode($rawPayload);
        $sig = hash_hmac('sha256', $rawBody, 'test_key');
        $this->webhookAction->execute('telebirr', [], $rawBody, $rawPayload, $sig);
        $payment->refresh();
    }
}
