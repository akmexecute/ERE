<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Advertising\Models\AdPlacement;
use App\Domains\Listing\Models\Listing;
use App\Domains\Promotion\Models\PromotionTierPackage;
use App\Domains\Subscription\Actions\InitiateSubscriptionCheckoutAction;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\SubscriptionFeature;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly InitiateSubscriptionCheckoutAction $checkoutAction
    ) {}

    /**
     * Display public subscription plans, feature comparison, and upgrade packages.
     */
    public function index(Request $request): View
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->with(['featureValues.feature'])
            ->get();

        $features = SubscriptionFeature::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $promotionPackages = PromotionTierPackage::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('ranking_weight')
            ->get();

        $adPlacements = AdPlacement::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('price_etb')
            ->get();

        $activeSubscription = null;
        $userListings = collect();

        if ($user = $request->user()) {
            $activeSubscription = UserSubscription::query()
                ->where('user_id', $user->id)
                ->where('status', SubscriptionStatus::Active)
                ->where('expires_at', '>', now())
                ->with('plan')
                ->latest()
                ->first();

            $userListings = Listing::query()
                ->where('user_id', $user->id)
                ->get(['id', 'title_en']);
        }

        return view('subscriptions.index', [
            'plans' => $plans,
            'features' => $features,
            'promotionPackages' => $promotionPackages,
            'adPlacements' => $adPlacements,
            'activeSubscription' => $activeSubscription,
            'userListings' => $userListings,
        ]);
    }

    /**
     * Initiate checkout for a subscription plan and optional add-ons.
     */
    public function checkout(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated', 'message' => 'Please sign in to subscribe.'], 401);
            }

            return redirect()->guest(route('login'))->with('info', 'Please sign in or create an account to activate your subscription.');
        }

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'payment_method' => ['nullable', 'string'],
            'promotion_package_id' => ['nullable', 'exists:promotion_tier_packages,id'],
            'ad_placement_id' => ['nullable', 'exists:ad_placements,id'],
            'target_listing_id' => ['nullable', 'integer'],
            'target_ad_title' => ['nullable', 'string', 'max:120'],
            'target_ad_url' => ['nullable', 'url', 'max:255'],
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($validated['plan_id']);

        $result = $this->checkoutAction->execute($user, $plan, $validated);

        if ($result['is_free']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'is_free' => true,
                    'message' => 'Your Standard Free plan has been successfully activated.',
                    'subscription' => $result['subscription'],
                ]);
            }

            return redirect()->route('dashboard')->with('success', 'Your Standard Free plan has been activated! You can now publish listings.');
        }

        $checkoutUrl = route('payments.checkout', [
            'payable_type' => UserSubscription::class,
            'payable_id' => $result['subscription']->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_free' => false,
                'subscription' => $result['subscription'],
                'payment' => $result['payment'],
                'checkout_url' => $checkoutUrl,
            ]);
        }

        return redirect($checkoutUrl);
    }
}

