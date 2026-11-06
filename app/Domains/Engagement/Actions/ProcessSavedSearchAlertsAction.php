<?php

declare(strict_types=1);

namespace App\Domains\Engagement\Actions;

use App\Domains\Engagement\Models\SavedSearch;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Notification\Services\MultiChannelNotificationService;
use App\Support\Money\EtbFormatter;
use Illuminate\Support\Facades\Log;

final readonly class ProcessSavedSearchAlertsAction
{
    public function __construct(
        private MultiChannelNotificationService $notificationService = new MultiChannelNotificationService()
    ) {}

    /**
     * Match newly published listings against active saved-search filters and dispatch alerts.
     *
     * @param string|null $frequency Filter by 'instant', 'daily', 'weekly', or null for all.
     * @return array{
     *   searches_evaluated: int,
     *   alerts_dispatched: int,
     *   total_matches: int
     * }
     */
    public function execute(?string $frequency = null): array
    {
        $query = SavedSearch::query()
            ->where('is_active', true)
            ->with('user');

        if ($frequency !== null && $frequency !== 'all') {
            $query->where('frequency', $frequency);
        }

        $searches = $query->get();
        $searchesEvaluated = 0;
        $alertsDispatched = 0;
        $totalMatches = 0;

        foreach ($searches as $search) {
            $searchesEvaluated++;
            $user = $search->user;
            if (! $user) {
                continue;
            }

            $filters = $search->filters_json ?? [];
            $since = $search->last_notified_at ?? $search->created_at;

            $matchesQuery = Listing::query()
                ->where('status', ListingStatus::Published)
                ->where('created_at', '>', $since)
                ->where('published_by_user_id', '!=', $user->id)
                ->with(['property.city', 'property.subcity']);

            // Filter: Transaction Mode
            if (! empty($filters['transaction_mode'])) {
                $matchesQuery->where('transaction_mode', strtoupper((string) $filters['transaction_mode']));
            }

            // Filter: Max Price
            if (! empty($filters['max_price']) && is_numeric($filters['max_price'])) {
                $matchesQuery->where('price_etb', '<=', (float) $filters['max_price']);
            }

            // Filter: Min Price
            if (! empty($filters['min_price']) && is_numeric($filters['min_price'])) {
                $matchesQuery->where('price_etb', '>=', (float) $filters['min_price']);
            }

            // Filter: City Location
            if (! empty($filters['city'])) {
                $cityVal = (string) $filters['city'];
                $matchesQuery->whereHas('property.city', function ($q) use ($cityVal) {
                    $q->where('name_en', $cityVal)
                        ->orWhere('name_am', $cityVal)
                        ->orWhere('code', $cityVal);
                });
            }

            // Filter: Subcity Location
            if (! empty($filters['subcity'])) {
                $subcityVal = (string) $filters['subcity'];
                $matchesQuery->whereHas('property.subcity', function ($q) use ($subcityVal) {
                    $q->where('name_en', $subcityVal)
                        ->orWhere('name_am', $subcityVal);
                });
            }

            // Filter: Minimum Bedrooms
            if (! empty($filters['bedrooms']) && is_numeric($filters['bedrooms'])) {
                $minBeds = (int) $filters['bedrooms'];
                $matchesQuery->whereHas('property', function ($q) use ($minBeds) {
                    $q->where('bedrooms', '>=', $minBeds);
                });
            }

            $matchingListings = $matchesQuery->take(10)->get();
            $matchesCount = $matchingListings->count();

            if ($matchesCount > 0) {
                $first = $matchingListings->first();
                $totalMatches += $matchesCount;

                $this->notificationService->send(
                    recipient: $user,
                    eventKey: 'search.matched',
                    data: [
                        'search_name' => $search->name,
                        'listing_title' => $first->property?->title_en ?? $first->slug,
                        'price_formatted' => EtbFormatter::format($first->price_etb),
                    ],
                    isMarketing: false
                );

                $search->update(['last_notified_at' => now()]);
                $alertsDispatched++;

                Log::info("[SavedSearchAlert] Sent alert to User #{$user->id} for '{$search->name}' with {$matchesCount} matches.");
            }
        }

        return [
            'searches_evaluated' => $searchesEvaluated,
            'alerts_dispatched' => $alertsDispatched,
            'total_matches' => $totalMatches,
        ];
    }
}
