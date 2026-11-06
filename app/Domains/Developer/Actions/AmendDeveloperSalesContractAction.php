<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use App\Domains\Developer\Enums\SalesContractStatus;
use App\Domains\Developer\Models\ContractAmendment;
use App\Domains\Developer\Models\ContractInstallment;
use App\Domains\Developer\Models\DeveloperSalesContract;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class AmendDeveloperSalesContractAction
{
    public function __construct(
        private CalculateInstallmentScheduleAction $calculator = new CalculateInstallmentScheduleAction()
    ) {
    }

    /**
     * Authoritatively amends a developer sales contract price, snapshots previous state,
     * and generates a revised installment schedule totaling 100.00% of the revised price.
     */
    public function execute(
        int $contractId,
        float $revisedPriceEtb,
        string $reason,
        User $authorizedUser
    ): DeveloperSalesContract {
        if ($revisedPriceEtb <= 0.0) {
            throw new InvalidArgumentException("Revised contract price must be greater than zero.");
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException("A valid contractual reason must be provided for the price amendment.");
        }

        return DB::transaction(function () use ($contractId, $revisedPriceEtb, $reason, $authorizedUser): DeveloperSalesContract {
            /** @var DeveloperSalesContract $contract */
            $contract = DeveloperSalesContract::with(['installments', 'project.developer.company'])
                ->where('id', $contractId)
                ->lockForUpdate()
                ->firstOrFail();

            // Check authorization
            $isSuperAdmin = method_exists($authorizedUser, 'hasRole') && $authorizedUser->hasRole('super_admin');
            $isCompanyOwner = (int) $contract->project->developer->company->owner_user_id === (int) $authorizedUser->id;
            $isDeveloperUser = (int) $contract->project->developer->user_id === (int) $authorizedUser->id;

            if (! $isSuperAdmin && ! $isCompanyOwner && ! $isDeveloperUser) {
                throw new DomainException("Unauthorized: Only authorized company officers or administrators may amend sales contracts.");
            }

            if ($contract->status !== SalesContractStatus::Active && $contract->status !== SalesContractStatus::Signed) {
                throw new DomainException(sprintf(
                    "Contract #%s cannot be amended in status '%s'. Only active or signed contracts are amendable.",
                    $contract->contract_code,
                    $contract->status->value
                ));
            }

            // Ensure no unaccepted/pending amendments exist before proposing a new one
            $hasPending = ContractAmendment::where('contract_id', $contract->id)
                ->where('status', 'pending')
                ->exists();
            if ($hasPending) {
                throw new DomainException(sprintf(
                    "Contract #%s already has a pending amendment awaiting buyer consent. Competing amendments cannot be created.",
                    $contract->contract_code
                ));
            }

            $previousPrice = (float) $contract->contract_price_etb;

            // 1. Snapshot previous installments schedule
            $previousSchedule = $contract->installments->map(fn (ContractInstallment $i) => [
                'sequence_number' => $i->sequence_number,
                'milestone_label_en' => $i->milestone_label_en,
                'percentage' => (float) $i->percentage,
                'amount_etb' => (float) $i->amount_etb,
                'paid_amount_etb' => (float) $i->paid_amount_etb,
                'outstanding_balance_etb' => (float) $i->outstanding_balance_etb,
                'status' => $i->status,
            ])->toArray();

            // 2. Prepare installment template using original percentage distribution
            $planSnapshot = $contract->payment_plan_snapshot_json ?? [];
            $templateInstallments = $planSnapshot['installments'] ?? $contract->installments->map(fn (ContractInstallment $i) => [
                'sequence_number' => $i->sequence_number,
                'milestone_label_en' => $i->milestone_label_en,
                'percentage' => (float) $i->percentage,
            ])->toArray();

            // 3. Recalculate schedule with cent absorption totaling 100.00% of revised price
            $calc = $this->calculator->execute($revisedPriceEtb, $templateInstallments);

            // 4. Record the proposal in pending state (contract remains unaltered until buyer consent)
            ContractAmendment::create([
                'contract_id' => $contract->id,
                'status' => 'pending',
                'previous_price_etb' => $previousPrice,
                'revised_price_etb' => $revisedPriceEtb,
                'previous_schedule_snapshot_json' => $previousSchedule,
                'revised_schedule_snapshot_json' => $calc['schedule'],
                'reason' => $reason,
                'authorized_by_user_id' => $authorizedUser->id,
                'accepted_by_buyer' => false,
            ]);

            // Note: In accordance with statutory contract integrity rules, the active contract price
            // and installment schedule remain untouched until the buyer explicitly accepts the amendment.

            return $contract->load(['installments', 'amendments']);

            return $contract->load(['installments', 'amendments']);
        });
    }
}

