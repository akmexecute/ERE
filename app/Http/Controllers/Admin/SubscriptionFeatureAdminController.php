<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Subscription\Enums\FeatureValueType;
use App\Domains\Subscription\Models\SubscriptionFeature;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SubscriptionFeatureAdminController extends Controller
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

        $features = SubscriptionFeature::query()
            ->orderBy('sort_order')
            ->get();

        return view('admin.subscriptions.features.index', [
            'features' => $features,
            'valueTypes' => FeatureValueType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request, 'subscription.manage');

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:64', 'unique:subscription_features,key'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'name_om' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string'],
            'value_type' => ['required', Rule::enum(FeatureValueType::class)],
            'allowed_options_json' => ['nullable', 'array'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        SubscriptionFeature::query()->create([
            'key' => $validated['key'],
            'name_en' => $validated['name_en'],
            'name_am' => $validated['name_am'] ?? null,
            'name_om' => $validated['name_om'] ?? null,
            'description_en' => $validated['description_en'] ?? null,
            'value_type' => $validated['value_type'],
            'allowed_options_json' => $validated['allowed_options_json'] ?? null,
            'default_value' => $validated['default_value'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.subscriptions.features.index')
            ->with('success', "Subscription feature [{$validated['key']}] created successfully.");
    }

    public function update(Request $request, SubscriptionFeature $feature): RedirectResponse
    {
        $this->authorizeAdmin($request, 'subscription.manage');

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'name_om' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string'],
            'value_type' => ['required', Rule::enum(FeatureValueType::class)],
            'default_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $feature->update([
            'name_en' => $validated['name_en'],
            'name_am' => $validated['name_am'] ?? null,
            'name_om' => $validated['name_om'] ?? null,
            'description_en' => $validated['description_en'] ?? null,
            'value_type' => $validated['value_type'],
            'default_value' => $validated['default_value'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.subscriptions.features.index')
            ->with('success', "Feature [{$feature->key}] updated successfully.");
    }

    public function toggleStatus(Request $request, SubscriptionFeature $feature): RedirectResponse
    {
        $this->authorizeAdmin($request, 'subscription.manage');

        $feature->update(['is_active' => ! $feature->is_active]);

        return redirect()->route('admin.subscriptions.features.index')
            ->with('success', "Feature [{$feature->key}] status set to " . ($feature->is_active ? 'active' : 'inactive') . '.');
    }

    public function destroy(Request $request, SubscriptionFeature $feature): RedirectResponse
    {
        $this->authorizeAdmin($request, 'subscription.manage');

        $feature->delete();

        return redirect()->route('admin.subscriptions.features.index')
            ->with('success', "Feature [{$feature->key}] archived.");
    }
}

