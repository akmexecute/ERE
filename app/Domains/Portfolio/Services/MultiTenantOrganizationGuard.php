<?php

declare(strict_types=1);

namespace App\Domains\Portfolio\Services;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class MultiTenantOrganizationGuard
{
    /**
     * Resolves the primary company ID associated with an authenticated actor.
     */
    public static function resolveActorCompanyId(User $actor): ?int
    {
        // 1. Direct company_id attribute on user (if present)
        if (isset($actor->company_id) && $actor->company_id) {
            return (int) $actor->company_id;
        }

        // 2. Company ownership
        $ownedCompanyId = DB::table('companies')
            ->where('owner_user_id', $actor->id)
            ->value('id');

        if ($ownedCompanyId) {
            return (int) $ownedCompanyId;
        }

        // 3. Branch manager assignment
        $branchCompanyId = DB::table('company_branches')
            ->where('manager_user_id', $actor->id)
            ->value('company_id');

        if ($branchCompanyId) {
            return (int) $branchCompanyId;
        }

        // 4. Agent profile assignment
        $agentCompanyId = DB::table('agent_profiles')
            ->where('user_id', $actor->id)
            ->value('company_id');

        return $agentCompanyId ? (int) $agentCompanyId : null;
    }

    /**
     * Resolves the specific branch ID for a branch-scoped actor (returns null for head office / global).
     */
    public static function resolveActorBranchId(User $actor): ?int
    {
        // 1. Direct branch_id attribute on user (if present)
        if (isset($actor->branch_id) && $actor->branch_id) {
            return (int) $actor->branch_id;
        }

        // 2. Branch manager assignment
        $managedBranchId = DB::table('company_branches')
            ->where('manager_user_id', $actor->id)
            ->value('id');

        if ($managedBranchId) {
            return (int) $managedBranchId;
        }

        // 3. Agent profile assignment
        $agentBranchId = DB::table('agent_profiles')
            ->where('user_id', $actor->id)
            ->value('branch_id');

        return $agentBranchId ? (int) $agentBranchId : null;
    }

    /**
     * Checks if the actor is a company head-office administrator.
     */
    public static function isHeadOfficeAdmin(User $actor, ?int $companyId = null): bool
    {
        if ($actor->hasRole('super_admin') || $actor->hasRole('platform_admin')) {
            return true;
        }

        $companyId = $companyId ?? self::resolveActorCompanyId($actor);
        if (! $companyId) {
            return false;
        }

        // Check if user owns the company
        $isOwner = DB::table('companies')
            ->where('id', $companyId)
            ->where('owner_user_id', $actor->id)
            ->exists();

        if ($isOwner) {
            return true;
        }

        // Check if user manages the head-office branch
        return DB::table('company_branches')
            ->where('company_id', $companyId)
            ->where('manager_user_id', $actor->id)
            ->where('is_head_office', true)
            ->exists();
    }

    /**
     * Applies strict, fail-closed multi-tenant and branch isolation to an Eloquent query.
     */
    public static function applyScope(
        Builder $query,
        User $actor,
        string $companyColumn = 'company_id',
        string $branchColumn = 'branch_id'
    ): Builder {
        // Platform & Super Admins have global visibility
        if ($actor->hasRole('super_admin') || $actor->hasRole('platform_admin')) {
            return $query;
        }

        $companyId = self::resolveActorCompanyId($actor);

        // If actor has no associated company, strictly fail closed (no records returned)
        if (! $companyId) {
            return $query->whereRaw('1 = 0');
        }

        $query->where($companyColumn, $companyId);

        // Head-office administrators can access all branches within their own company
        if (self::isHeadOfficeAdmin($actor, $companyId)) {
            return $query;
        }

        // Branch-scoped actors MUST match their specific branch ID.
        // FAIL-CLOSED: Records with null branch are NOT accessible to branch-scoped users!
        $branchId = self::resolveActorBranchId($actor);
        if ($branchId) {
            $query->where($branchColumn, $branchId);
        } else {
            // Non-head office user with no branch assigned cannot view branch-scoped records
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * Determines whether an actor can view/access a specific resource.
     */
    public static function canAccess(
        User $actor,
        Model|array $resource,
        string $companyColumn = 'company_id',
        string $branchColumn = 'branch_id'
    ): bool {
        if ($actor->hasRole('super_admin') || $actor->hasRole('platform_admin')) {
            return true;
        }

        $actorCompanyId = self::resolveActorCompanyId($actor);
        if (! $actorCompanyId) {
            return false;
        }

        $resCompanyId = $resource instanceof Model
            ? $resource->getAttribute($companyColumn)
            : ($resource[$companyColumn] ?? null);

        // Cross-company access is strictly prohibited
        if ((int) $resCompanyId !== $actorCompanyId) {
            return false;
        }

        // Head-office administrators have full company-wide visibility
        if (self::isHeadOfficeAdmin($actor, $actorCompanyId)) {
            return true;
        }

        $actorBranchId = self::resolveActorBranchId($actor);
        $resBranchId = $resource instanceof Model
            ? $resource->getAttribute($branchColumn)
            : ($resource[$branchColumn] ?? null);

        // FAIL-CLOSED: If branch-scoped actor has no branch or resource branch is null or different, access is rejected!
        if (! $actorBranchId || ! $resBranchId || (int) $resBranchId !== (int) $actorBranchId) {
            return false;
        }

        return true;
    }

    /**
     * Authorizes access or throws AuthorizationException.
     *
     * @throws AuthorizationException
     */
    public static function authorizeAccess(
        User $actor,
        Model|array $resource,
        string $companyColumn = 'company_id',
        string $branchColumn = 'branch_id'
    ): void {
        if (! self::canAccess($actor, $resource, $companyColumn, $branchColumn)) {
            throw new AuthorizationException('Cross-organization or unauthorized branch access is strictly prohibited.');
        }
    }
}
