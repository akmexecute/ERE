<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Advertising\Enums\AdPlacementType;
use App\Domains\Advertising\Enums\CampaignStatus;
use App\Domains\Advertising\Models\AdCampaign;
use App\Domains\Advertising\Models\AdPlacement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdvertisingAdminController extends Controller
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
        $this->authorizeAdmin($request, 'advertising.manage');

        $placements = AdPlacement::query()
            ->orderBy('price_etb')
            ->withCount(['campaigns as active_campaigns_count' => function ($q): void {
                $q->where('status', CampaignStatus::Active);
            }])
            ->get();

        $campaigns = AdCampaign::query()
            ->with(['user', 'placement'])
            ->latest()
            ->paginate(20);

        return view('admin.advertising.index', [
            'placements' => $placements,
            'campaigns' => $campaigns,
            'placementTypes' => AdPlacementType::cases(),
            'campaignStatuses' => CampaignStatus::cases(),
        ]);
    }

    public function updatePlacement(Request $request, AdPlacement $placement): RedirectResponse
    {
        $this->authorizeAdmin($request, 'advertising.manage');

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:120'],
            'price_etb' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'impression_limit' => ['nullable', 'integer', 'min:0'],
            'click_limit' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'eligible_plan_codes' => ['nullable', 'array'],
        ]);

        $placement->update([
            'name_en' => $validated['name_en'],
            'price_etb' => $validated['price_etb'],
            'duration_days' => $validated['duration_days'],
            'impression_limit' => $validated['impression_limit'] ?? null,
            'click_limit' => $validated['click_limit'] ?? null,
            'eligible_plan_codes' => $validated['eligible_plan_codes'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('admin.advertising.index')
            ->with('success', "Ad placement [{$placement->placement->value}] updated successfully.");
    }

    public function approveCampaign(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $this->authorizeAdmin($request, 'advertising.approve');

        $campaign->update([
            'status' => CampaignStatus::Active,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'starts_at' => $campaign->starts_at ?? now(),
            'expires_at' => $campaign->expires_at ?? now()->addDays($campaign->placement?->duration_days ?? 30),
        ]);

        return redirect()->route('admin.advertising.index')
            ->with('success', "Ad campaign #{$campaign->id} approved and activated.");
    }

    public function rejectCampaign(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $this->authorizeAdmin($request, 'advertising.approve');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $campaign->update([
            'status' => CampaignStatus::Rejected,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()->route('admin.advertising.index')
            ->with('warning', "Ad campaign #{$campaign->id} rejected.");
    }
}

