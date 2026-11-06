<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\CRM\Models\CrmLead;
use App\Domains\User\Models\User;

final class CrmLeadAccessGuard
{
    /**
     * Determine whether the given user can view or manage this CRM lead.
     */
    public function canAccessLead(User $user, CrmLead $lead): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('platform_admin')) {
            return true;
        }

        // Assigned agent
        if ((int) $lead->assigned_agent_id === (int) $user->id || (int) $lead->agent_id === (int) $user->id) {
            return true;
        }

        // Branch Manager of the lead's branch
        if ($lead->branch_id) {
            $isBranchManager = CompanyBranch::query()
                ->where('id', $lead->branch_id)
                ->where('manager_user_id', $user->id)
                ->exists();

            if ($isBranchManager) {
                return true;
            }
        }

        // Company Owner
        if ($lead->company_id) {
            $isCompanyOwner = Company::query()
                ->where('id', $lead->company_id)
                ->where('owner_user_id', $user->id)
                ->exists();

            if ($isCompanyOwner) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope a CRM query to the records the user is authorized to view.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<CrmLead>  $query
     * @return \Illuminate\Database\Eloquent\Builder<CrmLead>
     */
    public function scopeQueryForUser(User $user, $query)
    {
        if ($user->hasRole('super_admin') || $user->hasRole('platform_admin')) {
            return $query;
        }

        $userId = (int) $user->id;

        // Collect branches user manages
        $managedBranchIds = CompanyBranch::query()->where('manager_user_id', $userId)->pluck('id')->all();

        // Collect companies user owns
        $ownedCompanyIds = Company::query()->where('owner_user_id', $userId)->pluck('id')->all();

        return $query->where(function ($q) use ($userId, $managedBranchIds, $ownedCompanyIds) {
            $q->where('assigned_agent_id', $userId)
                ->orWhere('agent_id', $userId);

            if (! empty($managedBranchIds)) {
                $q->orWhereIn('branch_id', $managedBranchIds);
            }

            if (! empty($ownedCompanyIds)) {
                $q->orWhereIn('company_id', $ownedCompanyIds);
            }
        });
    }
}

