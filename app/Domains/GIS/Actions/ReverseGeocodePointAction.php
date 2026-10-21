<?php

declare(strict_types=1);

namespace App\Domains\GIS\Actions;

use App\Domains\Location\Models\Location;
use Illuminate\Support\Facades\DB;

final readonly class ReverseGeocodePointAction
{
    /**
     * @return array<string, array{id: int, code: string, name: string, level: string}>
     */
    public function execute(float $latitude, float $longitude): array
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            // 1. Check exact polygon containment first
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

            // 2. Fallback to nearest center_point within 15km
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
            // Non-PostGIS test fallback using Euclidean approximation
            $matched = Location::query()
                ->where('is_active', true)
                ->whereNotNull('latitude')
                ->orderByRaw('((latitude - ?) * (latitude - ?) + (longitude - ?) * (longitude - ?)) ASC', [
                    $latitude, $latitude, $longitude, $longitude,
                ])
                ->first();
        }

        if (! $matched) {
            return [];
        }

        // Walk up the ancestry tree to return the full administrative chain
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
