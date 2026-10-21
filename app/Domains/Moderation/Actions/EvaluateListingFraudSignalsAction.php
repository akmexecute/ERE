<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Actions;

use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use Illuminate\Support\Facades\DB;

final readonly class EvaluateListingFraudSignalsAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(Listing $listing, Property $property): array
    {
        $signals = [];

        // 1. Spatial + Area Duplicate Check (within 50m and ±8% area on same property_type_id)
        if ($property->latitude && $property->longitude) {
            $areaCol = ((float) $property->land_area_sqm > 0) ? 'land_area_sqm' : 'building_area_sqm';
            $areaVal = (float) ($property->{$areaCol} ?? 0);

            if ($areaVal > 0 && DB::connection()->getDriverName() === 'pgsql') {
                $spatialMatch = DB::table('properties')
                    ->where('id', '!=', $property->id)
                    ->where('property_type_id', $property->property_type_id)
                    ->whereBetween($areaCol, [$areaVal * 0.92, $areaVal * 1.08])
                    ->whereRaw(
                        'ST_DWithin(coordinates, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, 50)',
                        [$property->longitude, $property->latitude]
                    )
                    ->first();

                if ($spatialMatch) {
                    $signals[] = $this->recordSignal(
                        listingId: $listing->id,
                        propertyId: $property->id,
                        signalType: 'spatial_duplicate',
                        severity: 'high',
                        matchedPropertyId: (int) $spatialMatch->id,
                        confidence: 88,
                        evidence: [
                            'reason' => 'Property within 50m with matching type and ±8% area.',
                            'matched_property_code' => $spatialMatch->property_code,
                        ]
                    );
                }
            }
        }

        // 2. Perceptual Image Hash (dHash) Duplicate Check across other properties
        $myHashes = DB::table('property_images')
            ->where('property_id', $property->id)
            ->whereNotNull('perceptual_hash')
            ->pluck('perceptual_hash')
            ->all();

        if ($myHashes !== []) {
            $candidateImages = DB::table('property_images')
                ->where('property_id', '!=', $property->id)
                ->whereNotNull('perceptual_hash')
                ->select(['property_id', 'perceptual_hash', 'original_sanitized_path'])
                ->limit(500)
                ->get();

            foreach ($myHashes as $myHash) {
                foreach ($candidateImages as $candidate) {
                    $distance = self::hammingDistance64Hex((string) $myHash, (string) $candidate->perceptual_hash);
                    if ($distance <= 5) {
                        $signals[] = $this->recordSignal(
                            listingId: $listing->id,
                            propertyId: $property->id,
                            signalType: 'image_hash_match',
                            severity: $distance <= 2 ? 'critical' : 'high',
                            matchedPropertyId: (int) $candidate->property_id,
                            confidence: max(75, 100 - ($distance * 4)),
                            evidence: [
                                'hamming_distance' => $distance,
                                'source_hash' => $myHash,
                                'matched_hash' => $candidate->perceptual_hash,
                            ]
                        );
                        break 2;
                    }
                }
            }
        }

        // 3. Statistical Price per m² Anomaly Detection (<25% or >400% of local average)
        if ($listing->price_per_sqm_etb && $property->city_id) {
            $avgSqm = (float) DB::table('listings')
                ->join('properties', 'properties.id', '=', 'listings.property_id')
                ->where('listings.id', '!=', $listing->id)
                ->where('listings.transaction_mode', $listing->transaction_mode)
                ->where('listings.status', 'published')
                ->where('properties.city_id', $property->city_id)
                ->where('properties.property_type_id', $property->property_type_id)
                ->avg('listings.price_per_sqm_etb');

            $currentSqm = (float) $listing->price_per_sqm_etb;
            if ($avgSqm > 0 && ($currentSqm < ($avgSqm * 0.25) || $currentSqm > ($avgSqm * 4.0))) {
                $signals[] = $this->recordSignal(
                    listingId: $listing->id,
                    propertyId: $property->id,
                    signalType: 'price_anomaly',
                    severity: 'medium',
                    matchedPropertyId: null,
                    confidence: 78,
                    evidence: [
                        'listing_price_per_sqm_etb' => $currentSqm,
                        'city_benchmark_price_per_sqm_etb' => round($avgSqm, 2),
                        'ratio' => round($currentSqm / $avgSqm, 2),
                    ]
                );
            }
        }

        return $signals;
    }

    /**
     * Computes 64-bit bitwise Hamming distance between two 16-char hexadecimal dHash strings.
     */
    public static function hammingDistance64Hex(string $hexA, string $hexB): int
    {
        $a = str_pad(strtolower(trim($hexA)), 16, '0', STR_PAD_LEFT);
        $b = str_pad(strtolower(trim($hexB)), 16, '0', STR_PAD_LEFT);

        $distance = 0;
        for ($i = 0; $i < 16; $i++) {
            $xor = hexdec($a[$i]) ^ hexdec($b[$i]);
            // Count set bits in 4-bit nibble
            $distance += substr_count(decbin($xor), '1');
        }

        return $distance;
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function recordSignal(
        int $listingId,
        int $propertyId,
        string $signalType,
        string $severity,
        ?int $matchedPropertyId,
        int $confidence,
        array $evidence
    ): array {
        $payload = [
            'listing_id' => $listingId,
            'property_id' => $propertyId,
            'signal_type' => $signalType,
            'severity' => $severity,
            'matched_property_id' => $matchedPropertyId,
            'confidence_score' => $confidence,
            'evidence_json' => json_encode($evidence),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('fraud_signals')->insert($payload);

        return $payload;
    }
}
