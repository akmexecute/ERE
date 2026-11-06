<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Actions;

use App\Domains\Advertising\Enums\CampaignStatus;
use App\Domains\Advertising\Models\AdCampaign;
use App\Domains\Advertising\Models\AdPlacement;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\Listing\Models\Listing;
use App\Domains\Payment\Models\Payment;
use App\Domains\Promotion\Models\ListingPromotion;
use App\Domains\Promotion\Models\PromotionTierPackage;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ActivateSubscriptionAction
{
    public function __construct(
        private DoubleEntryPostingService $postingService
    ) {}

    public function execute(UserSubscription $subscription, Payment $payment): UserSubscription
    {
        // 1. Idempotency Check: if already active, do not execute duplicate activation
        if ($subscription->status === SubscriptionStatus::Active) {
            return $subscription;
        }

        return DB::transaction(function () use ($subscription, $payment): UserSubscription {
            // 2. Expire any previously active subscriptions for this user
            UserSubscription::query()
                ->where('user_id', $subscription->user_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', SubscriptionStatus::Active)
                ->update([
                    'status' => SubscriptionStatus::Expired,
                    'cancelled_at' => now(),
                ]);

            // 3. Activate current subscription
            $durationDays = $subscription->plan?->duration_days ?? 30;
            $subscription->status = SubscriptionStatus::Active;
            $subscription->starts_at = now();
            $subscription->expires_at = now()->addDays($durationDays);
            $subscription->payment_id = $payment->id;
            $subscription->save();

            // 4. Fulfill Add-on Upgrades if purchased alongside the plan
            $addOns = $subscription->metadata_json['add_ons'] ?? [];

            // 4.1 Fulfill Promoted Listing
            if (! empty($addOns['promotion'])) {
                $promoData = $addOns['promotion'];
                $promoPkg = PromotionTierPackage::find($promoData['package_id']);
                if ($promoPkg) {
                    $targetListingId = $promoData['target_listing_id'] ?? null;
                    if (! $targetListingId) {
                        // Find user's latest published listing as fallback
                        $targetListing = Listing::query()
                            ->where('published_by_user_id', $subscription->user_id)
                            ->latest('published_at')
                            ->first();
                        $targetListingId = $targetListing?->id;
                    }

                    if ($targetListingId) {
                        ListingPromotion::query()->create([
                            'uuid' => (string) Str::uuid(),
                            'listing_id' => $targetListingId,
                            'package_id' => $promoPkg->id,
                            'user_id' => $subscription->user_id,
                            'tier' => $promoPkg->tier->value,
                            'ranking_weight' => $promoPkg->ranking_weight,
                            'price_etb' => $promoPkg->price_etb,
                            'starts_at' => now(),
                            'expires_at' => now()->addDays($promoPkg->duration_days),
                            'status' => 'active',
                            'subscription_id' => $subscription->id,
                            'payment_id' => $payment->id,
                        ]);

                        // Sync listing promotion state
                        Listing::query()->where('id', $targetListingId)->update([
                            'is_featured' => true,
                            'promotion_tier' => $promoPkg->tier->value,
                        ]);
                    }
                }
            }

            // 4.2 Fulfill Multi-Placement Advertising Campaign
            if (! empty($addOns['advertising'])) {
                $adData = $addOns['advertising'];
                $placement = AdPlacement::find($adData['placement_id']);
                if ($placement) {
                    AdCampaign::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'user_id' => $subscription->user_id,
                        'company_id' => $subscription->company_id,
                        'placement_id' => $placement->id,
                        'placement' => $placement->placement->value,
                        'title' => $adData['target_ad_title'] ?? "{$subscription->user->name} Campaign",
                        'target_url' => $adData['target_ad_url'] ?? config('app.url'),
                        'price_etb' => $placement->price_etb,
                        'starts_at' => now(),
                        'expires_at' => now()->addDays($placement->duration_days),
                        'status' => CampaignStatus::PendingApproval, // Pending admin approval per specification
                        'subscription_id' => $subscription->id,
                        'payment_id' => $payment->id,
                    ]);
                }
            }

            // 5. Post to General Ledger: Dr 1010 Cash & Clearing / Cr 4020 Listing & Subscription Revenue
            $this->postingService->postSubscriptionPayment($payment);

            return $subscription;
        });
    }
}

