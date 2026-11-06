<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Tenant\Actions\CreateOrUpdateTenantProfileAction;
use App\Domains\Tenant\Models\TenantProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        /** @var TenantProfile|null $profile */
        $profile = TenantProfile::query()
            ->where('user_id', $user->id)
            ->with(['rentalHistories', 'applications.property', 'leases.property'])
            ->first();

        return view('tenant.profile', [
            'user' => $user,
            'profile' => $profile,
        ]);
    }

    public function update(
        Request $request,
        CreateOrUpdateTenantProfileAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'occupation' => 'nullable|string|max:120',
            'employer_name' => 'nullable|string|max:160',
            'monthly_income_etb' => 'nullable|numeric|min:0',
            'emergency_contact_name' => 'nullable|string|max:160',
            'emergency_contact_phone' => 'nullable|string|max:32',
            'emergency_contact_relationship' => 'nullable|string|max:60',
        ]);

        $action->execute($request->user(), $validated);

        return redirect()->route('tenant.profile')
            ->with('status', 'Tenant profile updated successfully.');
    }
}
