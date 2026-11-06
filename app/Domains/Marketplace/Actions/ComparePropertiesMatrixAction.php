<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Actions;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Support\Money\EtbFormatter;

final readonly class ComparePropertiesMatrixAction
{
    /**
     * Fetch from database by listing IDs with strict status=published and visibility rules.
     *
     * @param array<int> $listingIds
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   highlights: array{
     *     lowest_price_id: ?int,
     *     lowest_price_per_sqm_id: ?int,
     *     largest_area_id: ?int,
     *     highest_verification_id: ?int
     *   }
     * }
     */
    public function executeFromDatabase(array $listingIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $listingIds))));
        $ids = array_slice($ids, 0, 4);

        if ($ids === []) {
            return $this->execute([]);
        }

        $listings = Listing::query()
            ->where(function ($q) use ($ids) {
                $q->whereIn('id', $ids)
                    ->orWhereIn('property_id', $ids);
            })
            ->where('status', ListingStatus::Published)
            ->with(['property.city', 'property.subcity', 'property.amenities', 'property.images'])
            ->get();

        // Preserve input ID order
        $ordered = [];
        $seenListingIds = [];
        foreach ($ids as $id) {
            $listing = $listings->first(fn ($l) => $l->id === $id || $l->property_id === $id);
            if ($listing && $listing->property && ! in_array($listing->id, $seenListingIds, true)) {
                $seenListingIds[] = $listing->id;
                $prop = $listing->property;
                $area = $prop->building_area_sqm ? (float) $prop->building_area_sqm : ($prop->land_area_sqm ? (float) $prop->land_area_sqm : null);
                $location = trim(($prop->subcity?->name_en ?? '').', '.($prop->city?->name_en ?? ''), ', ');

                $ordered[] = [
                    'id' => $listing->id,
                    'property_id' => $prop->id,
                    'property_code' => $prop->property_code,
                    'title' => $prop->title_en ?? $listing->slug,
                    'location_label' => $location ?: 'Ethiopia',
                    'price_etb' => (float) $listing->price_etb,
                    'price_per_sqm_etb' => $listing->price_per_sqm_etb ? (float) $listing->price_per_sqm_etb : null,
                    'area_sqm' => $area,
                    'bedrooms' => $prop->bedrooms,
                    'bathrooms' => $prop->bathrooms,
                    'parking_spaces' => $prop->parking_spaces,
                    'verification_level' => (int) $prop->verification_level,
                    'amenities' => $prop->amenities->pluck('name_en')->all(),
                    'primary_image' => $prop->images->first()?->image_path,
                ];
            }
        }

        return $this->execute($ordered);
    }

    /**
     * @param  array<int, array<string, mixed>>  $listingsData
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   highlights: array{
     *     lowest_price_id: ?int,
     *     lowest_price_per_sqm_id: ?int,
     *     largest_area_id: ?int,
     *     highest_verification_id: ?int
     *   }
     * }
     */
    public function execute(array $listingsData): array
    {
        $items = array_slice($listingsData, 0, 4);

        $lowestPrice = null;
        $lowestPriceId = null;

        $lowestSqm = null;
        $lowestSqmId = null;

        $largestArea = null;
        $largestAreaId = null;

        $highestVerif = 0;
        $highestVerifId = null;

        $normalized = [];

        foreach ($items as $row) {
            $id = (int) $row['id'];
            $price = isset($row['price_etb']) ? (float) $row['price_etb'] : 0.0;

            // Handle missing, null, zero or negative area without treating missing as zero
            $rawArea = $row['area_sqm'] ?? null;
            $area = ($rawArea !== null && (float) $rawArea > 0) ? (float) $rawArea : null;

            // Compute price per sqm only if area is a valid positive number
            $pricePerSqm = null;
            if (isset($row['price_per_sqm_etb']) && (float) $row['price_per_sqm_etb'] > 0) {
                $pricePerSqm = (float) $row['price_per_sqm_etb'];
            } elseif ($price > 0 && $area !== null) {
                $pricePerSqm = EtbFormatter::calculatePricePerSqm($price, $area);
            }

            $verif = (int) ($row['verification_level'] ?? 0);

            // 1. Lowest Asking Price (must be > 0; deterministic tie keeps first)
            if ($price > 0 && ($lowestPrice === null || $price < $lowestPrice)) {
                $lowestPrice = $price;
                $lowestPriceId = $id;
            }

            // 2. Lowest Price per SQM (must be valid positive; deterministic tie keeps first)
            if ($pricePerSqm !== null && $pricePerSqm > 0 && ($lowestSqm === null || $pricePerSqm < $lowestSqm)) {
                $lowestSqm = $pricePerSqm;
                $lowestSqmId = $id;
            }

            // 3. Largest Area (must be valid positive; deterministic tie keeps first)
            if ($area !== null && $area > 0 && ($largestArea === null || $area > $largestArea)) {
                $largestArea = $area;
                $largestAreaId = $id;
            }

            // 4. Highest Verification Level (must be > 0 to qualify; deterministic tie keeps first)
            if ($verif > 0 && $verif > $highestVerif) {
                $highestVerif = $verif;
                $highestVerifId = $id;
            }

            $normalized[] = [
                'id' => $id,
                'property_code' => $row['property_code'] ?? '',
                'title' => $row['title'] ?? '',
                'location_label' => $row['location_label'] ?? '',
                'price_etb' => $price,
                'price_formatted' => $price > 0 ? EtbFormatter::format($price) : 'N/A',
                'price_per_sqm_etb' => $pricePerSqm,
                'price_per_sqm_formatted' => $pricePerSqm ? EtbFormatter::format($pricePerSqm, true).' / m²' : 'N/A',
                'area_sqm' => $area,
                'area_formatted' => $area !== null ? number_format($area, 1).' m²' : 'N/A',
                'bedrooms' => isset($row['bedrooms']) ? (int) $row['bedrooms'] : null,
                'bathrooms' => isset($row['bathrooms']) ? (int) $row['bathrooms'] : null,
                'parking_spaces' => isset($row['parking_spaces']) ? (int) $row['parking_spaces'] : null,
                'verification_level' => $verif,
                'amenities' => $row['amenities'] ?? [],
                'primary_image' => $row['primary_image'] ?? null,
            ];
        }

        return [
            'items' => $normalized,
            'highlights' => [
                'lowest_price_id' => $lowestPriceId,
                'lowest_price_per_sqm_id' => $lowestSqmId,
                'largest_area_id' => $largestAreaId,
                'highest_verification_id' => $highestVerifId,
            ],
        ];
    }
}
