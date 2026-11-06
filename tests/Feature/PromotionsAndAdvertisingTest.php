<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Advertising\Enums\CampaignStatus;
use App\Domains\Advertising\Models\AdCampaign;
use App\Domains\Advertising\Models\AdPlacement;
use App\Domains\Financial\Models\JournalPostingGroup;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentStatus;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Payment\Models\Payment;
use App\Domains\Promotion\Models\ListingPromotion;
use App\Domains\Promotion\Models\PromotionTierPackage;
use App\Domains\Property\Enums\PropertyStatus;
use App\Domains\Property\Models\Property;
use App\Domains\Property\Models\PropertyCategory;
use App\Domains\Property\Models\PropertyType;
use App\Domains\Subscription\Actions\ActivateSubscriptionAction;
use App\Domains\Subscription\Actions\InitiateSubscriptionCheckoutAction;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\UserSubscription;
use App\Domains\User\Models\User;
use Database\Seeders\AdPlacementSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PromotionTierPackageSeeder;
use Database\Seeders\PropertyTaxonomySeeder;
use Database\Seeders\RbacRolePermissionSeeder;
use Database\Seeders\SubscriptionFeatureSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PromotionsAndAdvertisingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private Listing $listing;
    private SubscriptionPlan $agentProPlan;
    private SubscriptionPlan $freePlan;
    private PromotionTierPackage $featuredPackage;
    private AdPlacement $heroAdPlacement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacRolePermissionSeeder::class);
        $this->seed(PropertyTaxonomySeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(SubscriptionFeatureSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(PromotionTierPackageSeeder::class);
        $this->seed(AdPlacementSeeder::class);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Agent Chala',
            'phone' => '+251911123456',
            'status' => 'active',
        ]);
        $this->user->assignRole('agent');

        $this->admin = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Boss',
            'phone' => '+251911999999',
            'status' => 'active',
        ]);
        $this->admin->assignRole('super_admin');

        $cat = PropertyCategory::firstOrCreate(['code' => 'RES'], ['name_en' => 'Residential', 'sort_order' => 1, 'is_active' => true]);
        $type = PropertyType::firstOrCreate(['code' => 'APT'], ['category_id' => $cat->id, 'name_en' => 'Apartment', 'sort_order' => 1, 'is_active' => true]);

        $prop = Property::query()->create([
            'uuid' => (string) Str::uuid(),
            'property_code' => 'ETH-TEST-PROP-001',
            'title_en' => 'Bole Luxury Apartment',
            'category_id' => $cat->id,
            'property_type_id' => $type->id,
            'owner_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => PropertyStatus::Available,
        ]);

        $listingTypeId = \Illuminate\Support\Facades\DB::table('listing_types')->where('code', 'FOR_SALE')->value('id') ?? 1;

        $this->listing = Listing::query()->create([
            'uuid' => (string) Str::uuid(),
            'listing_code' => 'LST-TEST-001',
            'slug' => 'bole-luxury-apartment-001',
            'property_id' => $prop->id,
            'listing_type_id' => $listingTypeId,
            'published_by_user_id' => $this->user->id,
            'transaction_mode' => 'BUY',
            'price_etb' => 8_000_000,
            'currency' => 'ETB',
            'status' => ListingStatus::Published->value,
        ]);

        $this->freePlan = SubscriptionPlan::where('code', 'free')->firstOrFail();
        $this->agentProPlan = SubscriptionPlan::where('code', 'agent_pro')->firstOrFail();
        $this->featuredPackage = PromotionTierPackage::where('tier', 'featured')->firstOrFail();
        $this->heroAdPlacement = AdPlacement::where('placement', 'homepage')->firstOrFail();
    }

    public function test_standard_free_plan_strictly_rejects_promotions_and_advertising_upgrades(): void
    {
        $action = app(InitiateSubscriptionCheckoutAction::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Standard Free plan cannot include paid promotional or advertising upgrades.');

        $action->execute($this->user, $this->freePlan, [
            'promotion_package_id' => $this->featuredPackage->id,
        ]);
    }

    public function test_paid_plan_checkout_calculates_bcmath_total_with_optional_upgrades(): void
    {
        $action = app(InitiateSubscriptionCheckoutAction::class);

        // Agent Pro: 1200.00, Featured: 800.00, Hero Ad: 3500.00 => Total: 5500.00 ETB
        $result = $action->execute($this->user, $this->agentProPlan, [
            'payment_method' => PaymentMethod::Telebirr,
            'promotion_package_id' => $this->featuredPackage->id,
            'target_listing_id' => $this->listing->id,
            'ad_placement_id' => $this->heroAdPlacement->id,
            'target_ad_title' => 'Top Bole Apartments Sale',
            'target_ad_url' => 'https://example.com/bole-sale',
        ]);

        $this->assertFalse($result['is_free']);
        $subscription = $result['subscription'];
        $payment = $result['payment'];

        $this->assertSame(SubscriptionStatus::PendingPayment, $subscription->status);
        $this->assertEquals(7800.00, (float) $subscription->price_etb);

        $this->assertNotNull($payment);
        $this->assertEquals(7800.00, (float) $payment->total_amount_etb);
        $this->assertSame(PaymentType::Subscription, $payment->payment_type);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    public function test_payment_activation_fulfills_promotions_ads_and_posts_to_ledger_4020(): void
    {
        $checkoutAction = app(InitiateSubscriptionCheckoutAction::class);
        $activateAction = app(ActivateSubscriptionAction::class);

        $checkoutResult = $checkoutAction->execute($this->user, $this->agentProPlan, [
            'payment_method' => PaymentMethod::Telebirr,
            'promotion_package_id' => $this->featuredPackage->id,
            'target_listing_id' => $this->listing->id,
            'ad_placement_id' => $this->heroAdPlacement->id,
            'target_ad_title' => 'Top Bole Apartments Sale',
            'target_ad_url' => 'https://example.com/bole-sale',
        ]);

        $subscription = $checkoutResult['subscription'];
        $payment = $checkoutResult['payment'];

        // Simulate successful payment webhook
        $payment->status = PaymentStatus::Successful;
        $payment->save();

        $activatedSub = $activateAction->execute($subscription, $payment);

        $this->assertSame(SubscriptionStatus::Active, $activatedSub->status);
        $this->assertNotNull($activatedSub->starts_at);
        $this->assertNotNull($activatedSub->expires_at);

        // 1. Promoted listing created
        $this->assertDatabaseHas('listing_promotions', [
            'listing_id' => $this->listing->id,
            'user_id' => $this->user->id,
            'tier' => 'featured',
            'status' => 'active',
            'ranking_weight' => 10,
        ]);

        // 2. Ad campaign created
        $this->assertDatabaseHas('ad_campaigns', [
            'user_id' => $this->user->id,
            'placement_id' => $this->heroAdPlacement->id,
            'title' => 'Top Bole Apartments Sale',
            'status' => CampaignStatus::PendingApproval->value,
        ]);

        // 3. Double-entry ledger verified: Dr 1010, Cr 4020
        $postingGroup = JournalPostingGroup::query()
            ->where('source_type', Payment::class)
            ->where('source_id', $payment->id)
            ->with('entries.account')
            ->first();

        $this->assertNotNull($postingGroup);
        $entries = $postingGroup->entries;

        $debitEntry = $entries->firstWhere('entry_type', 'debit');
        $creditEntry = $entries->firstWhere('entry_type', 'credit');

        $this->assertNotNull($debitEntry);
        $this->assertNotNull($creditEntry);

        $this->assertSame('1010', $debitEntry->account->code);
        $this->assertEquals(7800.00, (float) $debitEntry->amount_etb);

        $this->assertSame('4020', $creditEntry->account->code);
        $this->assertEquals(7800.00, (float) $creditEntry->amount_etb);
    }

    public function test_ad_click_controller_increments_clicks_and_redirects(): void
    {
        $campaign = AdCampaign::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'placement_id' => $this->heroAdPlacement->id,
            'placement' => 'homepage',
            'title' => 'Test Campaign Link',
            'target_url' => 'https://example.com/property-campaign',
            'price_etb' => 3500.00,
            'status' => CampaignStatus::Active,
            'clicks_count' => 5,
        ]);

        $response = $this->get(route('ads.click', $campaign->uuid));

        $response->assertRedirect('https://example.com/property-campaign');

        $campaign->refresh();
        $this->assertSame(6, $campaign->clicks_count);
    }

    public function test_admin_can_approve_or_reject_ad_campaign(): void
    {
        $campaign = AdCampaign::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'placement_id' => $this->heroAdPlacement->id,
            'placement' => 'homepage',
            'title' => 'Pending Review Campaign',
            'target_url' => 'https://example.com/pending',
            'price_etb' => 3500.00,
            'status' => CampaignStatus::PendingApproval,
        ]);

        // 1. Approve
        $approveResp = $this->actingAs($this->admin)->post(route('admin.advertising.campaigns.approve', $campaign));
        $approveResp->assertRedirect(route('admin.advertising.index'));

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Active, $campaign->status);
        $this->assertEquals($this->admin->id, $campaign->approved_by);
        $this->assertNotNull($campaign->approved_at);

        // 2. Reject
        $rejectResp = $this->actingAs($this->admin)->post(route('admin.advertising.campaigns.reject', $campaign), [
            'rejection_reason' => 'Target landing page is currently inaccessible.',
        ]);
        $rejectResp->assertRedirect(route('admin.advertising.index'));

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Rejected, $campaign->status);
        $this->assertSame('Target landing page is currently inaccessible.', $campaign->rejection_reason);
    }
}
