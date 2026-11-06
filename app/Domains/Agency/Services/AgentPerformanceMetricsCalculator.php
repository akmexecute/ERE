<?php

declare(strict_types=1);

namespace App\Domains\Agency\Services;

use App\Domains\Agency\Models\AgentProfile;
use App\Domains\CRM\Models\CrmLead;
use App\Domains\Listing\Models\Listing;
use App\Domains\Offer\Models\PropertyOffer;
use App\Domains\Review\Models\StayReview;

final class AgentPerformanceMetricsCalculator
{
    /**
     * Compute and persist data-driven performance metrics for an agent profile.
     *
     * @return array{
     *   active_listings_count: int,
     *   closed_deals_count: int,
     *   reviews_count: int,
     *   average_rating: float,
     *   response_rate_pct: int,
     *   avg_response_minutes: int
     * }
     */
    public function recalculate(AgentProfile $profile): array
    {
        $userId = (int) $profile->user_id;

        // 1. Active Listings Count
        $activeListings = Listing::query()
            ->where('published_by_user_id', $userId)
            ->where('status', 'published')
            ->count();

        // 2. Closed Deals Count (CRM closed leads + accepted property offers)
        $closedLeads = CrmLead::query()
            ->where('assigned_agent_id', $userId)
            ->where('stage', 'closed')
            ->count();

        $acceptedOffers = PropertyOffer::query()
            ->where('agent_user_id', $userId)
            ->where('status', 'accepted')
            ->count();

        $closedDeals = $closedLeads + $acceptedOffers;

        // 3. Reviews and Rating
        $reviewsCount = StayReview::query()
            ->where('reviewee_user_id', $userId)
            ->count();

        $avgRating = $reviewsCount > 0
            ? (float) round(StayReview::query()->where('reviewee_user_id', $userId)->avg('rating') ?? 0.0, 2)
            : (float) $profile->average_rating;

        // 4. Update cached metrics on profile
        $profile->update([
            'active_listings_count' => $activeListings,
            'closed_deals_count' => $closedDeals,
            'reviews_count' => $reviewsCount,
            'average_rating' => $avgRating,
        ]);

        return [
            'active_listings_count' => $activeListings,
            'closed_deals_count' => $closedDeals,
            'reviews_count' => $reviewsCount,
            'average_rating' => $avgRating,
            'response_rate_pct' => (int) $profile->response_rate_pct,
            'avg_response_minutes' => (int) $profile->avg_response_minutes,
        ];
    }
}

