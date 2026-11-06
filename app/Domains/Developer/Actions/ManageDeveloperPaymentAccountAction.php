<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use App\Domains\Developer\Models\DeveloperPaymentAccount;
use App\Domains\Developer\Models\DeveloperProfile;
use App\Domains\User\Models\User;
use DomainException;
use InvalidArgumentException;

final readonly class ManageDeveloperPaymentAccountAction
{
    /**
     * Creates or updates a manual payment destination account for a developer.
     *
     * @param  User  $actor
     * @param  array{
     *   account_id?: int|null,
     *   developer_id: int,
     *   project_id?: int|null,
     *   account_label: string,
     *   institution_name: string,
     *   account_holder_name: string,
     *   account_number: string,
     *   branch_name?: string|null,
     *   swift_code?: string|null,
     *   currency?: string,
     *   transfer_instructions?: string|null,
     *   is_active?: bool
     * }  $data
     * @return DeveloperPaymentAccount
     */
    public function execute(User $actor, array $data): DeveloperPaymentAccount
    {
        $developerId = (int) $data['developer_id'];
        /** @var DeveloperProfile $developer */
        $developer = DeveloperProfile::with('company')->findOrFail($developerId);

        // Check developer authorization
        $isSuperAdmin = method_exists($actor, 'hasRole') && $actor->hasRole('super_admin');
        $isCompanyOwner = (int) $developer->company->owner_user_id === (int) $actor->id;
        $isDeveloperUser = (int) $developer->user_id === (int) $actor->id;

        if (! $isSuperAdmin && ! $isCompanyOwner && ! $isDeveloperUser) {
            throw new DomainException("Unauthorized: You do not have permission to manage payment accounts for this developer.");
        }

        if (empty($data['institution_name']) || empty($data['account_number']) || empty($data['account_holder_name'])) {
            throw new InvalidArgumentException("Bank institution name, account holder name, and account number are required.");
        }

        $accountId = $data['account_id'] ?? null;

        if ($accountId) {
            /** @var DeveloperPaymentAccount $account */
            $account = DeveloperPaymentAccount::where('id', $accountId)
                ->where('developer_id', $developer->id)
                ->firstOrFail();

            $account->update([
                'project_id' => $data['project_id'] ?? $account->project_id,
                'account_label' => $data['account_label'] ?? $account->account_label,
                'institution_name' => $data['institution_name'],
                'account_holder_name' => $data['account_holder_name'],
                'account_number' => $data['account_number'],
                'branch_name' => $data['branch_name'] ?? $account->branch_name,
                'swift_code' => $data['swift_code'] ?? $account->swift_code,
                'currency' => $data['currency'] ?? $account->currency,
                'transfer_instructions' => $data['transfer_instructions'] ?? $account->transfer_instructions,
                'is_active' => $data['is_active'] ?? $account->is_active,
            ]);

            return $account;
        }

        return DeveloperPaymentAccount::create([
            'company_id' => $developer->company_id,
            'developer_id' => $developer->id,
            'project_id' => $data['project_id'] ?? null,
            'account_label' => $data['account_label'] ?? ($data['institution_name'] . ' Account'),
            'institution_name' => $data['institution_name'],
            'account_holder_name' => $data['account_holder_name'],
            'account_number' => $data['account_number'],
            'branch_name' => $data['branch_name'] ?? null,
            'swift_code' => $data['swift_code'] ?? null,
            'currency' => $data['currency'] ?? 'ETB',
            'transfer_instructions' => $data['transfer_instructions'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}

