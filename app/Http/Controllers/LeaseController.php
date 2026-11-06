<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Lease\Actions\AcknowledgePropertyInspectionAction;
use App\Domains\Lease\Actions\RecordPropertyInspectionAction;
use App\Domains\Lease\Actions\SignLeaseContractAction;
use App\Domains\Lease\Enums\InspectionType;
use App\Domains\Lease\Models\Lease;
use App\Domains\Lease\Models\PropertyInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LeaseController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $tenantLeases = Lease::query()
            ->where('tenant_id', $user->id)
            ->with(['property', 'projectUnit', 'landlord'])
            ->latest('start_date')
            ->get();

        $landlordLeases = Lease::query()
            ->where('landlord_id', $user->id)
            ->with(['property', 'projectUnit', 'tenant'])
            ->latest('start_date')
            ->get();

        return view('tenant.leases.index', [
            'tenantLeases' => $tenantLeases,
            'landlordLeases' => $landlordLeases,
        ]);
    }

    public function show(string $uuid, Request $request): View
    {
        $user = $request->user();
        /** @var Lease $lease */
        $lease = Lease::query()
            ->where('uuid', $uuid)
            ->with([
                'property',
                'projectUnit',
                'tenant',
                'landlord',
                'originatingApplication',
                'signedContractDocument',
                'inspections.inspector',
                'rentSchedules',
            ])
            ->firstOrFail();

        $isTenant = ($user->id === $lease->tenant_id);
        $isLandlord = ($user->id === $lease->landlord_id || ($lease->company_id && $user->company_id === $lease->company_id));
        $isStaff = $user->hasAnyRole(['property_admin', 'platform_admin', 'super_admin']);

        if (! $isTenant && ! $isLandlord && ! $isStaff) {
            abort(403, 'Unauthorized access to lease contract.');
        }

        return view('tenant.leases.show', [
            'lease' => $lease,
            'isTenant' => $isTenant,
            'isLandlord' => $isLandlord,
            'isStaff' => $isStaff,
        ]);
    }

    public function sign(
        string $uuid,
        Request $request,
        SignLeaseContractAction $signAction
    ): RedirectResponse {
        $lease = Lease::query()->where('uuid', $uuid)->firstOrFail();

        $signAction->execute($request->user(), $lease, $request->ip());

        return redirect()->route('leases.show', $lease->uuid)
            ->with('status', 'Lease contract successfully signed!');
    }

    public function storeInspection(
        string $uuid,
        Request $request,
        RecordPropertyInspectionAction $recordAction
    ): RedirectResponse {
        $lease = Lease::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'inspection_type' => 'required|string|in:move_in,move_out',
            'inspection_date' => 'required|date',
            'electricity_meter_reading' => 'nullable|numeric|min:0',
            'water_meter_reading' => 'nullable|numeric|min:0',
            'condition_checklist' => 'nullable|array',
            'photographs' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        $stage = InspectionType::from($validated['inspection_type']);
        $recordAction->execute($request->user(), $lease, $stage, $validated);

        return redirect()->route('leases.show', $lease->uuid)
            ->with('status', "{$stage->label()} recorded successfully.");
    }

    public function acknowledgeInspection(
        string $inspectionUuid,
        Request $request,
        AcknowledgePropertyInspectionAction $ackAction
    ): RedirectResponse {
        $inspection = PropertyInspection::query()->where('uuid', $inspectionUuid)->firstOrFail();

        $ackAction->execute($request->user(), $inspection, $request->ip());

        return redirect()->route('leases.show', $inspection->lease->uuid)
            ->with('status', 'Property inspection acknowledged successfully.');
    }
}
