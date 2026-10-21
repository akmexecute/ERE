<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Analytics\Models\MarketAnalyticsSnapshot;
use App\Domains\Analytics\Services\MarketAnalyticsCalculator;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Location\Models\Location;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class AggregateDailyMarketAnalyticsCommand extends Command
{
    protected $signature = 'analytics:aggregate-daily {--date= : The snapshot date (YYYY-MM-DD)}';

    protected $description = 'Aggregate daily national & city real estate market analytics (SRS Sections 37, 126, 214, 215)';

    public function handle(): int
    {
        $dateStr = $this->option('date') ?: Carbon::today()->toDateString();
        $snapshotDate = Carbon::parse($dateStr)->toDateString();

        $this->info("Aggregating daily real estate market analytics for: {$snapshotDate}");

        // Cities to snapshot: Addis Ababa, Harar, Jijiga, Dire Dawa, Hawassa, Bahir Dar
        $cities = Location::query()->where('level', 'city')->get();

        $modes = ['BUY', 'RENT', 'LEASE'];
        $createdSnapshots = 0;

        foreach ($modes as $mode) {
            // 1. National Snapshot (city_id = null)
            $nationalListings = Listing::query()
                ->where('status', ListingStatus::Published->value)
                ->where('transaction_mode', $mode)
                ->get(['price_etb', 'price_per_sqm_etb'])
                ->map(fn ($l) => ['price_etb' => (float) $l->price_etb, 'price_per_sqm_etb' => $l->price_per_sqm_etb ? (float) $l->price_per_sqm_etb : null])
                ->all();

            $nationalSummary = MarketAnalyticsCalculator::summarizeListings($nationalListings);

            MarketAnalyticsSnapshot::query()->updateOrCreate(
                [
                    'snapshot_date' => $snapshotDate,
                    'city_id' => null,
                    'transaction_mode' => $mode,
                ],
                [
                    'active_listings_count' => $nationalSummary['count'],
                    'avg_asking_price_etb' => $nationalSummary['avg_asking_price_etb'],
                    'median_asking_price_etb' => $nationalSummary['median_asking_price_etb'],
                    'avg_price_per_sqm_etb' => $nationalSummary['avg_price_per_sqm_etb'],
                    'data_basis' => MarketAnalyticsCalculator::DATA_BASIS_ASKING_PRICE,
                ]
            );
            $createdSnapshots++;

            // 2. City-Level Snapshots
            foreach ($cities as $city) {
                $cityListings = Listing::query()
                    ->join('properties', 'properties.id', '=', 'listings.property_id')
                    ->where('listings.status', ListingStatus::Published->value)
                    ->where('listings.transaction_mode', $mode)
                    ->where('properties.city_id', $city->id)
                    ->get(['listings.price_etb', 'listings.price_per_sqm_etb'])
                    ->map(fn ($l) => ['price_etb' => (float) $l->price_etb, 'price_per_sqm_etb' => $l->price_per_sqm_etb ? (float) $l->price_per_sqm_etb : null])
                    ->all();

                $citySummary = MarketAnalyticsCalculator::summarizeListings($cityListings);

                MarketAnalyticsSnapshot::query()->updateOrCreate(
                    [
                        'snapshot_date' => $snapshotDate,
                        'city_id' => $city->id,
                        'transaction_mode' => $mode,
                    ],
                    [
                        'region_id' => $city->parent_id,
                        'active_listings_count' => $citySummary['count'],
                        'avg_asking_price_etb' => $citySummary['avg_asking_price_etb'],
                        'median_asking_price_etb' => $citySummary['median_asking_price_etb'],
                        'avg_price_per_sqm_etb' => $citySummary['avg_price_per_sqm_etb'],
                        'data_basis' => MarketAnalyticsCalculator::DATA_BASIS_ASKING_PRICE,
                    ]
                );
                $createdSnapshots++;
            }
        }

        $this->info("Successfully generated {$createdSnapshots} market analytics snapshots with data_basis = 'platform_asking_price_indicator'.");

        return self::SUCCESS;
    }
}
