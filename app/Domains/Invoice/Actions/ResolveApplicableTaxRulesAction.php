<?php

declare(strict_types=1);

namespace App\Domains\Invoice\Actions;

use App\Domains\Invoice\Models\TaxRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final readonly class ResolveApplicableTaxRulesAction
{
    /**
     * Resolves and calculates applicable tax rules deterministically.
     * Enforces geographic specificity (local override vs national fallback) and compound tax ordering.
     *
     * @return array{
     *     subtotal_etb: float,
     *     tax_amount_etb: float,
     *     withholding_amount_etb: float,
     *     total_amount_etb: float,
     *     applied_rules: array<int, array{
     *         tax_code: string,
     *         name_en: string,
     *         rate_pct: float,
     *         tax_amount_etb: float,
     *         is_compound: bool,
     *         is_withholding: bool,
     *         tax_rule_id: int,
     *     }>
     * }
     */
    public function execute(
        string $serviceType,
        string|\DateTimeInterface $date,
        ?int $locationId,
        float|int|string $subtotalEtb
    ): array {
        $subtotal = round((float) $subtotalEtb, 2);
        $dateStr = Carbon::parse($date)->toDateString();

        // 1. Fetch all candidate active rules whose effective dates include the invoice date
        /** @var Collection<int, TaxRule> $candidates */
        $candidates = TaxRule::query()
            ->where('is_active', true)
            ->where('effective_from', '<=', $dateStr)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $dateStr);
            })
            ->whereIn('service_type', [$serviceType, 'all'])
            ->where(function ($q) use ($locationId) {
                $q->whereNull('location_id');
                if ($locationId !== null) {
                    $q->orWhere('location_id', $locationId);
                }
            })
            ->orderBy('calculation_order')
            ->orderByDesc('effective_from')
            ->get();

        // 2. Specificity and Override Policy: Group by tax_code
        // If a location-specific rule exists for a tax code, it OVERRIDES the national rule!
        /** @var array<string, TaxRule> $resolvedRulesByCode */
        $resolvedRulesByCode = [];

        foreach ($candidates as $rule) {
            $code = $rule->tax_code;

            if (! isset($resolvedRulesByCode[$code])) {
                $resolvedRulesByCode[$code] = $rule;
                continue;
            }

            $existing = $resolvedRulesByCode[$code];

            // If current is location-specific and existing was national, override with location-specific
            if ($rule->location_id !== null && $existing->location_id === null) {
                $resolvedRulesByCode[$code] = $rule;
            }
        }

        // 3. Sort final resolved rules by calculation_order
        $sortedRules = collect($resolvedRulesByCode)
            ->sortBy('calculation_order')
            ->values();

        $appliedRules = [];
        $accumulatedTax = 0.00;
        $withholdingAmount = 0.00;

        foreach ($sortedRules as $rule) {
            $rate = (float) $rule->rate_pct;

            if ($rule->is_withholding) {
                // Withholding tax calculated on base subtotal
                $taxAmount = round($subtotal * ($rate / 100.0), 2);
                $withholdingAmount = round($withholdingAmount + $taxAmount, 2);
            } elseif ($rule->is_compound) {
                // Compound tax calculated on (base subtotal + accumulated previous taxes)
                $taxableBase = round($subtotal + $accumulatedTax, 2);
                $taxAmount = round($taxableBase * ($rate / 100.0), 2);
                $accumulatedTax = round($accumulatedTax + $taxAmount, 2);
            } else {
                // Non-compound tax calculated on base subtotal
                $taxAmount = round($subtotal * ($rate / 100.0), 2);
                $accumulatedTax = round($accumulatedTax + $taxAmount, 2);
            }

            $appliedRules[] = [
                'tax_code' => $rule->tax_code,
                'name_en' => $rule->name_en,
                'rate_pct' => $rate,
                'tax_amount_etb' => $taxAmount,
                'is_compound' => (bool) $rule->is_compound,
                'is_withholding' => (bool) $rule->is_withholding,
                'tax_rule_id' => $rule->id,
            ];
        }

        $totalAmount = round($subtotal + $accumulatedTax, 2);

        return [
            'subtotal_etb' => $subtotal,
            'tax_amount_etb' => $accumulatedTax,
            'withholding_amount_etb' => $withholdingAmount,
            'total_amount_etb' => $totalAmount,
            'applied_rules' => $appliedRules,
        ];
    }
}
