<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Host\Models\AccommodationUnit;
use App\Domains\Maintenance\Models\MaintenanceRequest;
use App\Domains\Portfolio\Actions\CalculatePropertyManagementDashboardAction;
use App\Domains\Portfolio\Actions\CreatePropertyManagementMandateAction;
use App\Domains\Portfolio\Models\PropertyManagementMandate;
use App\Domains\Portfolio\Services\MultiTenantOrganizationGuard;
use App\Domains\Property\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PortfolioController extends Controller
{
    public function dashboard(
        Request $request,
        CalculatePropertyManagementDashboardAction $dashboardAction
    ): View {
        $user = $request->user();

        $filters = [
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date'),
            'branch_id' => $request->filled('branch_id') ? (int) $request->query('branch_id') : null,
        ];

        $kpis = $dashboardAction->execute($user, $filters);

        $companyId = MultiTenantOrganizationGuard::resolveActorCompanyId($user);
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');
        $isHeadOffice = $companyId ? MultiTenantOrganizationGuard::isHeadOfficeAdmin($user, $companyId) : false;

        $branches = collect();
        if ($companyId) {
            $branchesQuery = CompanyBranch::query()->where('company_id', $companyId);
            if (! $isGlobalAdmin && ! $isHeadOffice) {
                $userBranchId = MultiTenantOrganizationGuard::resolveActorBranchId($user);
                $branchesQuery->where('id', $userBranchId);
            }
            $branches = $branchesQuery->get();
        }

        $recentTicketsQuery = MaintenanceRequest::query()
            ->with(['property', 'reporter'])
            ->orderByDesc('created_at')
            ->limit(5);

        $recentMandatesQuery = PropertyManagementMandate::query()
            ->with(['property', 'company', 'branch', 'owner'])
            ->orderByDesc('created_at')
            ->limit(5);

        if (! $isGlobalAdmin) {
            if ($companyId) {
                MultiTenantOrganizationGuard::applyScope($recentTicketsQuery, $user);
                MultiTenantOrganizationGuard::applyScope($recentMandatesQuery, $user);
            } else {
                $recentTicketsQuery->whereHas('property', fn($pq) => $pq->where('owner_id', $user->id));
                $recentMandatesQuery->where('owner_user_id', $user->id);
            }
        }

        $recentTickets = $recentTicketsQuery->get();
        $recentMandates = $recentMandatesQuery->get();

        return view('portfolio.dashboard', [
            'kpis' => $kpis,
            'branches' => $branches,
            'recentTickets' => $recentTickets,
            'recentMandates' => $recentMandates,
            'filters' => $filters,
        ]);
    }

    public function matrix(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        $companyId = MultiTenantOrganizationGuard::resolveActorCompanyId($user);

        $propertiesQuery = Property::query()
            ->with([
                'projectUnits',
                'accommodationUnits',
                'owner',
                'leases' => fn($q) => $q->where('status', 'active')
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today)
                    ->with('tenant'),
            ]);

        if (! $isGlobalAdmin) {
            if ($companyId) {
                $isHeadOffice = MultiTenantOrganizationGuard::isHeadOfficeAdmin($user, $companyId);
                $branchId = MultiTenantOrganizationGuard::resolveActorBranchId($user);

                $propertiesQuery->where(function ($q) use ($companyId, $isHeadOffice, $branchId) {
                    $q->where('company_id', $companyId)
                        ->orWhereHas('mandates', function ($mq) use ($companyId, $isHeadOffice, $branchId) {
                            $mq->where('company_id', $companyId)
                                ->where('status', 'active');
                            if (! $isHeadOffice && $branchId) {
                                $mq->where('branch_id', $branchId);
                            } elseif (! $isHeadOffice && ! $branchId) {
                                $mq->whereRaw('1 = 0');
                            }
                        });
                });
            } else {
                // Strict fail-closed: user without company affiliation cannot view cross-tenant matrix
                $propertiesQuery->whereRaw('1 = 0');
            }
        }

        $properties = $propertiesQuery->paginate(10);

        return view('portfolio.matrix', [
            'properties' => $properties,
            'today' => $today,
        ]);
    }

    public function mandates(Request $request): View
    {
        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');
        $companyId = MultiTenantOrganizationGuard::resolveActorCompanyId($user);

        $query = PropertyManagementMandate::query()
            ->with(['property.owner', 'company', 'branch', 'assignedManager'])
            ->orderByDesc('created_at');

        if (! $isGlobalAdmin) {
            MultiTenantOrganizationGuard::applyScope($query, $user);
        }

        $mandates = $query->paginate(15);

        if ($isGlobalAdmin) {
            $companies = Company::query()->where('status', 'active')->get();
            $properties = Property::query()->limit(20)->get();
        } else {
            $companies = $companyId
                ? Company::query()->where('id', $companyId)->get()
                : collect();

            $properties = Property::query()
                ->where(function ($q) use ($companyId, $user) {
                    $q->where('owner_id', $user->id)
                        ->orWhere('owner_user_id', $user->id);
                    if ($companyId) {
                        $q->orWhere('company_id', $companyId);
                    }
                })
                ->limit(20)
                ->get();
        }

        return view('portfolio.mandates', [
            'mandates' => $mandates,
            'companies' => $companies,
            'properties' => $properties,
        ]);
    }

    public function storeMandate(
        Request $request,
        CreatePropertyManagementMandateAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'nullable|exists:company_branches,id',
            'assigned_manager_user_id' => 'nullable|exists:users,id',
            'management_fee_pct' => 'required|numeric|between:0,100',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'terms' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        if (! $isGlobalAdmin) {
            $property = Property::query()->findOrFail($validated['property_id']);
            $isOwner = ($property->owner_id === $user->id) || ($property->owner_user_id === $user->id);
            $mandateResource = [
                'company_id' => (int) $validated['company_id'],
                'branch_id' => $validated['branch_id'] ?? null,
            ];
            $isCompanyManager = MultiTenantOrganizationGuard::canAccess($user, $mandateResource);

            if (! $isOwner && ! $isCompanyManager) {
                abort(403, 'You do not have authorization to create a management mandate for this property.');
            }
        }

        $mandate = $action->execute($request->user(), $validated);

        return redirect()->route('portfolio.mandates')
            ->with('status', "Property management mandate {$mandate->mandate_code} created successfully.");
    }
}
