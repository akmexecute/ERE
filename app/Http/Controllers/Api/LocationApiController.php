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
        $parentSlug = $request->query('parent_slug');
        $parentCode = $request->query('parent_code');
        $isRoot = $request->boolean('root') || $parentId === 'root' || ($parentId === null && $parentSlug === null && $parentCode === null);

        $resolvedParentId = null;

        if ($isRoot) {
            $rootCountry = Location::getRootCountry();
            $resolvedParentId = $rootCountry?->id;
        } elseif ($parentSlug) {
            $resolvedParentId = Location::query()->where('slug', $parentSlug)->value('id');
        } elseif ($parentCode) {
            $resolvedParentId = Location::query()->where('code', $parentCode)->value('id');
        } elseif (is_numeric($parentId)) {
            $resolvedParentId = (int) $parentId;
        }

        if (! $resolvedParentId) {
            return response()->json(['data' => []]);
        }

        $parent = Location::query()->find($resolvedParentId);
        if (! $parent || ! $parent->is_active) {
            return response()->json(['data' => []]);
        }

        // Safeguard: Ensure no ancestor in hierarchy is inactive
        if ($parent->ancestry_path && $parent->ancestry_path !== '/') {
            $ancestorIds = array_values(array_filter(explode('/', trim($parent->ancestry_path, '/')), 'is_numeric'));
            if (! empty($ancestorIds)) {
                $hasInactiveAncestor = Location::query()
                    ->whereIn('id', $ancestorIds)
                    ->where('is_active', false)
                    ->exists();

                if ($hasInactiveAncestor) {
                    return response()->json(['data' => []]);
                }
            }
        }

        $children = Location::query()
            ->where('parent_id', $resolvedParentId)
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
