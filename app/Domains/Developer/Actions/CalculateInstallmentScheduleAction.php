<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use InvalidArgumentException;

final readonly class CalculateInstallmentScheduleAction
{
    /**
     * Calculates authoritative installment amounts for a contract price, ensuring
     * the final installment absorbs rounding cents so the sum exactly equals the contract price.
     *
     * @param  float  $contractPriceEtb
     * @param  array<int, array{
     *   sequence_number: int,
     *   milestone_label_en: string,
     *   milestone_label_am?: string|null,
     *   percentage: float|int,
     *   trigger_milestone_code?: string|null,
     *   due_days_after_trigger?: int
     * }>  $installments
     * @return array{
     *   contract_price_etb: float,
     *   total_percentage: float,
     *   schedule: array<int, array{
     *     sequence_number: int,
     *     milestone_label_en: string,
     *     milestone_label_am: ?string,
     *     percentage: float,
     *     amount_etb: float,
     *     trigger_milestone_code: ?string,
     *     due_days_after_trigger: int
     *   }>
     * }
     */
    public function execute(float $contractPriceEtb, array $installments): array
    {
        if ($contractPriceEtb <= 0.0) {
            throw new InvalidArgumentException("Contract price in ETB must be greater than zero.");
        }

        if (empty($installments)) {
            throw new InvalidArgumentException("Payment plan must contain at least one installment.");
        }

        // Validate percentage sum
        $totalPercentage = 0.0;
        foreach ($installments as $item) {
            $pct = (float) ($item['percentage'] ?? 0.0);
            if ($pct <= 0.0) {
                throw new InvalidArgumentException("Installment percentage must be greater than zero.");
            }
            $totalPercentage += $pct;
        }

        if (abs($totalPercentage - 100.0) > 0.001) {
            throw new InvalidArgumentException(sprintf(
                "Payment plan installment percentages must total exactly 100.00%%. Provided sum: %.2f%%.",
                $totalPercentage
            ));
        }

        // Sort by sequence_number
        usort($installments, fn ($a, $b) => ($a['sequence_number'] ?? 0) <=> ($b['sequence_number'] ?? 0));

        $schedule = [];
        $runningSumEtb = 0.0;
        $count = count($installments);

        // Convert contract price to integer cents (minor units) to prevent floating-point discrepancies
        $totalCents = (int) round($contractPriceEtb * 100);
        $allocatedCents = 0;

        foreach ($installments as $index => $item) {
            $isLast = ($index === $count - 1);
            $pct = (float) $item['percentage'];

            if ($isLast) {
                // Final installment absorbs all remainder cents
                $itemCents = $totalCents - $allocatedCents;
            } else {
                $itemCents = (int) round(($totalCents * $pct) / 100.0);
                $allocatedCents += $itemCents;
            }

            $amountEtb = round($itemCents / 100.0, 2);
            $runningSumEtb += $amountEtb;

            $schedule[] = [
                'sequence_number' => (int) ($item['sequence_number'] ?? ($index + 1)),
                'milestone_label_en' => (string) ($item['milestone_label_en'] ?? "Milestone {$index}"),
                'milestone_label_am' => $item['milestone_label_am'] ?? null,
                'percentage' => $pct,
                'amount_etb' => $amountEtb,
                'trigger_milestone_code' => $item['trigger_milestone_code'] ?? null,
                'due_days_after_trigger' => (int) ($item['due_days_after_trigger'] ?? 0),
            ];
        }

        return [
            'contract_price_etb' => round($contractPriceEtb, 2),
            'total_percentage' => round($totalPercentage, 2),
            'schedule' => $schedule,
        ];
    }
}
