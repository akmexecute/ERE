<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Actions;

use App\Domains\Advertising\Models\AdPlacement;
use App\Domains\Payment\Actions\InitiatePaymentAction;
use App\Domains\Payment\Enums\PaymentMethod;
use App\Domains\Payment\Enums\PaymentType;
use App\Domains\Promotion\Models\PromotionTierPackage;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\UserSubscription;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class InitiateSubscriptionCheckoutAction
{
    public function __construct(
        private InitiatePaymentAction $initiatePaymentAction
    ) {}

    /**
     * @param array{
     *     payment_method?: string|PaymentMethod,
     *     promotion_package_id?: int|string|null,
     *     ad_placement_id?: int|string|null,
     *     idempotency_key?: string|null,
     *     target_listing_id?: int|string|null,
     *     target_ad_title?: string|null,
     *     target_ad_url?: string|null,
     * } $params
     */
    public function execute(User $user, SubscriptionPlan $plan, array $params = []): array
    {
        // 1. Prepare feature snapshot from current plan definitions
        $featuresSnapshot = [];
        foreach ($plan->featureValues()->with('feature')->get() as $fv) {
            if ($fv->feature) {
                $featuresSnapshot[$fv->feature->key] = $fv->value;
            }
        }

        // 2. Free Plan Rule: activates immediately without external payment gateway
        if ($plan->isFree()) {
            if (! empty($params['promotion_package_id']) || ! empty($params['ad_placement_id'])) {
                throw new DomainException('Standard Free plan cannot include paid promotional or advertising upgrades.');
            }

            return DB::transaction(function () use ($user, $plan, $featuresSnapshot): array {
                // Deactivate any currently active subscription
                UserSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('status', SubscriptionStatus::Active)
                    ->update([
                        'status' => SubscriptionStatus::Expired,
                        'cancelled_at' => now(),
                    ]);

                $subscription = UserSubscription::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'company_id' => $user->company_id ?? null,
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active,
                    'price_etb' => 0.00,
                    'currency' => 'ETB',
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($plan->duration_days),
                    'auto_renew' => false,
                    'features_snapshot_json' => $featuresSnapshot,
                    'metadata_json' => [
                        'checkout_type' => 'free_activation',
                        'activated_at' => now()->toIso8601String(),
                    ],
                ]);

                return [
                    'subscription' => $subscription,
                    'is_free' => true,
                    'payment' => null,
                ];
            });
        }

        // 3. Paid Plan Calculation with Optional Upgrades
        $basePrice = number_format((float) $plan->price_etb, 2, '.', '');
        $totalPrice = $basePrice;

        $metadata = [
            'plan_code' => $plan->code,
            'base_price_etb' => $basePrice,
            'add_ons' => [],
        ];

        // 3.1 Optional Promoted Listing Upgrade
        if (! empty($params['promotion_package_id'])) {
            $promoPkg = PromotionTierPackage::query()->findOrFail($params['promotion_package_id']);
            if (! $promoPkg->isPlanEligible($plan->code)) {
                throw new DomainException("Promotion package [{$promoPkg->name_en}] is not eligible for plan [{$plan->name_en}].");
            }

            $promoPrice = number_format((float) $promoPkg->price_etb, 2, '.', '');
            $totalPrice = bcadd($totalPrice, $promoPrice, 2);

            $metadata['add_ons']['promotion'] = [
                'package_id' => $promoPkg->id,
                'tier' => $promoPkg->tier->value,
                'price_etb' => $promoPrice,
                'target_listing_id' => $params['target_listing_id'] ?? null,
            ];
        }

        // 3.2 Optional Multi-Placement Advertising Upgrade
        if (! empty($params['ad_placement_id'])) {
            $adPlacement = AdPlacement::query()->findOrFail($params['ad_placement_id']);
            if (! $adPlacement->isPlanEligible($plan->code)) {
                throw new DomainException("Advertising placement [{$adPlacement->name_en}] is not eligible for plan [{$plan->name_en}].");
            }

            $adPrice = number_format((float) $adPlacement->price_etb, 2, '.', '');
            $totalPrice = bcadd($totalPrice, $adPrice, 2);

            $metadata['add_ons']['advertising'] = [
                'placement_id' => $adPlacement->id,
                'placement' => $adPlacement->placement->value,
                'price_etb' => $adPrice,
                'target_ad_title' => $params['target_ad_title'] ?? null,
                'target_ad_url' => $params['target_ad_url'] ?? null,
            ];
        }

        // 4. Create Pending UserSubscription and Initiate Payment Intent
        return DB::transaction(function () use ($user, $plan, $totalPrice, $featuresSnapshot, $metadata, $params): array {
            $subscription = UserSubscription::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'company_id' => $user->company_id ?? null,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::PendingPayment,
                'price_etb' => $totalPrice,
                'currency' => 'ETB',
                'auto_renew' => false,
                'features_snapshot_json' => $featuresSnapshot,
                'metadata_json' => $metadata,
            ]);

            $paymentMethod = $params['payment_method'] ?? PaymentMethod::Telebirr;
            $paymentResult = $this->initiatePaymentAction->execute($user, [
                'payable' => $subscription,
                'payment_method' => $paymentMethod,
                'idempotency_key' => $params['idempotency_key'] ?? ('SUB-IDEMP-' . $subscription->uuid),
                'custom_amount_etb' => (float) $totalPrice,
                'payment_type' => PaymentType::Subscription,
                'notes' => "ERE Platform Subscription: {$plan->name_en}",
            ]);

            $subscription->payment_id = $paymentResult['payment']->id;
            $subscription->save();

            return [
                'subscription' => $subscription,
                'is_free' => false,
                'payment' => $paymentResult['payment'],
                'provider_response' => $paymentResult['provider_response'] ?? null,
            ];
        });
    }
}

