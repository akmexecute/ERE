<?php

declare(strict_types=1);

namespace App\Domains\Commission\Actions;

use App\Domains\Commission\Models\CommissionCalculation;
use App\Domains\Property\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RecordCommissionCalculationAction
{
    public function __construct(
        private CalculateAgentCommissionAction $calculator
    ) {}

    /**
     * Atomically calculate and persist commission ledger record for a closed deal.
     */
    public function execute(
        int $agentUserId,
        int $propertyId,
        float $transactionValueEtb,
        string $transactionType = 'sale',
        ?int $listingId = null,
        ?int $companyId = null,
        ?int $branchId = null,
        ?string $transactionDate = null,
        ?string $transactionReference = null
    ): CommissionCalculation {
        if ($transactionValueEtb <= 0) {
            throw new InvalidArgumentException('Transaction value must be greater than zero.');
        }

        return DB::transaction(function () use (
            $agentUserId,
            $propertyId,
            $transactionValueEtb,
            $transactionType,
            $listingId,
            $companyId,
            $branchId,
            $transactionDate,
            $transactionReference
        ): CommissionCalculation {
            // Lock parent property row to serialize concurrent commission postings for this property
            Property::query()->where('id', $propertyId)->lockForUpdate()->first();

            // Check for existing calculation for this specific transaction reference or property/listing
            $query = CommissionCalculation::query();
            if ($transactionReference !== null && trim($transactionReference) !== '') {
                $query->where('transaction_reference', trim($transactionReference))
                    ->where('transaction_type', $transactionType);
            } else {
                $query->where('property_id', $propertyId)
                    ->where('agent_user_id', $agentUserId)
                    ->where('transaction_type', $transactionType);

                if ($listingId) {
                    $query->where('listing_id', $listingId);
                }
            }

            /** @var CommissionCalculation|null $existing */
            $existing = $query->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            // Calculate authoritative commission breakdown
            $calc = $this->calculator->execute(
                transactionAmountEtb: $transactionValueEtb,
                transactionType: $transactionType,
                companyId: $companyId,
                propertyTypeId: null,
                agentUserId: $agentUserId,
                transactionDate: $transactionDate
            );

            return CommissionCalculation::query()->create([
                'uuid' => (string) Str::uuid(),
                'commission_rule_id' => $calc['matched_rule_id'],
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'agent_user_id' => $agentUserId,
                'property_id' => $propertyId,
                'listing_id' => $listingId,
                'transaction_type' => $transactionType,
                'transaction_reference' => $transactionReference ? trim($transactionReference) : null,
                'transaction_value_etb' => $calc['transaction_amount_etb'],
                'applied_rate_pct' => $calc['commission_rate_pct'],
                'gross_commission_etb' => $calc['gross_commission_etb_str'] ?? $calc['gross_commission_etb'],
                'agent_payout_etb' => $calc['agent_share_etb_str'] ?? $calc['agent_share_etb'],
                'company_share_etb' => $calc['company_share_etb_str'] ?? $calc['company_share_etb'],
                'status' => CommissionCalculation::STATUS_PENDING_SETTLEMENT,
            ]);
        });
    }
}

