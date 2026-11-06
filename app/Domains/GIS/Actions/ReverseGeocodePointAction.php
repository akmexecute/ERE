<?php

declare(strict_types=1);

namespace App\Domains\GIS\Actions;

use App\Domains\Location\Models\Location;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ReverseGeocodePointAction
{
    /**
     * Reverse geocode a geographic point (lat, lng) to its Ethiopian administrative chain.
     *
     * @return array<string, array{id: int, code: string, name: string, level: string}>
     */
    public function execute(float $latitude, float $longitude): array
    {
        // 1. Strict coordinate validation
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException("Latitude must be between -90 and 90 degrees. Received: {$latitude}");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException("Longitude must be between -180 and 180 degrees. Received: {$longitude}");
        }

        $matched = null;

        if (DB::connection()->getDriverName() === 'pgsql') {
            // 2. Check exact polygon containment first
            $matched = Location::query()
                ->where('is_active', true)
                ->whereNotNull('boundary')
                ->whereRaw(
                    'ST_Covers(boundary, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
                    [$longitude, $latitude]
                )
                ->orderByRaw("CASE level
                    WHEN 'neighborhood' THEN 1
                    WHEN 'kebele' THEN 2
                    WHEN 'woreda' THEN 3
                    WHEN 'subcity' THEN 4
                    WHEN 'city' THEN 5
                    WHEN 'region' THEN 6
                    ELSE 7 END")
                ->first();

            // 3. Fallback to nearest center_point strictly within 15km (15,000 meters)
            if (! $matched) {
                $matched = Location::query()
                    ->where('is_active', true)
                    ->whereNotNull('center_point')
                    ->whereRaw(
                        'ST_DWithin(center_point, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, 15000)',
                        [$longitude, $latitude]
                    )
                    ->orderByRaw(
                        'ST_Distance(center_point, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) ASC',
                        [$longitude, $latitude]
                    )
                    ->first();
            }
        } else {
            // 4. Non-PostGIS test fallback with strict 15km threshold enforcement
            // 1 degree latitude ~= 111.32 km = 111,320m
            // 1 degree longitude at ~9 deg N ~= 111.32 * cos(9 deg) ~= 109.9 km = 109,900m
            $candidates = Location::query()
                ->where('is_active', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get();

            $minDistance = INF;
            $bestCandidate = null;

            foreach ($candidates as $loc) {
                $dLat = deg2rad($latitude - (float) $loc->latitude);
                $dLon = deg2rad($longitude - (float) $loc->longitude);
                $a = sin($dLat / 2) * sin($dLat / 2) +
                     cos(deg2rad((float) $loc->latitude)) * cos(deg2rad($latitude)) *
                     sin($dLon / 2) * sin($dLon / 2);
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                $distanceMeters = 6371000 * $c; // Earth radius in meters

                if ($distanceMeters < $minDistance) {
                    $minDistance = $distanceMeters;
                    $bestCandidate = $loc;
                }
            }

            // Strictly enforce the 15 km (15,000 m) threshold: if point is beyond 15 km, return no match!
            if ($bestCandidate && $minDistance <= 15000) {
                $matched = $bestCandidate;
            }
        }

        if (! $matched) {
            return [];
        }

        // 5. Walk up the administrative ancestry tree to return the full hierarchy chain
        $chain = [];
        $current = $matched;
        while ($current !== null) {
            $chain[$current->level] = [
                'id' => $current->id,
                'code' => $current->code,
                'name' => $current->localizedName(),
                'level' => $current->level,
            ];
            $current = $current->parent;
        }

        return array_reverse($chain, preserve_keys: true);
    }
}
