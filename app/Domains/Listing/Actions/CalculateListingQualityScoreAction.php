<?php

declare(strict_types=1);

namespace App\Domains\Listing\Actions;

use App\Domains\Property\Models\Property;

final readonly class CalculateListingQualityScoreAction
{
    /**
     * Calculates the authoritative Listing Quality Score (0–100%) and actionable guidance (SRS Section 165 & 203).
     *
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

        // 1. Photos (25 points: >= 5 photos [20 pts] + key room tags [5 pts] per SRS Section 202)
        $hasKeyTags = false;
        $requiredTags = ['exterior', 'living_room', 'bedroom', 'kitchen', 'bathroom', 'compound', 'site_plan'];
        $matchedTags = count(array_intersect($requiredTags, $uploadedRoomTags));

        if ($photoCount >= 5) {
            $score += 20;
            if ($matchedTags >= 3) {
                $score += 5;
                $hasKeyTags = true;
            }
            $breakdown['photos'] = true;
            if (! $hasKeyTags) {
                $missing[] = 'Tag photos with room types (living room, bedroom, kitchen, compound) for complete photo score.';
            }
        } elseif ($photoCount >= 1) {
            $score += 10;
            if ($matchedTags >= 2) {
                $score += 5;
            }
            $breakdown['photos'] = false;
            $missing[] = 'Upload at least 5 clear photos covering exterior, interior rooms, and compound.';
        } else {
            $breakdown['photos'] = false;
            $missing[] = 'Add property photos.';
        }

        // 2. Description (20 points: >= 120 chars)
        $descLength = mb_strlen(trim((string) ($property->description_en ?? $property->description_am ?? '')));
        if ($descLength >= 120) {
            $score += 20;
            $breakdown['description'] = true;
        } else {
            $score += ($descLength >= 40 ? 10 : 0);
            $breakdown['description'] = false;
            $missing[] = 'Expand property description to at least 120 characters covering access roads, utilities, and surroundings.';
        }

        // 3. Location & Coordinates (15 points: both lat AND lng must be present with valid hierarchy)
        $hasValidCoords = ($property->latitude !== null && $property->longitude !== null)
            && is_numeric($property->latitude) && is_numeric($property->longitude)
            && ((float) $property->latitude !== 0.0 || (float) $property->longitude !== 0.0);

        if ($property->city_id && ($property->subcity_id || $property->neighborhood_id) && $hasValidCoords) {
            $score += 15;
            $breakdown['location'] = true;
        } else {
            $breakdown['location'] = false;
            $missing[] = 'Pin exact map coordinates (latitude and longitude) and select Sub-city / Neighborhood.';
        }

        // 4. Price & Appropriate Area (15 points: respects authoritative area_basis)
        $areaBasis = $property->propertyType?->area_basis ?? 'building_area';
        $hasAppropriateArea = $areaBasis === 'land_area'
            ? ((float) $property->land_area_sqm > 0)
            : ((float) $property->building_area_sqm > 0);

        if ($priceEtb > 0 && $hasAppropriateArea) {
            $score += 15;
            $breakdown['price_and_area'] = true;
        } else {
            $breakdown['price_and_area'] = false;
            $areaName = $areaBasis === 'land_area' ? 'land area' : 'building floor area';
            $missing[] = "Specify a valid ETB price and authoritative {$areaName} in square meters (m²).";
        }

        // 5. Amenities & Infrastructure (15 points: >= 3 amenities)
        if ($amenityCount >= 3) {
            $score += 15;
            $breakdown['amenities'] = true;
        } else {
            $breakdown['amenities'] = false;
            $missing[] = 'Select at least 3 amenities (e.g. Wi-Fi, Backup Generator, Reserve Water Tanker, 24/7 Security).';
        }

        // 6. Supporting Documents (10 points completeness bonus — separate from legal verification)
        if ($documentCount >= 1) {
            $score += 10;
            $breakdown['documents'] = true;
        } else {
            $breakdown['documents'] = false;
            $missing[] = 'Upload supporting ownership or lease documents (Carta / Title Deed) to the private verification vault.';
        }

        return [
            'score' => min(100, $score),
            'missing_fields' => $missing,
            'breakdown' => $breakdown,
        ];
    }
}
