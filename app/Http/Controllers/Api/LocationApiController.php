<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\GIS\Actions\ReverseGeocodePointAction;
use App\Domains\Location\Models\Location;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationApiController extends Controller
{
    public function children(Request $request): JsonResponse
    {
        $parentId = $request->query('parent_id');
        if (! $parentId) {
            return response()->json(['data' => []]);
        }

        $children = Location::query()
            ->where('parent_id', $parentId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(fn (Location $loc): array => [
                'id' => $loc->id,
                'name' => $loc->localizedName(),
                'code' => $loc->code,
                'level' => $loc->level,
                'latitude' => $loc->latitude,
                'longitude' => $loc->longitude,
            ]);

        return response()->json(['data' => $children]);
    }

    public function reverseGeocode(Request $request, ReverseGeocodePointAction $reverseGeocode): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $chain = $reverseGeocode->execute(
            (float) $request->query('lat'),
            (float) $request->query('lng')
        );

        return response()->json(['data' => $chain]);
    }
}
