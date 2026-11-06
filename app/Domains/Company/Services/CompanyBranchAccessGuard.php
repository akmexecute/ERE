<?php

declare(strict_types=1);

namespace App\Domains\Company\Services;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\User\Models\User;

final class CompanyBranchAccessGuard
{
    /**
     * Determine whether the user has full administrative ownership over the company.
     */
    public function canManageCompany(User $user, Company $company): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('platform_admin')) {
            return true;
        }

        return (int) $company->owner_user_id === (int) $user->id;
    }

    /**
     * Determine whether the user can manage this specific branch.
     */
    public function canManageBranch(User $user, CompanyBranch $branch): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('platform_admin')) {
            return true;
        }

        /** @var Company|null $company */
        $company = $branch->company ?? Company::query()->find($branch->company_id);
        if ($company && (int) $company->owner_user_id === (int) $user->id) {
            return true;
        }

        return (int) $branch->manager_user_id === (int) $user->id;
    }

    /**
     * Determine whether the user can view branch-level records.
     */
    public function canViewBranch(User $user, CompanyBranch $branch): bool
    {
        if ($this->canManageBranch($user, $branch)) {
            return true;
        }

        // Agents assigned to this branch
        $agentProfile = $user->agentProfile;
        if ($agentProfile && (int) $agentProfile->branch_id === (int) $branch->id) {
            return true;
        }

        return false;
    }

    /**
     * Ensure the user does not attempt cross-company access.
     */
    public function validateCompanyScope(User $user, int $targetCompanyId): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('platform_admin')) {
            return true;
        }

        $company = Company::query()->find($targetCompanyId);
        if ($company && (int) $company->owner_user_id === (int) $user->id) {
            return true;
        }

        // Check if agent belongs to this company
        $agentProfile = $user->agentProfile;
        if ($agentProfile && (int) $agentProfile->company_id === $targetCompanyId) {
            return true;
        }

        // Check if user is branch manager in this company
        return CompanyBranch::query()
            ->where('company_id', $targetCompanyId)
            ->where('manager_user_id', $user->id)
            ->exists();
    }
}

