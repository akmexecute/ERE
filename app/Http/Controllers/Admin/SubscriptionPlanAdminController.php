<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Subscription\Enums\FeatureValueType;
use App\Domains\Subscription\Enums\SubscriberType;
use App\Domains\Subscription\Models\SubscriptionFeature;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Domains\Subscription\Models\SubscriptionPlanFeatureValue;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SubscriptionPlanAdminController extends Controller
{
    private function authorizeAdmin(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super_admin') && ! $user->hasPermission($permission))) {
            abort(403, "Unauthorized access: required permission [{$permission}].");
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request, 'subscription.view');

        $plans = SubscriptionPlan::query()
            ->orderBy('sort_order')
            ->with(['featureValues.feature'])
            ->withCount(['subscriptions as active_subscribers_count' => function ($q): void {
                $q->where('status', 'active')->where('expires_at', '>', now());
            }])
            ->get();

        $features = SubscriptionFeature::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('admin.subscriptions.plans.index', [
            'plans' => $plans,
            'features' => $features,
            'subscriberTypes' => SubscriberType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'subscription.manage');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:subscription_plans,code'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'name_om' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string'],
            'subscriber_type' => ['required', Rule::enum(SubscriberType::class)],
            'price_etb' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'feature_values' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $plan = SubscriptionPlan::query()->create([
                'code' => $validated['code'],
                'name_en' => $validated['name_en'],
                'name_am' => $validated['name_am'] ?? null,
                'name_om' => $validated['name_om'] ?? null,
                'description_en' => $validated['description_en'] ?? null,
                'subscriber_type' => $validated['subscriber_type'],
                'price_etb' => $validated['price_etb'],
                'currency' => 'ETB',
                'duration_days' => $validated['duration_days'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active', true),
                'is_public' => $request->boolean('is_public', true),
            ]);

            if (! empty($validated['feature_values'])) {
                foreach ($validated['feature_values'] as $featureId => $val) {
                    SubscriptionPlanFeatureValue::query()->create([
                        'plan_id' => $plan->id,
                        'feature_id' => $featureId,
                        'value' => (string) $val,
                    ]);
                }
            }
        });

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', "Subscription plan [{$validated['code']}] created successfully.");
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $this->authorizeAdmin($request, 'subscription.manage');

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'name_om' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string'],
            'subscriber_type' => ['required', Rule::enum(SubscriberType::class)],
            'price_etb' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'feature_values' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($request, $plan, $validated): void {
            $plan->update([
                'name_en' => $validated['name_en'],
                'name_am' => $validated['name_am'] ?? null,
                'name_om' => $validated['name_om'] ?? null,
                'description_en' => $validated['description_en'] ?? null,
                'subscriber_type' => $validated['subscriber_type'],
                'price_etb' => $validated['price_etb'],
                'duration_days' => $validated['duration_days'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active'),
                'is_public' => $request->boolean('is_public'),
            ]);

            if (isset($validated['feature_values'])) {
                foreach ($validated['feature_values'] as $featureId => $val) {
                    SubscriptionPlanFeatureValue::query()->updateOrCreate(
                        ['plan_id' => $plan->id, 'feature_id' => $featureId],
                        ['value' => (string) $val]
                    );
                }
            }
        });

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', "Plan [{$plan->code}] updated successfully.");
    }

    public function toggleStatus(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $this->authorizeAdmin($request, 'subscription.manage');

        $plan->update(['is_active' => ! $plan->is_active]);

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', "Plan [{$plan->code}] status updated.");
    }

    public function destroy(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $this->authorizeAdmin($request, 'subscription.manage');

        $plan->delete();

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', "Plan [{$plan->code}] archived.");
    }
}

