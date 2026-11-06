<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Promotion\Enums\PromotionTier;
use App\Domains\Promotion\Models\PromotionTierPackage;
use App\Domains\Subscription\Models\SubscriptionPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PromotionPackageAdminController extends Controller
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
        $this->authorizeAdmin($request, 'promotion.manage');

        $packages = PromotionTierPackage::query()
            ->orderBy('ranking_weight')
            ->withCount('activePromotions')
            ->get();

        $plans = SubscriptionPlan::query()->where('is_active', true)->get(['id', 'code', 'name_en']);

        return view('admin.promotions.index', [
            'packages' => $packages,
            'plans' => $plans,
            'tiers' => PromotionTier::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'promotion.manage');

        $validated = $request->validate([
            'tier' => ['required', Rule::enum(PromotionTier::class), 'unique:promotion_tier_packages,tier'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'name_om' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string'],
            'price_etb' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'ranking_weight' => ['required', 'integer', 'min:0'],
            'badge_label' => ['nullable', 'string', 'max:40'],
            'eligible_plan_codes' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        PromotionTierPackage::query()->create([
            'tier' => $validated['tier'],
            'name_en' => $validated['name_en'],
            'name_am' => $validated['name_am'] ?? null,
            'name_om' => $validated['name_om'] ?? null,
            'description_en' => $validated['description_en'] ?? null,
            'price_etb' => $validated['price_etb'],
            'duration_days' => $validated['duration_days'],
            'ranking_weight' => $validated['ranking_weight'],
            'badge_label' => $validated['badge_label'] ?? null,
            'eligible_plan_codes' => $validated['eligible_plan_codes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_public' => $request->boolean('is_public', true),
        ]);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion package [{$validated['name_en']}] created successfully.");
    }

    public function update(Request $request, PromotionTierPackage $package): RedirectResponse
    {
        $this->authorizeAdmin($request, 'promotion.manage');

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'name_om' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string'],
            'price_etb' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'ranking_weight' => ['required', 'integer', 'min:0'],
            'badge_label' => ['nullable', 'string', 'max:40'],
            'eligible_plan_codes' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $package->update([
            'name_en' => $validated['name_en'],
            'name_am' => $validated['name_am'] ?? null,
            'name_om' => $validated['name_om'] ?? null,
            'description_en' => $validated['description_en'] ?? null,
            'price_etb' => $validated['price_etb'],
            'duration_days' => $validated['duration_days'],
            'ranking_weight' => $validated['ranking_weight'],
            'badge_label' => $validated['badge_label'] ?? null,
            'eligible_plan_codes' => $validated['eligible_plan_codes'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Promotion package [{$package->tier->value}] updated successfully.");
    }

    public function toggleStatus(Request $request, PromotionTierPackage $package): RedirectResponse
    {
        $this->authorizeAdmin($request, 'promotion.manage');

        $package->update(['is_active' => ! $package->is_active]);

        return redirect()->route('admin.promotions.index')
            ->with('success', "Package status updated.");
    }
}

