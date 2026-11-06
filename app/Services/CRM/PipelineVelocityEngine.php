<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Domains\Commission\Models\CommissionCalculation;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\CRM\Models\CrmLead;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Viewing\Models\ViewingAppointment;
use App\Models\NegotiationOffer;
use App\Models\PropertyViewing;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;

class PipelineVelocityEngine
{
    /**
     * Calculate Section 96 CRM Dashboard metrics filtered optionally by company branch or agent.
     *
     * @return array{
     *   kpis: array{
     *     total_leads: int,
     *     new_leads: int,
     *     viewings_count: int,
     *     active_offers_count: int,
     *     closed_deals_count: int,
     *     estimated_commission_etb: float,
     *     recorded_commission_etb: float,
     *     avg_velocity_days: float
     *   },
     *   leads_by_source: array<string, int>,
     *   stage_breakdown: array<string, int>,
     *   active_branch_code: string
     * }
     */
    public function computeDashboardStats(?string $branchCode = null, ?User $user = null): array
    {
        $branchCode = $branchCode ?: 'ALL';

        $leadQuery = CrmLead::query();
        $viewingQuery = ViewingAppointment::query();
        $offerQuery = PropertyOffer::query();
        $commissionQuery = CommissionCalculation::query();

        // 1. Authorization and Scope Filtering
        $isAdmin = $user && ($user->hasRole('super_admin') || $user->hasRole('platform_admin'));

        if ($user && ! $isAdmin) {
            $userId = (int) $user->id;
            $agentProfile = $user->agentProfile;

            // If user is an agent, resolve their assigned branch
            if ($agentProfile && $agentProfile->branch_id) {
                $userBranchCode = CompanyBranch::query()->where('id', $agentProfile->branch_id)->value('branch_code');
                if ($branchCode !== $userBranchCode && ! $user->hasRole('property_owner')) {
                    $branchCode = $userBranchCode ?: 'ALL';
                }
            }

            // Scope lead queries to assigned agent or managed branch/company
            $managedBranchIds = CompanyBranch::query()->where('manager_user_id', $userId)->pluck('id')->all();
            $ownedCompanyIds = Company::query()->where('owner_user_id', $userId)->pluck('id')->all();

            $leadQuery->where(function ($q) use ($userId, $managedBranchIds, $ownedCompanyIds) {
                $q->where('assigned_agent_id', $userId)
                    ->orWhere('agent_id', $userId);
                if (! empty($managedBranchIds)) {
                    $q->orWhereIn('branch_id', $managedBranchIds);
                }
                if (! empty($ownedCompanyIds)) {
                    $q->orWhereIn('company_id', $ownedCompanyIds);
                }
            });

            // Scope viewings queries to assigned agent
            $viewingQuery->where(function ($q) use ($userId) {
                $q->where('seller_or_agent_user_id', $userId)
                    ->orWhere('agent_id', $userId);
            });

            // Scope offer queries to user deals
            $offerQuery->where(function ($q) use ($userId) {
                $q->where('seller_user_id', $userId)
                    ->orWhere('agent_user_id', $userId)
                    ->orWhere('buyer_user_id', $userId);
            });

            // Scope commission calculations to agent or managed company/branch
            $commissionQuery->where(function ($q) use ($userId, $managedBranchIds, $ownedCompanyIds) {
                $q->where('agent_user_id', $userId);
                if (! empty($managedBranchIds)) {
                    $q->orWhereIn('branch_id', $managedBranchIds);
                }
                if (! empty($ownedCompanyIds)) {
                    $q->orWhereIn('company_id', $ownedCompanyIds);
                }
            });
        }

        // Branch filter
        if ($branchCode && $branchCode !== 'ALL') {
            $branchId = CompanyBranch::query()->where('branch_code', $branchCode)->value('id');
            if ($branchId) {
                $leadQuery->where('branch_id', $branchId);
                $commissionQuery->where('branch_id', $branchId);
            }
        }

        $totalLeads = (clone $leadQuery)->count();
        $newLeads = (clone $leadQuery)->whereIn('stage', ['new_lead', 'new'])->count();
        $closedDeals = (clone $leadQuery)->where('stage', 'closed')->count();

        // Viewings count from authoritative viewing_appointments or fallback property_viewings
        $viewingsCount = (clone $viewingQuery)->whereIn('status', ['confirmed', 'scheduled', 'completed'])->count();
        if ($viewingsCount === 0) {
            $viewingsCount = PropertyViewing::query()->whereIn('status', ['confirmed', 'scheduled', 'completed'])->count();
        }

        // Active Offers count
        $activeOffersCount = (clone $offerQuery)->whereIn('status', ['submitted', 'countered'])->count();
        if ($activeOffersCount === 0) {
            $activeOffersCount = NegotiationOffer::query()->whereIn('status', ['pending', 'countered'])->count();
        }

        // Leads by source
        $leadsBySource = (clone $leadQuery)
            ->select('lead_source', DB::raw('count(*) as count'))
            ->groupBy('lead_source')
            ->pluck('count', 'lead_source')
            ->toArray();

        // Stage breakdown
        $stageBreakdown = (clone $leadQuery)
            ->select('stage', DB::raw('count(*) as count'))
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->toArray();

        // Average velocity from stage history
        $avgDurationSeconds = DB::table('crm_stage_history')
            ->where('duration_in_stage_seconds', '>', 0)
            ->avg('duration_in_stage_seconds');

        $avgVelocityDays = $avgDurationSeconds ? round(((float) $avgDurationSeconds) / 86400, 1) : 0.0;

        // Recorded commissions vs estimated pipeline commission
        $recordedCommission = (float) ((clone $commissionQuery)->sum('gross_commission_etb') ?? 0.0);
        $estimatedCommission = $recordedCommission > 0.0 ? $recordedCommission : (float) ($closedDeals * 400_000.0);

        return [
            'kpis' => [
                'total_leads' => $totalLeads,
                'new_leads' => $newLeads,
                'viewings_count' => $viewingsCount,
                'active_offers_count' => $activeOffersCount,
                'closed_deals_count' => $closedDeals,
                'estimated_commission_etb' => $estimatedCommission,
                'recorded_commission_etb' => $recordedCommission,
                'avg_velocity_days' => $avgVelocityDays,
            ],
            'leads_by_source' => $leadsBySource,
            'stage_breakdown' => $stageBreakdown,
            'active_branch_code' => $branchCode,
        ];
    }
}
