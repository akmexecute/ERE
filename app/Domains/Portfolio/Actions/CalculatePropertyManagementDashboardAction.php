<?php

declare(strict_types=1);

namespace App\Domains\Portfolio\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Dispute\Models\Dispute;
use App\Domains\Stay\Models\AccommodationUnit;
use App\Domains\Lease\Models\Lease;
use App\Domains\Maintenance\Enums\MaintenanceStatus;
use App\Domains\Maintenance\Models\MaintenanceRequest;
use App\Domains\Portfolio\Enums\PropertyManagementMandateStatus;
use App\Domains\Portfolio\Models\PropertyManagementMandate;
use App\Domains\Portfolio\Services\MultiTenantOrganizationGuard;
use App\Domains\Property\Models\Property;
use App\Domains\RentalFinancial\Models\RentalPayment;
use App\Domains\RentalFinancial\Models\RentSchedule;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class CalculatePropertyManagementDashboardAction
{
    /**
     * Calculates the 11 real database-backed business KPIs defined in SRS Section 196.
     * Applies strict organizational/branch scoping for the authenticated user.
     *
     * @param array{
     *     company_id?: int|null,
     *     branch_id?: int|null,
     *     from_date?: string|null,
     *     to_date?: string|null,
     * } $filters
     * @return array<string, mixed>
     */
    public function execute(User $actor, array $filters = []): array
    {
        $today = Carbon::today()->toDateString();
        $isGlobalAdmin = $actor->hasRole('super_admin') || $actor->hasRole('platform_admin');

        // Resolve company scope (prevent user manipulation)
        $companyId = $isGlobalAdmin
            ? ($filters['company_id'] ?? null)
            : MultiTenantOrganizationGuard::resolveActorCompanyId($actor);

        $isHeadOffice = $companyId ? MultiTenantOrganizationGuard::isHeadOfficeAdmin($actor, $companyId) : false;

        $branchId = ($isGlobalAdmin || $isHeadOffice)
            ? ($filters['branch_id'] ?? null)
            : MultiTenantOrganizationGuard::resolveActorBranchId($actor);

        // 1. Managed Properties: Properties under active mandates or owned/managed by the company
        $propertyQuery = Property::query();
        if ($companyId) {
            if (! $isGlobalAdmin && ! $isHeadOffice && ! $branchId) {
                // Non-head-office user with null branch is default-denied to prevent accidental company-wide leak
                return $this->emptyKpis();
            }

            $propertyQuery->where(function ($q) use ($companyId, $branchId) {
                if ($branchId) {
                    $q->whereHas('mandates', function ($mq) use ($companyId, $branchId) {
                        $mq->where('company_id', $companyId)
                            ->where('status', PropertyManagementMandateStatus::Active->value)
                            ->where('branch_id', $branchId);
                    });
                } else {
                    $q->where('company_id', $companyId)
                        ->orWhereHas('mandates', function ($mq) use ($companyId) {
                            $mq->where('company_id', $companyId)
                                ->where('status', PropertyManagementMandateStatus::Active->value);
                        });
                }
            });
        } elseif (! $isGlobalAdmin) {
            // Non-admin actor without company membership is strictly scoped to properties they personally own
            $propertyQuery->where('owner_id', $actor->id);
        }
        $managedPropertyIds = $propertyQuery->pluck('id')->all();
        $propertiesManagedCount = count($managedPropertyIds);

        if ($propertiesManagedCount === 0 && ! $isGlobalAdmin) {
            return $this->emptyKpis();
        }

        // 2. Total Units: Authoritative inventory units (project_units + accommodation_units + standalone properties)
        $projectUnitsCount = ProjectUnit::query()
            ->whereIn('project_id', function ($q) use ($managedPropertyIds) {
                $q->select('id')->from('development_projects')->whereIn('id', function ($pq) use ($managedPropertyIds) {
                    $pq->select('project_id')->from('project_units')->whereIn('project_id', $managedPropertyIds);
                });
            })
            ->count();

        // Count accommodation units for managed properties
        $accommodationUnitsCount = AccommodationUnit::query()
            ->when(! empty($managedPropertyIds), fn($q) => $q->whereIn('property_id', $managedPropertyIds))
            ->count();

        // Standalone properties without child units count as 1 unit each
        $standaloneUnitsCount = empty($managedPropertyIds) ? 0 : Property::query()
            ->whereIn('id', $managedPropertyIds)
            ->whereDoesntHave('accommodationUnits')
            ->count();

        $totalUnits = max($propertiesManagedCount, $projectUnitsCount + $accommodationUnitsCount + $standaloneUnitsCount);

        // 3. Occupied Units: Units currently under an active lease OR an active short-stay booking
        $activeLeaseQuery = Lease::query()
            ->where('status', 'active')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->when(! empty($managedPropertyIds), fn($q) => $q->whereIn('property_id', $managedPropertyIds));

        $activeLeaseCount = (clone $activeLeaseQuery)->count();

        $activeStayQuery = Booking::query()
            ->whereIn('status', ['confirmed', 'checked_in', 'active'])
            ->where('check_in_date', '<=', $today)
            ->where('check_out_date', '>=', $today)
            ->when(! empty($managedPropertyIds), fn($q) => $q->whereIn('property_id', $managedPropertyIds));

        $activeStayCount = (clone $activeStayQuery)->count();

        $occupiedUnits = min($totalUnits, $activeLeaseCount + $activeStayCount);
        $vacantUnits = max(0, $totalUnits - $occupiedUnits);
        $occupancyRatePct = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0.0;
        $vacancyRatePct = $totalUnits > 0 ? round(max(0.0, 100.0 - $occupancyRatePct), 1) : 0.0;

        // 4. Distinct Tenants & Guests
        $distinctTenantsCount = (clone $activeLeaseQuery)->distinct('tenant_id')->count('tenant_id');
        $distinctGuestsCount = (clone $activeStayQuery)->distinct('guest_user_id')->count('guest_user_id');

        // 5. Active Bookings
        $activeBookingsCount = $activeStayCount;

        // 6. Rent Collected (Confirmed payments from rental_payments)
        $rentCollectedQuery = RentalPayment::query()
            ->where('status', 'confirmed')
            ->when(! empty($managedPropertyIds), function ($q) use ($managedPropertyIds) {
                $q->whereHas('lease', fn($lq) => $lq->whereIn('property_id', $managedPropertyIds));
            });

        if (! empty($filters['from_date'])) {
            $rentCollectedQuery->where('created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }
        if (! empty($filters['to_date'])) {
            $rentCollectedQuery->where('created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        $rentCollectedEtb = (float) $rentCollectedQuery->sum('amount_etb');

        // 6b. Expected Rent and Outstanding Rent / Arrears from persistent rent schedules
        $rentScheduleQuery = RentSchedule::query()
            ->when(! empty($managedPropertyIds), function ($q) use ($managedPropertyIds) {
                $q->whereHas('lease', fn($lq) => $lq->whereIn('property_id', $managedPropertyIds));
            });

        if (! empty($filters['from_date'])) {
            $rentScheduleQuery->where('due_date', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }
        if (! empty($filters['to_date'])) {
            $rentScheduleQuery->where('due_date', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        $expectedRentEtb = (float) (clone $rentScheduleQuery)->sum('net_amount_due_etb');
        $outstandingRentEtb = (float) (clone $rentScheduleQuery)
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->sum('outstanding_balance_etb');

        // 7. Short-Stay Confirmed Revenue
        $bookingRevenueQuery = Booking::query()
            ->whereIn('status', ['confirmed', 'checked_in', 'active', 'checked_out', 'completed'])
            ->when(! empty($managedPropertyIds), fn($q) => $q->whereIn('property_id', $managedPropertyIds));

        if (! empty($filters['from_date'])) {
            $bookingRevenueQuery->where('created_at', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }
        if (! empty($filters['to_date'])) {
            $bookingRevenueQuery->where('created_at', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        $stayRevenueEtb = (float) $bookingRevenueQuery->sum('total_amount_etb');
        $totalPortfolioRevenueEtb = $rentCollectedEtb + $stayRevenueEtb;

        // 8. Open Maintenance Requests (open, assigned, in_progress, waiting)
        $openMaintenanceCount = MaintenanceRequest::query()
            ->whereIn('status', [
                MaintenanceStatus::Open->value,
                MaintenanceStatus::Assigned->value,
                MaintenanceStatus::InProgress->value,
                MaintenanceStatus::Waiting->value,
            ])
            ->when(! empty($managedPropertyIds), fn($q) => $q->whereIn('property_id', $managedPropertyIds))
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->count();

        // 9. Active Cleaning Operations (pending, assigned, cleaning, inspection)
        $activeCleaningCount = CleaningTask::query()
            ->whereIn('status', ['pending', 'assigned', 'cleaning', 'inspection'])
            ->when(! empty($managedPropertyIds), fn($q) => $q->whereIn('property_id', $managedPropertyIds))
            ->count();

        // 10. Active Disputes Count
        $activeDisputesCount = Dispute::query()
            ->whereIn('status', ['opened', 'under_review', 'mediation', 'pending_evidence'])
            ->when(! empty($managedPropertyIds), function ($q) use ($managedPropertyIds) {
                $q->whereHas('booking', fn($bq) => $bq->whereIn('property_id', $managedPropertyIds));
            })
            ->count();

        return [
            'properties_managed' => $propertiesManagedCount,
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'vacant_units' => $vacantUnits,
            'occupancy_rate_pct' => $occupancyRatePct,
            'vacancy_rate_pct' => $vacancyRatePct,
            'active_leases' => $activeLeaseCount,
            'tenants' => $distinctTenantsCount,
            'guests' => $distinctGuestsCount,
            'active_bookings' => $activeBookingsCount,
            'rent_collected_etb' => $rentCollectedEtb,
            'expected_rent_etb' => $expectedRentEtb,
            'outstanding_rent_etb' => $outstandingRentEtb,
            'short_stay_revenue_etb' => $stayRevenueEtb,
            'total_portfolio_revenue_etb' => $totalPortfolioRevenueEtb,
            'open_maintenance_requests' => $openMaintenanceCount,
            'active_cleaning_operations' => $activeCleaningCount,
            'active_disputes' => $activeDisputesCount,
            'reporting_period' => [
                'from_date' => $filters['from_date'] ?? null,
                'to_date' => $filters['to_date'] ?? null,
                'as_of_date' => $today,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyKpis(): array
    {
        return [
            'properties_managed' => 0,
            'total_units' => 0,
            'occupied_units' => 0,
            'vacant_units' => 0,
            'occupancy_rate_pct' => 0.0,
            'vacancy_rate_pct' => 100.0,
            'active_leases' => 0,
            'tenants' => 0,
            'guests' => 0,
            'active_bookings' => 0,
            'rent_collected_etb' => 0.00,
            'expected_rent_etb' => 0.00,
            'outstanding_rent_etb' => 0.00,
            'short_stay_revenue_etb' => 0.00,
            'total_portfolio_revenue_etb' => 0.00,
            'open_maintenance_requests' => 0,
            'active_cleaning_operations' => 0,
            'active_disputes' => 0,
            'reporting_period' => [
                'as_of_date' => Carbon::today()->toDateString(),
            ],
        ];
    }
}
