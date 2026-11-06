<?php

declare(strict_types=1);

namespace App\Domains\Moderation\Actions;

use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

            if ($areaVal > 0) {
                $spatialMatch = null;
                $driver = DB::connection()->getDriverName();

                if ($driver === 'pgsql') {
                    $spatialMatch = DB::table('properties')
                        ->where('id', '!=', $property->id)
                        ->where('property_type_id', $property->property_type_id)
                        ->whereBetween($areaCol, [$areaVal * 0.92, $areaVal * 1.08])
                        ->whereRaw(
                            'ST_DWithin(coordinates, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, 50)',
                            [$property->longitude, $property->latitude]
                        )
                        ->first();
                } else {
                    // SQLite / non-pgsql fallback: compute distance using Haversine formula
                    $candidates = DB::table('properties')
                        ->where('id', '!=', $property->id)
                        ->where('property_type_id', $property->property_type_id)
                        ->whereBetween($areaCol, [$areaVal * 0.92, $areaVal * 1.08])
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->get();

                    foreach ($candidates as $cand) {
                        $dist = self::haversineDistance(
                            (float) $property->latitude,
                            (float) $property->longitude,
                            (float) $cand->latitude,
                            (float) $cand->longitude
                        );
                        if ($dist <= 50.0) {
                            $spatialMatch = $cand;
                            break;
                        }
                    }
                }

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
            $matchFound = false;

            DB::table('property_images')
                ->where('property_id', '!=', $property->id)
                ->whereNotNull('perceptual_hash')
                ->select(['id', 'property_id', 'perceptual_hash', 'original_sanitized_path'])
                ->orderBy('id')
                ->chunk(200, function ($candidateImages) use (&$signals, &$matchFound, $myHashes, $listing, $property) {
                    foreach ($myHashes as $myHash) {
                        foreach ($candidateImages as $candidate) {
                            try {
                                $distance = self::hammingDistance64Hex((string) $myHash, (string) $candidate->perceptual_hash);
                            } catch (InvalidArgumentException) {
                                continue;
                            }

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
                                $matchFound = true;
                                return false; // Break chunk loop
                            }
                        }
                    }
                });
        }

        // 3. Statistical Price per m² Anomaly Detection (<25% or >400% of local median)
        if ($listing->price_per_sqm_etb && $property->city_id) {
            $prices = DB::table('listings')
                ->join('properties', 'properties.id', '=', 'listings.property_id')
                ->where('listings.id', '!=', $listing->id)
                ->where('listings.transaction_mode', $listing->transaction_mode)
                ->where('listings.status', 'published')
                ->where('properties.city_id', $property->city_id)
                ->where('properties.property_type_id', $property->property_type_id)
                ->where('listings.price_per_sqm_etb', '>', 0)
                ->orderBy('listings.price_per_sqm_etb')
                ->pluck('listings.price_per_sqm_etb')
                ->map(fn ($p) => (float) $p)
                ->all();

            // Require minimum sample size of at least 3 comparables for reliable statistical benchmark
            if (count($prices) >= 3) {
                $count = count($prices);
                $middle = (int) floor($count / 2);
                $medianSqm = ($count % 2 === 1)
                    ? $prices[$middle]
                    : ($prices[$middle - 1] + $prices[$middle]) / 2.0;

                $currentSqm = (float) $listing->price_per_sqm_etb;
                if ($medianSqm > 0 && ($currentSqm < ($medianSqm * 0.25) || $currentSqm > ($medianSqm * 4.0))) {
                    $signals[] = $this->recordSignal(
                        listingId: $listing->id,
                        propertyId: $property->id,
                        signalType: 'price_anomaly',
                        severity: 'high',
                        matchedPropertyId: null,
                        confidence: 78,
                        evidence: [
                            'listing_price_per_sqm_etb' => $currentSqm,
                            'city_benchmark_price_per_sqm_etb' => round($medianSqm, 2),
                            'local_median_price_per_sqm' => round($medianSqm, 2),
                            'ratio' => round($currentSqm / $medianSqm, 2),
                            'sample_size' => $count,
                        ]
                    );
                }
            }
        }

        return $signals;
    }

    /**
     * Computes 64-bit bitwise Hamming distance between two 16-char hexadecimal dHash strings.
     *
     * @throws InvalidArgumentException
     */
    public static function hammingDistance64Hex(string $hexA, string $hexB): int
    {
        $a = trim($hexA);
        $b = trim($hexB);

        if (strlen($a) !== 16 || strlen($b) !== 16 || ! ctype_xdigit($a) || ! ctype_xdigit($b)) {
            throw new InvalidArgumentException('Both perceptual hashes must be exactly 16 hexadecimal characters.');
        }

        $a = strtolower($a);
        $b = strtolower($b);

        $distance = 0;
        for ($i = 0; $i < 16; $i++) {
            $xor = hexdec($a[$i]) ^ hexdec($b[$i]);
            // Count set bits in 4-bit nibble
            $distance += substr_count(decbin($xor), '1');
        }

        return $distance;
    }

    /**
     * Computes great-circle distance in metres between two coordinate pairs using Haversine formula.
     */
    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0; // metres
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
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
        // Enforce confidence bounds (0..100)
        $clampedConfidence = max(0, min(100, $confidence));

        $existing = DB::table('fraud_signals')
            ->where('listing_id', $listingId)
            ->where('signal_type', $signalType)
            ->where(function ($q) use ($matchedPropertyId): void {
                if ($matchedPropertyId !== null) {
                    $q->where('matched_property_id', $matchedPropertyId);
                } else {
                    $q->whereNull('matched_property_id');
                }
            })
            ->where('status', 'open')
            ->first();

        if ($existing) {
            DB::table('fraud_signals')->where('id', $existing->id)->update([
                'severity' => $severity,
                'confidence_score' => $clampedConfidence,
                'evidence_json' => json_encode($evidence),
                'updated_at' => now(),
            ]);

            return [
                'id' => $existing->id,
                'listing_id' => $listingId,
                'property_id' => $propertyId,
                'signal_type' => $signalType,
                'severity' => $severity,
                'matched_property_id' => $matchedPropertyId,
                'confidence_score' => $clampedConfidence,
                'evidence' => $evidence,
                'evidence_json' => json_encode($evidence),
                'status' => 'open',
            ];
        }

        $payload = [
            'listing_id' => $listingId,
            'property_id' => $propertyId,
            'signal_type' => $signalType,
            'severity' => $severity,
            'matched_property_id' => $matchedPropertyId,
            'confidence_score' => $clampedConfidence,
            'evidence_json' => json_encode($evidence),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            $id = DB::table('fraud_signals')->insertGetId($payload);
            $payload['id'] = $id;
            $payload['evidence'] = $evidence;

            return $payload;
        } catch (\Illuminate\Database\QueryException $e) {
            // Concurrent race condition handled by database unique partial index.
            // Retrieve and reconcile the open signal created concurrently by the winning worker.
            $concurrentRow = DB::table('fraud_signals')
                ->where('listing_id', $listingId)
                ->where('signal_type', $signalType)
                ->where(function ($q) use ($matchedPropertyId): void {
                    if ($matchedPropertyId !== null) {
                        $q->where('matched_property_id', $matchedPropertyId);
                    } else {
                        $q->whereNull('matched_property_id');
                    }
                })
                ->where('status', 'open')
                ->first();

            if ($concurrentRow) {
                $existingEvidence = json_decode((string) ($concurrentRow->evidence_json ?? '[]'), true) ?: [];
                $mergedEvidence = array_merge($existingEvidence, $evidence);

                DB::table('fraud_signals')->where('id', $concurrentRow->id)->update([
                    'severity' => $severity,
                    'confidence_score' => max((int) $concurrentRow->confidence_score, $clampedConfidence),
                    'evidence_json' => json_encode($mergedEvidence),
                    'updated_at' => now(),
                ]);

                return [
                    'id' => $concurrentRow->id,
                    'listing_id' => $listingId,
                    'property_id' => $propertyId,
                    'signal_type' => $signalType,
                    'severity' => $severity,
                    'matched_property_id' => $matchedPropertyId,
                    'confidence_score' => max((int) $concurrentRow->confidence_score, $clampedConfidence),
                    'evidence' => $mergedEvidence,
                    'evidence_json' => json_encode($mergedEvidence),
                    'status' => 'open',
                ];
            }

            throw $e;
        }
    }
}
