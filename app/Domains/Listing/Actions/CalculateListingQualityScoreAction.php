<?php

declare(strict_types=1);

namespace App\Domains\Listing\Actions;

use App\Domains\Property\Models\Property;

final readonly class CalculateListingQualityScoreAction
{
    /**
     * @param  array<int, string>  $uploadedRoomTags
     * @return array{score: int, missing_fields: array<int, string>, breakdown: array<string, bool>}
     */
    public function execute(
        Property $property,
        float $priceEtb,
        int $photoCount,
        array $uploadedRoomTags = [],
        int $amenityCount = 0,
        int $documentCount = 0
    ): array {
        $score = 0;
        $missing = [];
        $breakdown = [];

        // 1. Photos (25 points: >= 5 photos + key room tags per SRS Section 202)
        if ($photoCount >= 5) {
            $score += 20;
            $breakdown['photos'] = true;
        } elseif ($photoCount >= 1) {
            $score += 10;
            $breakdown['photos'] = false;
            $missing[] = 'Upload at least 5 clear photos (living room, bedrooms, kitchen, bathroom, exterior).';
        } else {
            $breakdown['photos'] = false;
            $missing[] = 'Add property photos.';
        }

        $requiredTags = ['exterior', 'living_room', 'bedroom', 'kitchen', 'bathroom'];
        $matchedTags = count(array_intersect($requiredTags, $uploadedRoomTags));
        if ($matchedTags >= 3) {
            $score += 5;
        }

        // 2. Description (20 points)
        $descLength = mb_strlen(trim((string) ($property->description_en ?? $property->description_am ?? '')));
        if ($descLength >= 120) {
            $score += 20;
            $breakdown['description'] = true;
        } else {
            $score += ($descLength >= 40 ? 10 : 0);
            $breakdown['description'] = false;
            $missing[] = 'Expand description to at least 120 characters covering compound, road access, and utilities.';
        }

        // 3. Location & Coordinates (15 points)
        if ($property->city_id && ($property->subcity_id || $property->neighborhood_id) && $property->latitude) {
            $score += 15;
            $breakdown['location'] = true;
        } else {
            $breakdown['location'] = false;
            $missing[] = 'Pin exact map coordinates and select Sub-city / Neighborhood.';
        }

        // 4. Price & Area Specifications (15 points)
        $hasArea = ((float) $property->land_area_sqm > 0) || ((float) $property->building_area_sqm > 0);
        if ($priceEtb > 0 && $hasArea) {
            $score += 15;
            $breakdown['price_and_area'] = true;
        } else {
            $breakdown['price_and_area'] = false;
            $missing[] = 'Specify valid ETB price and property area in square meters (m²).';
        }

        // 5. Amenities & Utilities (15 points)
        if ($amenityCount >= 3) {
            $score += 15;
            $breakdown['amenities'] = true;
        } else {
            $breakdown['amenities'] = false;
            $missing[] = 'Select at least 3 property amenities (e.g. Wi-Fi, Generator, Reserve Water Tanker, Security).';
        }

        // 6. Supporting Documents (10 points completeness bonus — separate from legal verification)
        if ($documentCount >= 1) {
            $score += 10;
            $breakdown['documents'] = true;
        } else {
            $breakdown['documents'] = false;
            $missing[] = 'Upload supporting ownership or lease documents to the private verification vault.';
        }

        return [
            'score' => min(100, $score),
            'missing_fields' => $missing,
            'breakdown' => $breakdown,
        ];
    }
}
