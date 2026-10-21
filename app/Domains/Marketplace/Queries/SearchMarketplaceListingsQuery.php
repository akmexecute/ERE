<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Queries;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
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
            ->where('listings.status', ListingStatus::Published->value);

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

        // 4. Property Specifications (Type, Area, Bedrooms, Bathrooms, Floor, Parking)
        if (! empty($filters['property_type_id'])) {
            $query->where('properties.property_type_id', (int) $filters['property_type_id']);
        }
        if (! empty($filters['min_bedrooms'])) {
            $query->where('properties.bedrooms', '>=', (int) $filters['min_bedrooms']);
        }
        if (! empty($filters['min_bathrooms'])) {
            $query->where('properties.bathrooms', '>=', (int) $filters['min_bathrooms']);
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

        // 6. PostGIS Map Viewport Bounding Box Search (SRS Section 13: search visible map area)
        if (
            isset($filters['ne_lat'], $filters['ne_lng'], $filters['sw_lat'], $filters['sw_lng'])
            && DB::connection()->getDriverName() === 'pgsql'
        ) {
            $query->whereRaw(
                'properties.coordinates && ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography',
                [
                    (float) $filters['sw_lng'],
                    (float) $filters['sw_lat'],
                    (float) $filters['ne_lng'],
                    (float) $filters['ne_lat'],
                ]
            );
        }

        // 7. PostGIS Radius Search (SRS Section 13: search within radius)
        if (
            isset($filters['center_lat'], $filters['center_lng'], $filters['radius_meters'])
            && DB::connection()->getDriverName() === 'pgsql'
        ) {
            $query->whereRaw(
                'ST_DWithin(properties.coordinates, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                [
                    (float) $filters['center_lng'],
                    (float) $filters['center_lat'],
                    (int) $filters['radius_meters'],
                ]
            );
        }

        // 8. Sorting (SRS Section 17)
        $sort = (string) ($filters['sort'] ?? 'relevance');
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

        return $query->paginate($perPage)->withQueryString();
    }
}
