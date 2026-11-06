<?php

declare(strict_types=1);

namespace App\Domains\Review\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Property\Models\Property;
use App\Domains\Review\Models\StayReview;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SubmitStayReviewAction
{
    public function __construct(
        private EvaluateTwoWayReviewSubmissionAction $evaluator
    ) {}

    /**
     * @param array<string, int> $scores
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        Booking $booking,
        User $reviewer,
        string $direction,
        array $scores,
        string $publicComment,
        ?string $privateFeedbackNote = null
    ): StayReview {
        $isGuest = (int) $booking->guest_user_id === (int) $reviewer->id;
        $isHost = (int) $booking->host?->user_id === (int) $reviewer->id || (int) $booking->property?->owner_id === (int) $reviewer->id;

        if ($direction === 'guest_to_host' && ! $isGuest) {
            throw new AuthorizationException('Only the booking guest may submit a guest-to-host review.');
        }

        if ($direction === 'host_to_guest' && ! $isHost) {
            throw new AuthorizationException('Only the property host may submit a host-to-guest review.');
        }

        // Validate 14-day review window
        $checkedOutAt = $booking->checkOut?->checked_out_at ?? $booking->checked_out_at;
        if (! $checkedOutAt) {
            throw ValidationException::withMessages([
                'booking' => ['Reviews can only be submitted after checkout.'],
            ]);
        }

        $checkoutDate = CarbonImmutable::parse($checkedOutAt);
        $reviewDeadline = $checkoutDate->addDays(14);
        if (CarbonImmutable::now()->greaterThan($reviewDeadline)) {
            throw ValidationException::withMessages([
                'review_window' => ['The 14-day post-checkout review window has expired.'],
            ]);
        }

        return DB::transaction(function () use (
            $booking,
            $reviewer,
            $direction,
            $scores,
            $publicComment,
            $privateFeedbackNote
        ): StayReview {
            // Check for duplicate review
            $existing = StayReview::query()
                ->where('booking_id', $booking->id)
                ->where('direction', $direction)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'review' => ['A review in this direction has already been submitted for this stay.'],
                ]);
            }

            // Check if counterparty has submitted
            $counterpartyDirection = $direction === 'guest_to_host' ? 'host_to_guest' : 'guest_to_host';
            $counterpartyReview = StayReview::query()
                ->where('booking_id', $booking->id)
                ->where('direction', $counterpartyDirection)
                ->first();

            $counterpartySubmitted = $counterpartyReview !== null;

            // Evaluate review & check spam/moderation
            $evalResult = $this->evaluator->execute(
                direction: $direction,
                scores: $scores,
                comment: $publicComment,
                counterpartyAlreadySubmitted: $counterpartySubmitted
            );

            $revieweeId = $direction === 'guest_to_host'
                ? ($booking->host?->user_id ?? $booking->property?->owner_id)
                : $booking->guest_user_id;

            $now = CarbonImmutable::now();
            $isCurrentApproved = ($evalResult['moderation_status'] === 'approved');
            $shouldPublishCurrent = $isCurrentApproved && $counterpartySubmitted;

            $review = StayReview::query()->create([
                'uuid' => (string) Str::uuid(),
                'booking_id' => $booking->id,
                'property_id' => $booking->property_id,
                'listing_id' => $booking->listing_id,
                'host_id' => $booking->host_id,
                'reviewer_user_id' => $reviewer->id,
                'reviewee_user_id' => $revieweeId,
                'direction' => $direction,
                'overall_rating' => (int) round($evalResult['computed_overall_rating']),
                'communication_rating' => (int) ($scores['communication'] ?? 5),
                'cleanliness_rating' => isset($scores['cleanliness']) ? (int) $scores['cleanliness'] : null,
                'accuracy_rating' => isset($scores['accuracy']) ? (int) $scores['accuracy'] : null,
                'location_rating' => isset($scores['location']) ? (int) $scores['location'] : null,
                'value_rating' => isset($scores['value']) ? (int) $scores['value'] : null,
                'property_care_rating' => isset($scores['property_care']) ? (int) $scores['property_care'] : null,
                'rule_compliance_rating' => isset($scores['rule_compliance']) ? (int) $scores['rule_compliance'] : null,
                'public_comment' => $publicComment,
                'private_feedback_note' => $privateFeedbackNote,
                'is_published' => $shouldPublishCurrent,
                'moderation_status' => $evalResult['moderation_status'],
                'published_at' => $shouldPublishCurrent ? $now : null,
            ]);

            // Crucial: A flagged spam review by one party must not indefinitely suppress the other party's eligible review!
            if ($counterpartyReview && ! $counterpartyReview->is_published && $counterpartyReview->moderation_status === 'approved') {
                $counterpartyReview->update([
                    'is_published' => true,
                    'published_at' => $now,
                ]);
            }

            // Recalculate reputation if a guest_to_host review was published (either current or counterparty)
            $guestToHostPublished = ($direction === 'guest_to_host' && $shouldPublishCurrent)
                || ($counterpartyReview && $counterpartyReview->direction === 'guest_to_host' && (bool) $counterpartyReview->fresh()?->is_published);

            if ($guestToHostPublished) {
                $this->recalculateReputation($booking->property_id, $booking->host_id);
            }

            return $review;
        });
    }

    public function recalculateReputation(int $propertyId, int $hostId): void
    {
        $publishedGuestReviews = StayReview::query()
            ->where('direction', 'guest_to_host')
            ->where('is_published', true)
            ->where('moderation_status', 'approved');

        // Update Host Rating
        $hostStats = (clone $publishedGuestReviews)
            ->where('host_id', $hostId)
            ->selectRaw('COUNT(*) as total_reviews, AVG(overall_rating) as avg_rating')
            ->first();

        if ($hostStats && $hostStats->total_reviews > 0) {
            DB::table('hosts')->where('id', $hostId)->update([
                'average_rating' => round((float) $hostStats->avg_rating, 2),
                'reviews_count' => (int) $hostStats->total_reviews,
                'is_superhost' => ((float) $hostStats->avg_rating >= 4.8 && (int) $hostStats->total_reviews >= 5),
            ]);
        }
    }
}
