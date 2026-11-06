<?php

declare(strict_types=1);

namespace App\Domains\GIS\Services;

use App\Domains\GIS\Models\GisNeighborhoodHeatmap;
use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Location\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class NeighborhoodHeatmapAggregationService
{
    /**
     * Compute and record neighborhood ETB/m² heatmap statistics from active platform listings.
     *
     * @return Collection<int, GisNeighborhoodHeatmap>
     */
    public function aggregate(string $transactionMode = 'BUY', ?int $targetLocationId = null): Collection
    {
        $mode = strtoupper($transactionMode);
        $snapshotDate = now()->toDateString();

        // 1. Query active published listings with valid area and prices
        $query = Listing::query()
            ->join('properties', 'properties.id', '=', 'listings.property_id')
            ->where('listings.status', ListingStatus::Published->value)
            ->where('listings.transaction_mode', $mode)
            ->where('listings.price_etb', '>', 0)
            ->where(function ($q): void {
                $q->where('properties.building_area_sqm', '>', 0)
                    ->orWhere('properties.land_area_sqm', '>', 0);
            })
            ->whereNotNull('properties.neighborhood_id');

        if ($targetLocationId) {
            $query->where('properties.neighborhood_id', $targetLocationId);
        }

        $listings = $query->select([
            'listings.id as listing_id',
            'listings.price_etb',
            'listings.price_per_sqm_etb',
            'properties.neighborhood_id',
            'properties.building_area_sqm',
            'properties.land_area_sqm',
        ])->get();

        // Group by neighborhood
        $grouped = $listings->groupBy('neighborhood_id');
        $heatmaps = collect();

        foreach ($grouped as $neighborhoodId => $items) {
            $pricesPerSqm = [];
            foreach ($items as $item) {
                if ($item->price_per_sqm_etb && (float) $item->price_per_sqm_etb > 0) {
                    $pricesPerSqm[] = (float) $item->price_per_sqm_etb;
                } else {
                    $area = (float) ($item->building_area_sqm ?: $item->land_area_sqm);
                    if ($area > 0) {
                        $pricesPerSqm[] = round((float) $item->price_etb / $area, 2);
                    }
                }
            }

            if (empty($pricesPerSqm)) {
                continue;
            }

            sort($pricesPerSqm);
            $sampleSize = count($pricesPerSqm);
            $avgPrice = round(array_sum($pricesPerSqm) / $sampleSize, 2);
            $midIndex = (int) floor($sampleSize / 2);
            $medianPrice = $sampleSize % 2 === 0
                ? round(($pricesPerSqm[$midIndex - 1] + $pricesPerSqm[$midIndex]) / 2, 2)
                : $pricesPerSqm[$midIndex];
            $minPrice = $pricesPerSqm[0];
            $maxPrice = $pricesPerSqm[$sampleSize - 1];

            $location = Location::find($neighborhoodId);
            $boundaryGeoJson = null;

            // Extract PostGIS polygon if PostgreSQL, or use pre-stored/simulated boundary coordinates
            if ($location) {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    $geoJsonRow = DB::selectOne(
                        'SELECT ST_AsGeoJSON(boundary::geometry) as geojson FROM locations WHERE id = ?',
                        [$location->id]
                    );
                    if (! empty($geoJsonRow?->geojson)) {
                        $boundaryGeoJson = json_decode($geoJsonRow->geojson, true);
                    }
                }

                // If no native polygon boundary present, synthesize valid polygon boundary around center point for choropleth mapping
                if (! $boundaryGeoJson && $location->latitude && $location->longitude) {
                    $lat = (float) $location->latitude;
                    $lng = (float) $location->longitude;
                    $offset = 0.006; // approx 650m bounding box
                    $boundaryGeoJson = [
                        'type' => 'Polygon',
                        'coordinates' => [
                            [
                                [$lng - $offset, $lat - $offset],
                                [$lng + $offset, $lat - $offset],
                                [$lng + $offset, $lat + $offset],
                                [$lng - $offset, $lat + $offset],
                                [$lng - $offset, $lat - $offset],
                            ],
                        ],
                    ];
                }
            }

            $priceTier = GisNeighborhoodHeatmap::tierFromPrice($avgPrice);

            $heatmap = GisNeighborhoodHeatmap::query()->updateOrCreate(
                [
                    'location_id' => $neighborhoodId,
                    'transaction_mode' => $mode,
                    'snapshot_date' => $snapshotDate,
                ],
                [
                    'sample_size' => $sampleSize,
                    'avg_price_per_sqm_etb' => $avgPrice,
                    'median_price_per_sqm_etb' => $medianPrice,
                    'min_price_per_sqm_etb' => $minPrice,
                    'max_price_per_sqm_etb' => $maxPrice,
                    'price_tier' => $priceTier,
                    'center_latitude' => $location?->latitude,
                    'center_longitude' => $location?->longitude,
                    'boundary_geojson' => $boundaryGeoJson,
                    'platform_asking_price_indicator' => true,
                ]
            );

            $heatmaps->push($heatmap);
        }

        return $heatmaps;
    }

    /**
     * Build standard GeoJSON FeatureCollection for frontend map rendering.
     */
    public function toGeoJson(string $transactionMode = 'BUY'): array
    {
        $mode = strtoupper($transactionMode);
        $records = GisNeighborhoodHeatmap::query()
            ->with('location')
            ->where('transaction_mode', $mode)
            ->whereDate('snapshot_date', now()->toDateString())
            ->get();

        if ($records->isEmpty()) {
            $this->aggregate($mode);
            $records = GisNeighborhoodHeatmap::query()
                ->with('location')
                ->where('transaction_mode', $mode)
                ->whereDate('snapshot_date', now()->toDateString())
                ->get();
        }

        $features = $records->map(fn ($r) => $r->toGeoJsonFeature())->all();

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'metadata' => [
                'transaction_mode' => $mode,
                'snapshot_date' => now()->toDateString(),
                'platform_asking_price_indicator' => true,
                'attribution' => 'Platform-Derived Market Indicator (SRS Sections 37 & 127): Values reflect active listing asking prices and do not represent verified government transaction registry prices.',
            ],
        ];
    }
}

