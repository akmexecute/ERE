<?php

declare(strict_types=1);

namespace App\Domains\Commission\Actions;

use App\Domains\Commission\Models\CommissionRule;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CalculateAgentCommissionAction
{
    public const SUPPORTED_TRANSACTION_TYPES = [
        'sale',
        'rental',
        'lease',
        'developer_unit',
    ];

    /**
     * @param  array<int, array{
     *   id: int,
     *   name: string,
     *   company_id?: int|null,
     *   property_type_id?: int|null,
     *   agent_user_id?: int|null,
     *   transaction_type: string,
     *   commission_rate_pct: float|int,
     *   flat_bonus_etb?: float|int,
     *   agent_split_pct?: float|int,
     *   company_split_pct?: float|int,
     *   effective_from?: string|null,
     *   effective_to?: string|null,
     *   is_active?: bool|int
     * }>  $candidateRules
     * @return array{
     *   matched_rule_id: ?int,
     *   matched_rule_name: string,
     *   transaction_amount_etb: float,
     *   commission_rate_pct: float,
     *   gross_commission_etb: float,
     *   agent_share_etb: float,
     *   company_share_etb: float
     * }
     */
    public function execute(
        float $transactionAmountEtb,
        string $transactionType,
        ?int $companyId = null,
        ?int $propertyTypeId = null,
        ?int $agentUserId = null,
        ?string $transactionDate = null,
        array $candidateRules = []
    ): array {
        if ($transactionAmountEtb <= 0) {
            throw new InvalidArgumentException('Transaction amount in ETB must be greater than zero.');
        }

        if (! in_array($transactionType, self::SUPPORTED_TRANSACTION_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported transaction type '{$transactionType}'. Supported types: " . implode(', ', self::SUPPORTED_TRANSACTION_TYPES));
        }

        $txDate = CarbonImmutable::parse($transactionDate ?? now()->toDateString())->toDateString();

        // 1. If candidateRules was not provided, load active rules from database
        if (empty($candidateRules)) {
            try {
                $candidateRules = CommissionRule::query()
                    ->where('is_active', true)
                    ->where('transaction_type', $transactionType)
                    ->where(function ($q) use ($txDate) {
                        $q->whereNull('effective_from')
                            ->orWhere('effective_from', '<=', $txDate);
                    })
                    ->where(function ($q) use ($txDate) {
                        $q->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $txDate);
                    })
                    ->where(function ($q) use ($transactionAmountEtb) {
                        $q->whereNull('min_property_value')
                            ->orWhere('min_property_value', '<=', $transactionAmountEtb);
                    })
                    ->where(function ($q) use ($transactionAmountEtb) {
                        $q->whereNull('max_property_value')
                            ->orWhere('max_property_value', '>=', $transactionAmountEtb);
                    })
                    ->get()
                    ->map(fn (CommissionRule $r) => [
                        'id' => (int) $r->id,
                        'name' => (string) $r->name,
                        'company_id' => $r->company_id ? (int) $r->company_id : null,
                        'property_type_id' => $r->property_type_id ? (int) $r->property_type_id : null,
                        'agent_user_id' => $r->agent_user_id ? (int) $r->agent_user_id : null,
                        'transaction_type' => (string) $r->transaction_type,
                        'commission_rate_pct' => (float) ($r->commission_rate_pct ?: $r->base_commission_percentage),
                        'flat_bonus_etb' => (float) ($r->flat_bonus_etb ?? 0.0),
                        'agent_split_pct' => (float) ($r->agent_split_pct ?: $r->agent_split_percentage),
                        'company_split_pct' => (float) ($r->company_split_pct ?: $r->company_split_percentage),
                        'effective_from' => $r->effective_from?->toDateString(),
                        'effective_to' => $r->effective_to?->toDateString(),
                        'is_active' => (bool) $r->is_active,
                    ])
                    ->all();
            } catch (\Throwable) {
                $candidateRules = [];
            }
        }

        $bestRule = null;
        $bestScore = -1;

        foreach ($candidateRules as $rule) {
            if (isset($rule['is_active']) && ! $rule['is_active']) {
                continue;
            }

            if (($rule['transaction_type'] ?? '') !== $transactionType) {
                continue;
            }

            if (! empty($rule['effective_from']) && $txDate < $rule['effective_from']) {
                continue;
            }
            if (! empty($rule['effective_to']) && $txDate > $rule['effective_to']) {
                continue;
            }

            if (! empty($rule['company_id']) && $rule['company_id'] !== $companyId) {
                continue;
            }
            if (! empty($rule['property_type_id']) && $rule['property_type_id'] !== $propertyTypeId) {
                continue;
            }
            if (! empty($rule['agent_user_id']) && $rule['agent_user_id'] !== $agentUserId) {
                continue;
            }

            // Specificity ranking across SRS Section 99 dimensions (agent > property_type > company > date range)
            $score = 1;
            if (! empty($rule['agent_user_id'])) {
                $score += 8;
            }
            if (! empty($rule['property_type_id'])) {
                $score += 4;
            }
            if (! empty($rule['company_id'])) {
                $score += 2;
            }
            if (! empty($rule['effective_from']) || ! empty($rule['effective_to'])) {
                $score += 1;
            }

            // Deterministic tie-breaking on equal score: higher rule id wins
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRule = $rule;
            } elseif ($score === $bestScore && $bestRule !== null) {
                $currentId = $rule['id'] ?? 0;
                $bestId = $bestRule['id'] ?? 0;
                if ($currentId > $bestId) {
                    $bestRule = $rule;
                }
            }
        }

        $ratePct = (float) ($bestRule['commission_rate_pct'] ?? 2.0);
        $flatBonus = (float) ($bestRule['flat_bonus_etb'] ?? 0.0);
        $agentSplitPct = (float) ($bestRule['agent_split_pct'] ?? 50.0);
        $companySplitPct = (float) ($bestRule['company_split_pct'] ?? (100.0 - $agentSplitPct));

        // Validate financial parameters
        if ($ratePct < 0.0 || $ratePct > 100.0) {
            throw new InvalidArgumentException("Commission rate percentage ({$ratePct}%) must be between 0 and 100.");
        }
        if ($flatBonus < 0.0) {
            throw new InvalidArgumentException('Flat bonus in ETB cannot be negative.');
        }

        // Validate exact 100.00% split total with zero tolerance for drift
        $agentSplitStr = number_format($agentSplitPct, 4, '.', '');
        $companySplitStr = number_format($companySplitPct, 4, '.', '');
        $splitSumStr = bcadd($agentSplitStr, $companySplitStr, 4);
        if (bccomp($splitSumStr, '100.0000', 4) !== 0) {
            throw new InvalidArgumentException("Agent and company splits must total 100% (exactly 100.00%). Configured: {$splitSumStr}%.");
        }

        // Authoritative decimal-safe monetary arithmetic via bcmath at high intermediate scale (10)
        $txStr = number_format($transactionAmountEtb, 4, '.', '');
        $rateStr = number_format($ratePct, 4, '.', '');
        $flatStr = number_format($flatBonus, 4, '.', '');

        // gross = (tx * (rate / 100)) + flatBonus
        $rateRatio = bcdiv($rateStr, '100', 10);
        $baseCommission = bcmul($txStr, $rateRatio, 10);
        $grossUnrounded = bcadd($baseCommission, $flatStr, 10);
        $grossCommissionStr = $this->bcround($grossUnrounded, 2);

        // agentShare = gross * (agentSplit / 100)
        $agentSplitRatio = bcdiv($agentSplitStr, '100', 10);
        $agentUnrounded = bcmul($grossCommissionStr, $agentSplitRatio, 10);
        $agentShareStr = $this->bcround($agentUnrounded, 2);

        // companyShare = gross - agentShare (strictly guarantees agent + company === gross down to the cent)
        $companyShareStr = bcsub($grossCommissionStr, $agentShareStr, 2);

        return [
            'matched_rule_id' => $bestRule['id'] ?? null,
            'matched_rule_name' => $bestRule['name'] ?? 'Default Platform Commission (2%)',
            'transaction_amount_etb' => (float) $txStr,
            'commission_rate_pct' => $ratePct,
            'gross_commission_etb' => (float) $grossCommissionStr,
            'agent_share_etb' => (float) $agentShareStr,
            'company_share_etb' => (float) $companyShareStr,
            'gross_commission_etb_str' => $grossCommissionStr,
            'agent_share_etb_str' => $agentShareStr,
            'company_share_etb_str' => $companyShareStr,
        ];
    }

    /**
     * Exact decimal rounding to given precision using standard half-up arithmetic.
     */
    private function bcround(string $number, int $decimals = 2): string
    {
        if (! str_contains($number, '.')) {
            return $number . '.' . str_repeat('0', $decimals);
        }

        $sign = str_starts_with($number, '-') ? '-' : '';
        $absNumber = ltrim($number, '-');
        $offset = '0.' . str_repeat('0', $decimals) . '5';
        $rounded = bcadd($absNumber, $offset, $decimals + 1);

        $parts = explode('.', $rounded);
        $fraction = isset($parts[1]) ? substr($parts[1], 0, $decimals) : str_repeat('0', $decimals);
        $fraction = str_pad($fraction, $decimals, '0');

        return $sign . $parts[0] . '.' . $fraction;
    }
}
