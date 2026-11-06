<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Maintenance\Actions\CreateMaintenanceRequestAction;
use App\Domains\Maintenance\Actions\TransitionMaintenanceTicketAction;
use App\Domains\Maintenance\Enums\MaintenanceCategory;
use App\Domains\Maintenance\Enums\MaintenancePriority;
use App\Domains\Maintenance\Enums\MaintenanceStatus;
use App\Domains\Maintenance\Models\MaintenanceRequest;
use App\Domains\Portfolio\Services\MultiTenantOrganizationGuard;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        $query = MaintenanceRequest::query()
            ->with(['property', 'reporter', 'assignedTechnician'])
            ->orderByDesc('created_at');

        if (! $isGlobalAdmin) {
            $actorCompanyId = MultiTenantOrganizationGuard::resolveActorCompanyId($user);

            if ($actorCompanyId) {
                MultiTenantOrganizationGuard::applyScope($query, $user);
            } else {
                // Regular tenant or property owner
                $query->where(function ($q) use ($user) {
                    $q->where('reported_by_user_id', $user->id)
                        ->orWhereHas('property', fn($pq) => $pq->where('owner_id', $user->id)->orWhere('owner_user_id', $user->id))
                        ->orWhere('assigned_to_user_id', $user->id);
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        $tickets = $query->paginate(15)->withQueryString();

        return view('maintenance.index', [
            'tickets' => $tickets,
            'categories' => MaintenanceCategory::cases(),
            'statuses' => MaintenanceStatus::cases(),
            'priorities' => MaintenancePriority::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        if ($isGlobalAdmin) {
            $properties = Property::query()->limit(20)->get();
        } else {
            // Load properties accessible to user (properties owned, leased, booked, or managed)
            $properties = Property::query()
                ->where(function ($q) use ($user) {
                    $q->where('owner_id', $user->id)
                        ->orWhere('owner_user_id', $user->id)
                        ->orWhereHas('leases', fn($lq) => $lq->where('tenant_id', $user->id)->where('status', 'active'))
                        ->orWhereHas('bookings', fn($bq) => $bq->where('guest_user_id', $user->id)->whereIn('status', ['confirmed', 'checked_in', 'active']));

                    $companyId = MultiTenantOrganizationGuard::resolveActorCompanyId($user);
                    if ($companyId) {
                        $q->orWhere('company_id', $companyId);
                    }
                })
                ->get();
        }

        return view('maintenance.create', [
            'properties' => $properties,
            'selectedPropertyId' => $request->query('property_id'),
            'categories' => MaintenanceCategory::cases(),
            'priorities' => MaintenancePriority::cases(),
        ]);
    }

    public function store(
        Request $request,
        CreateMaintenanceRequestAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'project_unit_id' => 'nullable|exists:project_units,id',
            'accommodation_unit_id' => 'nullable|exists:accommodation_units,id',
            'lease_id' => 'nullable|exists:leases,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'category' => 'required|string|in:plumbing,electricity,internet,appliance,structural,cleaning,other',
            'priority' => 'nullable|string|in:low,normal,high,emergency',
            'title' => 'required|string|max:180',
            'description' => 'required|string|max:2000',
            'estimated_cost_etb' => 'nullable|numeric|min:0',
            'billable_party' => 'nullable|string|in:owner,tenant,company,warranty',
        ]);

        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        if (! $isGlobalAdmin) {
            $property = Property::query()->findOrFail($validated['property_id']);
            $isOwner = ($property->owner_id === $user->id) || ($property->owner_user_id === $user->id);
            $hasActiveLease = $property->leases()->where('tenant_id', $user->id)->where('status', 'active')->exists();
            $hasActiveBooking = $property->bookings()->where('guest_user_id', $user->id)->whereIn('status', ['confirmed', 'checked_in', 'active'])->exists();
            $isCompanyManager = $property->company_id && MultiTenantOrganizationGuard::canAccess($user, $property);

            if (! $isOwner && ! $hasActiveLease && ! $hasActiveBooking && ! $isCompanyManager) {
                abort(403, 'You do not have authorization to submit a maintenance ticket for this property.');
            }
        }

        $ticket = $action->execute($request->user(), $validated);

        return redirect()->route('maintenance.show', $ticket->uuid)
            ->with('status', "Maintenance ticket {$ticket->ticket_code} submitted successfully.");
    }

    public function show(string $uuid, Request $request): View
    {
        /** @var MaintenanceRequest $ticket */
        $ticket = MaintenanceRequest::query()
            ->where('uuid', $uuid)
            ->with([
                'property.owner',
                'projectUnit',
                'accommodationUnit',
                'reporter',
                'assignedTechnician',
                'company',
                'branch',
                'histories.actor',
                'attachments',
            ])
            ->firstOrFail();

        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        $isAuthorized = $isGlobalAdmin
            || $ticket->reported_by_user_id === $user->id
            || $ticket->assigned_to_user_id === $user->id
            || ($ticket->property && ($ticket->property->owner_id === $user->id || $ticket->property->owner_user_id === $user->id))
            || ($ticket->company_id && MultiTenantOrganizationGuard::canAccess($user, $ticket));

        if (! $isAuthorized) {
            abort(403, 'You are not authorized to view this maintenance ticket.');
        }

        // Staff technicians for assignment dropdown (if user is manager/admin)
        $technicians = User::query()
            ->whereHas('roles', fn($q) => $q->whereIn('slug', ['property_admin', 'platform_admin', 'super_admin']))
            ->limit(20)
            ->get();

        return view('maintenance.show', [
            'ticket' => $ticket,
            'technicians' => $technicians,
            'statuses' => MaintenanceStatus::cases(),
        ]);
    }

    public function transition(
        string $uuid,
        Request $request,
        TransitionMaintenanceTicketAction $action
    ): RedirectResponse {
        /** @var MaintenanceRequest $ticket */
        $ticket = MaintenanceRequest::query()->where('uuid', $uuid)->firstOrFail();
        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        $isAuthorized = $isGlobalAdmin
            || $ticket->reported_by_user_id === $user->id
            || $ticket->assigned_to_user_id === $user->id
            || ($ticket->property && ($ticket->property->owner_id === $user->id || $ticket->property->owner_user_id === $user->id))
            || ($ticket->company_id && MultiTenantOrganizationGuard::canAccess($user, $ticket));

        if (! $isAuthorized) {
            abort(403, 'You are not authorized to update this maintenance ticket.');
        }

        $validated = $request->validate([
            'target_status' => 'required|string|in:open,assigned,in_progress,waiting,completed,closed',
            'notes' => 'nullable|string|max:1000',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'contractor_name' => 'nullable|string|max:140',
            'contractor_phone' => 'nullable|string|max:32',
            'estimated_cost_etb' => 'nullable|numeric|min:0',
            'actual_cost_etb' => 'nullable|numeric|min:0',
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $action->execute(
            actor: $request->user(),
            ticket: $ticket,
            targetStatus: $validated['target_status'],
            payload: $validated
        );

        return redirect()->route('maintenance.show', $ticket->uuid)
            ->with('status', "Ticket status updated to {$validated['target_status']}.");
    }
}
