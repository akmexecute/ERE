<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Actions;

use App\Support\Money\EtbFormatter;

final readonly class ComparePropertiesMatrixAction
{
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

        $highestVerif = -1;
        $highestVerifId = null;

        $normalized = [];

        foreach ($items as $row) {
            $id = (int) $row['id'];
            $price = (float) $row['price_etb'];
            $area = (float) ($row['area_sqm'] ?? 0);
            $pricePerSqm = $row['price_per_sqm_etb'] ?? EtbFormatter::calculatePricePerSqm($price, $area);
            $verif = (int) ($row['verification_level'] ?? 0);

            if ($lowestPrice === null || $price < $lowestPrice) {
                $lowestPrice = $price;
                $lowestPriceId = $id;
            }

            if ($pricePerSqm !== null && ($lowestSqm === null || $pricePerSqm < $lowestSqm)) {
                $lowestSqm = $pricePerSqm;
                $lowestSqmId = $id;
            }

            if ($largestArea === null || $area > $largestArea) {
                $largestArea = $area;
                $largestAreaId = $id;
            }

            if ($verif > $highestVerif) {
                $highestVerif = $verif;
                $highestVerifId = $id;
            }

            $normalized[] = [
                'id' => $id,
                'property_code' => $row['property_code'] ?? '',
                'title' => $row['title'] ?? '',
                'location_label' => $row['location_label'] ?? '',
                'price_etb' => $price,
                'price_formatted' => EtbFormatter::format($price),
                'price_per_sqm_etb' => $pricePerSqm,
                'price_per_sqm_formatted' => $pricePerSqm ? EtbFormatter::format($pricePerSqm, true).' / m²' : 'N/A',
                'area_sqm' => $area,
                'bedrooms' => (int) ($row['bedrooms'] ?? 0),
                'bathrooms' => (int) ($row['bathrooms'] ?? 0),
                'parking_spaces' => (int) ($row['parking_spaces'] ?? 0),
                'verification_level' => $verif,
                'amenities' => $row['amenities'] ?? [],
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
