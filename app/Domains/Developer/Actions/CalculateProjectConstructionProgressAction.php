<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use InvalidArgumentException;

final readonly class CalculateProjectConstructionProgressAction
{
    /**
     * Calculates the overall project construction progress percentage based on weighted milestones.
     *
     * @param  array<int, array{
     *   stage_code: string,
     *   name_en?: string,
     *   progress_weight: float|int,
     *   completion_percentage: float|int
     * }>  $milestones
     * @return array{
     *   overall_progress_pct: float,
     *   total_weight: float,
     *   is_valid: bool,
     *   calculation_method: string
     * }
     */
    public function execute(array $milestones): array
    {
        if (empty($milestones)) {
            return [
                'overall_progress_pct' => 0.0,
                'total_weight' => 0.0,
                'is_valid' => false,
                'calculation_method' => 'weighted_average_conventional_rounding',
            ];
        }

        $totalWeight = 0.0;
        $weightedProgressSum = 0.0;

        foreach ($milestones as $milestone) {
            $weight = (float) ($milestone['progress_weight'] ?? 0.0);
            $completion = (float) ($milestone['completion_percentage'] ?? 0.0);

            if ($weight < 0.0) {
                throw new InvalidArgumentException("Milestone weight must be a positive number.");
            }

            if ($completion < 0.0 || $completion > 100.0) {
                throw new InvalidArgumentException("Milestone completion percentage must be between 0.00% and 100.00%.");
            }

            $totalWeight += $weight;
            $weightedProgressSum += ($completion * $weight);
        }

        // Validate that configured weights total 100.00% (with epsilon for float precision)
        if (abs($totalWeight - 100.0) > 0.01) {
            throw new InvalidArgumentException(sprintf(
                "Configured milestone weights must total exactly 100.00%%. Provided weights total %.2f%%.",
                $totalWeight
            ));
        }

        // Conventional arithmetic rounding to 2 decimal places per SRS Section 90, strictly bounded 0-100%
        $overallProgress = min(100.0, max(0.0, round($weightedProgressSum / $totalWeight, 2)));

        return [
            'overall_progress_pct' => $overallProgress,
            'overall_progress_floor_pct' => (int) floor($overallProgress),
            'overall_progress_round_pct' => (int) round($overallProgress),
            'total_weight' => round($totalWeight, 2),
            'is_valid' => true,
            'calculation_method' => 'weighted_average_conventional_rounding',
        ];
    }
}
