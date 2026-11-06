<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Queries;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Enums\PropertyStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class SearchMarketplaceListingsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = Listing::query()
            ->select('listings.*')
            ->join('properties', 'properties.id', '=', 'listings.property_id')
            ->with(['property.images', 'property.owner', 'property.propertyType', 'property.city', 'property.subcity'])
            ->where('listings.status', ListingStatus::Published->value)
            ->whereNotIn('properties.status', [
                PropertyStatus::Draft->value,
                PropertyStatus::Inactive->value,
                PropertyStatus::Archived->value,
            ]);

        // 1. Transaction Mode (BUY, RENT, LEASE)
        if (! empty($filters['transaction_mode'])) {
            $query->where('listings.transaction_mode', strtoupper((string) $filters['transaction_mode']));
        }

        // 2. Location Hierarchy Filters
        foreach (['region_id', 'city_id', 'subcity_id', 'woreda_id', 'kebele_id', 'neighborhood_id'] as $locCol) {
            if (! empty($filters[$locCol])) {
                $query->where("properties.{$locCol}", (int) $filters[$locCol]);
            }
        }

        // 3. Price Range Filters (ETB)
        if (! empty($filters['min_price_etb'])) {
            $query->where('listings.price_etb', '>=', (float) $filters['min_price_etb']);
        }
        if (! empty($filters['max_price_etb'])) {
            $query->where('listings.price_etb', '<=', (float) $filters['max_price_etb']);
        }

        // 4. Property Specifications (Category, Type, Area, Bedrooms, Bathrooms, Floor, Parking, Amenities)
        if (! empty($filters['category_id'])) {
            $query->where('properties.category_id', (int) $filters['category_id']);
        } elseif (! empty($filters['category_code'])) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->select(DB::raw(1))
                    ->from('property_categories')
                    ->whereColumn('property_categories.id', 'properties.category_id')
                    ->where('property_categories.code', $filters['category_code']);
            });
        }

        if (! empty($filters['property_type_id'])) {
            $query->where('properties.property_type_id', (int) $filters['property_type_id']);
        }
        if (! empty($filters['min_bedrooms'])) {
            $query->where('properties.bedrooms', '>=', (int) $filters['min_bedrooms']);
        }
        if (! empty($filters['min_bathrooms'])) {
            $query->where('properties.bathrooms', '>=', (int) $filters['min_bathrooms']);
        }
        if (! empty($filters['amenities'])) {
            $amenitySlugs = (array) $filters['amenities'];
            foreach ($amenitySlugs as $amSlug) {
                $query->whereExists(function ($sub) use ($amSlug) {
                    $sub->select(DB::raw(1))
                        ->from('property_amenity_pivot')
                        ->join('property_amenities', 'property_amenities.id', '=', 'property_amenity_pivot.amenity_id')
                        ->whereColumn('property_amenity_pivot.property_id', 'properties.id')
                        ->where('property_amenities.slug', $amSlug)
                        ->where('property_amenities.is_active', true);
                });
            }
        }
        if (! empty($filters['min_parking'])) {
            $query->where('properties.parking_spaces', '>=', (int) $filters['min_parking']);
        }
        if (! empty($filters['min_area_sqm'])) {
            $minArea = (float) $filters['min_area_sqm'];
            $query->where(function (Builder $q) use ($minArea): void {
                $q->where('properties.building_area_sqm', '>=', $minArea)
                    ->orWhere('properties.land_area_sqm', '>=', $minArea);
            });
        }

        // 5. Verification Filters (SRS Section 16: verified seller, verified company, documents submitted)
        if (! empty($filters['min_verification_level'])) {
            $query->where('properties.verification_level', '>=', (int) $filters['min_verification_level']);
        }

        // 6. Map Viewport Bounding Box Search (SRS Section 13: search visible map area)
        if (isset($filters['ne_lat'], $filters['ne_lng'], $filters['sw_lat'], $filters['sw_lng'])) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->whereRaw(
                    'properties.coordinates && ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography',
                    [
                        (float) $filters['sw_lng'],
                        (float) $filters['sw_lat'],
                        (float) $filters['ne_lng'],
                        (float) $filters['ne_lat'],
                    ]
                );
            } else {
                $minLat = min((float) $filters['sw_lat'], (float) $filters['ne_lat']);
                $maxLat = max((float) $filters['sw_lat'], (float) $filters['ne_lat']);
                $minLng = min((float) $filters['sw_lng'], (float) $filters['ne_lng']);
                $maxLng = max((float) $filters['sw_lng'], (float) $filters['ne_lng']);

                $query->whereBetween('properties.latitude', [$minLat, $maxLat])
                    ->whereBetween('properties.longitude', [$minLng, $maxLng]);
            }
        }

        // 7. PostGIS Radius Search (SRS Section 13: search within radius)
        if (isset($filters['center_lat'], $filters['center_lng'], $filters['radius_meters'])) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->whereRaw(
                    'ST_DWithin(properties.coordinates, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                    [
                        (float) $filters['center_lng'],
                        (float) $filters['center_lat'],
                        (int) $filters['radius_meters'],
                    ]
                );
            } else {
                $lat = (float) $filters['center_lat'];
                $lng = (float) $filters['center_lng'];
                $radius = (float) $filters['radius_meters'];
                $latDelta = $radius / 111000.0;
                $lngDelta = $radius / (111000.0 * max(cos(deg2rad($lat)), 0.01));
                $query->whereBetween('properties.latitude', [$lat - $latDelta, $lat + $latDelta])
                    ->whereBetween('properties.longitude', [$lng - $lngDelta, $lng + $lngDelta]);
            }
        }

        // 8. Sorting (SRS Section 17 & Module 33.5: Database-Configured Promotion Ranking Weights)
        $sort = (string) ($filters['sort'] ?? 'relevance');
        if ($sort === 'relevance' || empty($filters['sort'])) {
            $query->leftJoin('listing_promotions', function ($join): void {
                $join->on('listing_promotions.listing_id', '=', 'listings.id')
                    ->where('listing_promotions.status', '=', 'active')
                    ->where('listing_promotions.expires_at', '>', now());
            });
            $query->orderByRaw('COALESCE(listing_promotions.ranking_weight, CASE WHEN listings.is_featured THEN 10 ELSE 0 END) DESC')
                ->orderByDesc('properties.verification_level')
                ->orderByDesc('listings.published_at');
        } else {
            match ($sort) {
                'newest' => $query->orderByDesc('listings.published_at'),
                'price_asc' => $query->orderBy('listings.price_etb'),
                'price_desc' => $query->orderByDesc('listings.price_etb'),
                'largest' => $query->orderByRaw('COALESCE(properties.land_area_sqm, properties.building_area_sqm, 0) DESC'),
                'smallest' => $query->orderByRaw('COALESCE(properties.building_area_sqm, properties.land_area_sqm, 0) ASC'),
                'most_viewed' => $query->orderByDesc('listings.views_count'),
                'most_saved' => $query->orderByDesc('listings.favorites_count'),
                'price_per_sqm_asc' => $query->whereNotNull('listings.price_per_sqm_etb')->orderBy('listings.price_per_sqm_etb'),
                default => $query->orderByDesc('listings.is_featured')->orderByDesc('properties.verification_level')->orderByDesc('listings.published_at'),
            };
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
