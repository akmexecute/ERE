<?php

declare(strict_types=1);

namespace App\Domains\Review\Actions;

use InvalidArgumentException;

final readonly class EvaluateTwoWayReviewSubmissionAction
{
    /**
     * @param  array<string, int>  $scores
     * @return array{
     *   direction: string,
     *   computed_overall_rating: float,
     *   moderation_status: string,
     *   publish_both_now: bool
     * }
     */
    public function execute(
        string $direction,
        array $scores,
        string $comment,
        bool $counterpartyAlreadySubmitted
    ): array {
        if ($direction === 'guest_to_host') {
            $required = ['cleanliness', 'accuracy', 'communication', 'location', 'value'];
        } elseif ($direction === 'host_to_guest') {
            $required = ['communication', 'property_care', 'rule_compliance'];
        } else {
            throw new InvalidArgumentException("Invalid review direction '{$direction}'.");
        }

        $sum = 0;
        foreach ($required as $key) {
            $val = (int) ($scores[$key] ?? 0);
            if ($val < 1 || $val > 5) {
                throw new InvalidArgumentException("Criterion '{$key}' must be rated between 1 and 5.");
            }
            $sum += $val;
        }

        $computedOverall = round($sum / count($required), 2);

        // SRS Section 119: Detect spam, external link solicitation, or abusive content
        $moderationStatus = 'approved';
        if (preg_match('/(https?:\/\/|t\.me\/|whatsapp\.com|scam|idiot)/iu', $comment)) {
            $moderationStatus = 'flagged_spam';
        }

        // Publish immediately only if moderation passed AND counterparty has also submitted
        $publishBothNow = ($moderationStatus === 'approved') && $counterpartyAlreadySubmitted;

        return [
            'direction' => $direction,
            'computed_overall_rating' => $computedOverall,
            'moderation_status' => $moderationStatus,
            'publish_both_now' => $publishBothNow,
        ];
    }
}
