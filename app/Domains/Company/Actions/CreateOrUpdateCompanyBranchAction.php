<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Company\Services\CompanyBranchAccessGuard;
use App\Domains\Location\Models\Location;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class CreateOrUpdateCompanyBranchAction
{
    public function __construct(
        private CompanyBranchAccessGuard $guard
    ) {}

    /**
     * @param  array{
     *   branch_code: string,
     *   name_en: string,
     *   name_am?: ?string,
     *   name_om?: ?string,
     *   is_head_office?: bool,
     *   manager_user_id?: ?int,
     *   region_id?: ?int,
     *   city_id?: ?int,
     *   subcity_id?: ?int,
     *   phone?: ?string,
     *   email?: ?string,
     *   address_line?: ?string,
     *   is_active?: bool
     * }  $payload
     */
    public function execute(
        User $actor,
        int $companyId,
        array $payload,
        ?CompanyBranch $existingBranch = null
    ): CompanyBranch {
        $company = Company::query()->findOrFail($companyId);

        // 1. Authorization
        if ($existingBranch === null) {
            if (! $this->guard->canManageCompany($actor, $company)) {
                throw new AuthorizationException('Unauthorized to create branches for this company.');
            }
        } else {
            if (! $this->guard->canManageBranch($actor, $existingBranch)) {
                throw new AuthorizationException('Unauthorized to update this branch.');
            }
            if ($existingBranch->company_id !== $companyId) {
                throw new AuthorizationException('Cross-company branch reassignment is prohibited.');
            }
        }

        // 2. Branch code uniqueness within company
        $branchCode = strtoupper(trim($payload['branch_code']));
        $codeQuery = CompanyBranch::query()
            ->where('company_id', $companyId)
            ->where('branch_code', $branchCode);

        if ($existingBranch) {
            $codeQuery->where('id', '!=', $existingBranch->id);
        }

        if ($codeQuery->exists()) {
            throw ValidationException::withMessages([
                'branch_code' => ["Branch code '{$branchCode}' is already in use for this company."],
            ]);
        }

        // 3. Location hierarchy validation
        foreach (['region_id', 'city_id', 'subcity_id'] as $locKey) {
            if (! empty($payload[$locKey])) {
                $locId = (int) $payload[$locKey];
                if (! Location::query()->where('id', $locId)->exists()) {
                    throw new InvalidArgumentException("Invalid location reference for {$locKey}: {$locId}.");
                }
            }
        }

        // 4. Manager validation
        if (! empty($payload['manager_user_id'])) {
            $mgrId = (int) $payload['manager_user_id'];
            if (! User::query()->where('id', $mgrId)->exists()) {
                throw new InvalidArgumentException("Manager user with ID {$mgrId} does not exist.");
            }
        }

        $isHeadOffice = (bool) ($payload['is_head_office'] ?? ($existingBranch?->is_head_office ?? false));
        $isActive = (bool) ($payload['is_active'] ?? ($existingBranch?->is_active ?? true));

        // 5. Database transaction to enforce exactly one active head office per company
        return DB::transaction(function () use (
            $companyId,
            $branchCode,
            $payload,
            $existingBranch,
            $isHeadOffice,
            $isActive
        ): CompanyBranch {
            // Lock the parent company row to serialize concurrent branch creations/updates for this company
            Company::query()->where('id', $companyId)->lockForUpdate()->first();

            if ($existingBranch) {
                $existingBranch->fill([
                    'branch_code' => $branchCode,
                    'name_en' => $payload['name_en'],
                    'name_am' => $payload['name_am'] ?? $existingBranch->name_am,
                    'name_om' => $payload['name_om'] ?? $existingBranch->name_om,
                    'is_head_office' => $isHeadOffice,
                    'manager_user_id' => $payload['manager_user_id'] ?? $existingBranch->manager_user_id,
                    'region_id' => $payload['region_id'] ?? $existingBranch->region_id,
                    'city_id' => $payload['city_id'] ?? $existingBranch->city_id,
                    'subcity_id' => $payload['subcity_id'] ?? $existingBranch->subcity_id,
                    'phone' => $payload['phone'] ?? $existingBranch->phone,
                    'email' => $payload['email'] ?? $existingBranch->email,
                    'address_line' => $payload['address_line'] ?? $existingBranch->address_line,
                    'is_active' => $isActive,
                ]);

                $existingBranch->save();
                $targetBranch = $existingBranch;
            } else {
                $targetBranch = CompanyBranch::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'company_id' => $companyId,
                    'branch_code' => $branchCode,
                    'name_en' => $payload['name_en'],
                    'name_am' => $payload['name_am'] ?? null,
                    'name_om' => $payload['name_om'] ?? null,
                    'is_head_office' => $isHeadOffice,
                    'manager_user_id' => $payload['manager_user_id'] ?? null,
                    'region_id' => $payload['region_id'] ?? null,
                    'city_id' => $payload['city_id'] ?? null,
                    'subcity_id' => $payload['subcity_id'] ?? null,
                    'phone' => $payload['phone'] ?? null,
                    'email' => $payload['email'] ?? null,
                    'address_line' => $payload['address_line'] ?? null,
                    'is_active' => $isActive,
                ]);
            }

            // If this branch is an active head office, ensure all other branches of the company are not head office
            if ($isHeadOffice && $isActive) {
                CompanyBranch::query()
                    ->where('company_id', $companyId)
                    ->where('id', '!=', $targetBranch->id)
                    ->where('is_head_office', true)
                    ->update(['is_head_office' => false]);
            }

            return $targetBranch;
        });
    }
}

