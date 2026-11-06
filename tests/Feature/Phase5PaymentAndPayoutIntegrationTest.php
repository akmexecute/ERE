<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Financial\Enums\PayoutSchedule;
use App\Domains\Financial\Enums\PayoutStatus;
use App\Domains\Financial\Models\HostPayout;
use App\Domains\Financial\Models\HostPayoutDestination;
use App\Domains\Financial\Models\JournalPostingGroup;
use App\Domains\Host\Models\Host;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Payment\Enums\BankTransferVerificationStatus;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Models\Payment;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType as PropType;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Stay\Models\AccommodationUnitType;
use App\Domains\Stay\Models\StayListingProfile;
use App\Domains\User\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Phase5PaymentAndPayoutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
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

        $this->buyer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Buyer Kenenisa',
            'phone' => '+251911555111',
            'status' => 'active',
        ]);
        $this->buyer->assignRoleBySlug('buyer');

        $this->seller = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Seller Derartu',
            'phone' => '+251911555222',
            'status' => 'active',
        ]);
        $this->seller->assignRoleBySlug('seller');

        $this->hostUser = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Host Meseret',
            'phone' => '+251911555333',
            'status' => 'active',
        ]);
        $this->hostUser->assignRoleBySlug('host');

        $this->officer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Officer Aster',
            'phone' => '+251911555444',
            'status' => 'active',
        ]);
        $this->officer->assignRoleBySlug('super_admin');

        $this->host = Host::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->hostUser->id,
            'display_name' => 'Meseret Luxury Villas',
            'payout_schedule' => 'after_checkin',
            'status' => 'active',
        ]);

        $catId = PropertyCategory::query()->value('id');
        $typeId = PropType::query()->value('id');

        $this->property = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'PROP-2026-F5-01',
            'owner_id' => $this->seller->id,
            'category_id' => $catId,
            'property_type_id' => $typeId,
            'title_en' => 'Bole Medhanealem Modern Villa',
            'status' => 'available',
            'verification_level' => 3,
        ]);

        $listingType = \App\Domains\Listing\Models\ListingType::query()->first();
        $listing = \App\Domains\Listing\Models\Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-2026-F5-01',
            'property_id' => $this->property->id,
            'published_by_user_id' => $this->seller->id,
            'listing_type_id' => $listingType?->id ?? 1,
            'transaction_mode' => 'STAY',
            'title_en' => 'Bole Medhanealem Villa Stay',
            'slug' => 'bole-medhanealem-villa-stay-' . Str::random(6),
            'price_etb' => 8000.00,
            'status' => 'published',
        ]);

        $stayProfile = StayListingProfile::query()->create([
            'listing_id' => $listing->id,
            'property_id' => $this->property->id,
            'host_id' => $this->host->id,
            'min_stay_nights' => 1,
            'max_stay_nights' => 30,
            'base_nightly_rate_etb' => 8000.00,
        ]);

        $unitType = AccommodationUnitType::query()->create([
            'stay_listing_profile_id' => $stayProfile->id,
            'property_id' => $this->property->id,
            'name_en' => 'Entire Villa',
            'base_nightly_rate_etb' => 8000.00,
            'is_active' => true,
        ]);

        $unit = AccommodationUnit::query()->create([
            'unit_type_id' => $unitType->id,
            'property_id' => $this->property->id,
            'unit_number_or_code' => 'VILLA-BOLE-01',
            'status' => 'ready',
        ]);

        $this->booking = Booking::query()->create([
            'uuid' => (string) Str::uuid(),
            'booking_code' => 'BK-2026-F5-001',
            'idempotency_key' => 'IDEMP-F5-BK-001',
            'listing_id' => $listing->id,
            'property_id' => $this->property->id,
            'stay_listing_profile_id' => $stayProfile->id,
            'accommodation_unit_id' => $unit->id,
            'host_id' => $this->host->id,
            'guest_user_id' => $this->buyer->id,
            'booking_mode' => 'instant_book',
            'check_in_date' => '2026-11-20',
            'check_out_date' => '2026-11-25',
            'nights_count' => 5,
            'accommodation_raw_subtotal_etb' => 40000.00,
            'discount_amount_etb' => 0.00,
            'accommodation_net_subtotal_etb' => 40000.00,
            'cleaning_fee_etb' => 1500.00,
            'platform_fee_etb' => 3000.00,
            'tax_amount_etb' => 6000.00,
            'security_deposit_etb' => 8000.00,
            'total_amount_etb' => 58500.00,
            'currency' => 'ETB',
            'status' => 'awaiting_payment',
        ]);

        $this->offer = PropertyOffer::query()->create([
            'uuid' => (string) Str::uuid(),
            'offer_code' => 'OFR-2026-F5-001',
            'listing_id' => $listing->id,
            'property_id' => $this->property->id,
            'buyer_user_id' => $this->buyer->id,
            'seller_user_id' => $this->seller->id,
            'listed_asking_price_etb' => 20000000.00,
            'initial_offer_amount_etb' => 18000000.00,
            'latest_amount_etb' => 19000000.00,
            'status' => 'accepted',
            'expires_at' => now()->addDays(30),
            'accepted_at' => now(),
            'metadata_json' => [
                'total_paid_etb' => 0.00,
                'remaining_balance_etb' => 19000000.00,
            ],
        ]);
    }

    public function test_checkout_screen_renders_with_authoritative_server_breakdown(): void
    {
        $response = $this->actingAs($this->buyer)->get(route('payments.checkout', [
            'payable_type' => Booking::class,
            'payable_id' => $this->booking->id,
        ]));

        $response->assertOk();
        $response->assertSee('Secure Unified Checkout');
        $response->assertSee('58,500.00'); // Total amount
        $response->assertSee('Telebirr');
        $response->assertSee('CBE Birr');
        $response->assertSee('Visa / Mastercard');
        $response->assertSee('Bank Deposit / Slip');
    }

    public function test_property_purchase_checkout_renders_contract_details_and_remaining_balance(): void
    {
        $response = $this->actingAs($this->buyer)->get(route('payments.checkout', [
            'payable_type' => PropertyOffer::class,
            'payable_id' => $this->offer->id,
        ]));

        $response->assertOk();
        $response->assertSee('Property / Land Acquisition');
        $response->assertSee('19,000,000.00'); // Agreed price
        $response->assertSee('Notice: Financial settlement records funds on the ledger. Legal title deed transfer remains separate under civil registration.');
    }

    public function test_payment_initiation_endpoint_creates_payment_intent(): void
    {
        $response = $this->actingAs($this->buyer)->postJson(route('payments.initiate'), [
            'payable_type' => Booking::class,
            'payable_id' => $this->booking->id,
            'payment_method' => PaymentMethod::Telebirr->value,
            'idempotency_key' => 'IDEMP-API-TEST-01',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'is_duplicate' => false,
            'payment' => [
                'amount_etb' => 58500.00,
                'status' => 'pending',
            ],
        ]);

        $this->assertDatabaseHas('payments', [
            'idempotency_key' => 'IDEMP-API-TEST-01',
            'customer_user_id' => $this->buyer->id,
            'status' => 'pending',
        ]);
    }

    public function test_unauthorized_user_cannot_initiate_payment_for_anothers_payable(): void
    {
        $stranger = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stranger Danger',
            'phone' => '+251911999111',
            'status' => 'active',
        ]);

        $response = $this->actingAs($stranger)->postJson(route('payments.initiate'), [
            'payable_type' => Booking::class,
            'payable_id' => $this->booking->id,
            'payment_method' => PaymentMethod::Telebirr->value,
        ]);

        $response->assertStatus(422); // Catches DomainException and returns 422 JSON response
    }

    public function test_bank_slip_upload_and_officer_verification_flow(): void
    {
        $payment = Payment::query()->create([
            'idempotency_key' => 'IDEMP-BANK-HTTP-01',
            'customer_user_id' => $this->buyer->id,
            'payable_type' => Booking::class,
            'payable_id' => $this->booking->id,
            'property_id' => $this->property->id,
            'payment_type' => PaymentType::Booking,
            'principal_amount_etb' => 40000.00,
            'fee_amount_etb' => 4500.00,
            'tax_amount_etb' => 6000.00,
            'total_amount_etb' => 58500.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::BankTransfer,
            'provider' => 'bank_transfer',
            'status' => PaymentStatus::Pending,
        ]);

        // Buyer uploads slip
        $fakeSlip = UploadedFile::fake()->create('cbe_slip.pdf', 300, 'application/pdf');
        $uploadResponse = $this->actingAs($this->buyer)->post(route('payments.slip.submit', $payment->uuid), [
            'slip_file' => $fakeSlip,
            'transfer_reference' => 'CBE-FT-889900',
            'bank_name' => 'Commercial Bank of Ethiopia',
            'deposited_amount_etb' => 58500.00,
        ]);

        $uploadResponse->assertRedirect(route('payments.show', $payment->uuid));
        $this->assertDatabaseHas('bank_transfer_proofs', [
            'payment_id' => $payment->id,
            'transfer_reference' => 'CBE-FT-889900',
            'verification_status' => BankTransferVerificationStatus::Submitted->value,
        ]);

        // Verify status remains pending
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
    }

    public function test_webhook_api_processes_valid_callback_and_posts_double_entry(): void
    {
        Config::set('services.chapa.webhook_secret', 'webhook-secret-key');

        $payment = Payment::query()->create([
            'idempotency_key' => 'IDEMP-CHAPA-HTTP-01',
            'customer_user_id' => $this->buyer->id,
            'payable_type' => Booking::class,
            'payable_id' => $this->booking->id,
            'property_id' => $this->property->id,
            'payment_type' => PaymentType::Booking,
            'principal_amount_etb' => 40000.00,
            'fee_amount_etb' => 4500.00,
            'tax_amount_etb' => 6000.00,
            'total_amount_etb' => 58500.00,
            'currency' => 'ETB',
            'payment_method' => PaymentMethod::Card,
            'provider' => 'chapa',
            'reference_code' => 'PAY-CHAPA-TEST-001',
            'status' => PaymentStatus::Pending,
        ]);

        $payload = [
            'event' => 'charge.complete',
            'event_id' => 'chp_http_evt_01',
            'reference' => 'PAY-CHAPA-TEST-001',
            'amount' => 58500.00,
            'currency' => 'ETB',
            'status' => 'success',
        ];
        $raw = (string) json_encode($payload);
        $sig = hash_hmac('sha256', $raw, 'webhook-secret-key');

        $response = $this->call(
            'POST',
            route('api.webhooks.payments', 'chapa'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_x-chapa-signature' => $sig,
            ],
            $raw
        );

        $response->assertOk();
        $response->assertJson([
            'received' => true,
            'provider' => 'chapa',
            'result' => [
                'status' => 'success',
            ],
        ]);

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Successful, $payment->status);

        // Verify Journal posting group created and balanced
        $postingGroup = JournalPostingGroup::query()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->first();

        $this->assertNotNull($postingGroup);
        $this->assertTrue($postingGroup->is_balanced);
        $this->assertEquals(58500.00, (float) $postingGroup->total_debit_amount_etb);
        $this->assertEquals(58500.00, (float) $postingGroup->total_credit_amount_etb);
    }

    public function test_host_payout_console_displays_waterfall_and_supports_hold_release(): void
    {
        $dest = HostPayoutDestination::query()->create([
            'uuid' => (string) Str::uuid(),
            'host_id' => $this->host->id,
            'user_id' => $this->hostUser->id,
            'channel' => 'telebirr',
            'institution_name' => 'Telebirr',
            'account_holder_name' => 'Host Meseret',
            'account_number_encrypted' => 'enc',
            'account_number_masked' => '****5333',
            'is_verified' => true,
            'is_primary' => true,
            'status' => 'active',
        ]);
        $dest->setAccountNumber('0911555333');
        $dest->save();

        $payout = HostPayout::query()->create([
            'host_id' => $this->host->id,
            'booking_id' => $this->booking->id,
            'payout_destination_id' => $dest->id,
            'schedule_type' => PayoutSchedule::AfterCheckin,
            'scheduled_for' => now()->addDay(),
            'gross_revenue_etb' => 40000.00,
            'platform_deductions_etb' => 3000.00,
            'adjustments_amount_etb' => 0.00,
            'net_payable_etb' => 37000.00,
            'currency' => 'ETB',
            'status' => PayoutStatus::Scheduled,
        ]);

        // 1. Host Console renders waterfall
        $response = $this->actingAs($this->hostUser)->get(route('payments.payouts'));
        $response->assertOk();
        $response->assertSee('Host & Landlord Payout Console');
        $response->assertSee('40,000.00'); // Gross
        $response->assertSee('3,000.00');  // Deduction
        $response->assertSee('37,000.00'); // Net

        // 2. Put on Hold
        $holdResponse = $this->actingAs($this->officer)->post(route('payments.payouts.hold', $payout->uuid), [
            'reason' => 'Guest claims amenities were missing',
        ]);
        $holdResponse->assertRedirect();
        $payout->refresh();
        $this->assertEquals(PayoutStatus::OnHold, $payout->status);

        // 3. Release from Hold
        $releaseResponse = $this->actingAs($this->officer)->post(route('payments.payouts.release', $payout->uuid));
        $releaseResponse->assertRedirect();
        $payout->refresh();
        $this->assertEquals(PayoutStatus::Eligible, $payout->status);

        // 4. Settle payout
        $processResponse = $this->actingAs($this->officer)->post(route('payments.payouts.process', $payout->uuid));
        $processResponse->assertRedirect();
        $payout->refresh();
        $this->assertEquals(PayoutStatus::Paid, $payout->status);
    }
}
