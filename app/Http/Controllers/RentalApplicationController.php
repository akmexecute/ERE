<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Property\Models\Property;
use App\Domains\Tenant\Actions\ConvertApplicationToLeaseAction;
use App\Domains\Tenant\Actions\ReviewRentalApplicationAction;
use App\Domains\Tenant\Actions\SubmitRentalApplicationAction;
use App\Domains\Tenant\Models\RentalApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RentalApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $sentApplications = RentalApplication::query()
            ->where('tenant_id', $user->id)
            ->with(['property', 'listing', 'projectUnit', 'landlord'])
            ->latest()
            ->get();

        $receivedApplications = RentalApplication::query()
            ->where('landlord_id', $user->id)
            ->with(['property', 'listing', 'projectUnit', 'tenant', 'tenantProfile'])
            ->latest()
            ->get();

        return view('tenant.applications.index', [
            'sentApplications' => $sentApplications,
            'receivedApplications' => $receivedApplications,
        ]);
    }

    public function create(Request $request): View
    {
        $propertyId = $request->query('property_id');
        $property = $propertyId
            ? Property::query()->find($propertyId)
            : Property::query()->where('status', 'available')->first();

        if (! $property) {
            $property = Property::query()->first();
        }

        $availableProperties = Property::query()
            ->latest()
            ->take(20)
            ->get();

        return view('tenant.applications.create', [
            'property' => $property,
            'availableProperties' => $availableProperties,
            'listingId' => $request->query('listing_id'),
            'unitId' => $request->query('unit_id'),
        ]);
    }

    public function store(
        Request $request,
        SubmitRentalApplicationAction $submitAction
    ): RedirectResponse {
        $validated = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'listing_id' => 'nullable|integer|exists:listings,id',
            'project_unit_id' => 'nullable|integer|exists:project_units,id',
            'desired_move_in_date' => 'required|date|after_or_equal:today',
            'lease_duration_months' => 'required|integer|min:1|max:60',
            'proposed_monthly_rent_etb' => 'required|numeric|min:1',
            'payment_frequency' => 'required|string|in:monthly,quarterly,semi_annual,annual',
            'occupant_count' => 'required|integer|min:1|max:20',
            'applicant_message' => 'nullable|string|max:1000',
        ]);

        $application = $submitAction->execute($request->user(), $validated);

        return redirect()->route('rental-applications.show', $application->uuid)
            ->with('status', "Rental application #{$application->application_code} submitted successfully!");
    }

    public function show(string $uuid, Request $request): View
    {
        $user = $request->user();
        /** @var RentalApplication $application */
        $application = RentalApplication::query()
            ->where('uuid', $uuid)
            ->with(['property', 'listing', 'projectUnit', 'tenant', 'tenantProfile', 'landlord', 'convertedLease'])
            ->firstOrFail();

        // Access check
        $isParty = ($user->id === $application->tenant_id || $user->id === $application->landlord_id);
        $isCompany = ($application->company_id && $user->company_id === $application->company_id);
        $isStaff = $user->hasAnyRole(['property_admin', 'platform_admin', 'super_admin']);

        if (! $isParty && ! $isCompany && ! $isStaff) {
            abort(403, 'Unauthorized access to rental application.');
        }

        return view('tenant.applications.show', [
            'application' => $application,
            'isLandlordOrReviewer' => ($user->id === $application->landlord_id || $isCompany || $isStaff),
        ]);
    }

    public function review(
        string $uuid,
        Request $request,
        ReviewRentalApplicationAction $reviewAction
    ): RedirectResponse {
        $application = RentalApplication::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'decision' => 'required|string|in:approve,reject,document_review,withdraw',
            'notes' => 'nullable|string|max:500',
            'rejection_reason' => 'required_if:decision,reject|nullable|string|max:300',
        ]);

        $reviewAction->execute(
            $request->user(),
            $application,
            $validated['decision'],
            $validated['notes'] ?? null,
            $validated['rejection_reason'] ?? null
        );

        return redirect()->route('rental-applications.show', $application->uuid)
            ->with('status', "Application status updated to '{$application->fresh()->status->label()}'.");
    }

    public function convertToLease(
        string $uuid,
        Request $request,
        ConvertApplicationToLeaseAction $convertAction
    ): RedirectResponse {
        $application = RentalApplication::query()->where('uuid', $uuid)->firstOrFail();

        $lease = $convertAction->execute($request->user(), $application);

        return redirect()->route('leases.show', $lease->uuid)
            ->with('status', "Lease contract #{$lease->lease_code} generated from application #{$application->application_code}!");
    }
}
