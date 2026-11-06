<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\GIS\Services\NeighborhoodHeatmapAggregationService;
use App\Domains\Subscription\Services\SubscriptionEntitlementService;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GisHeatmapApiController extends Controller
{
    public function __construct(
        private readonly NeighborhoodHeatmapAggregationService $heatmapService,
        private readonly SubscriptionEntitlementService $entitlementService
    ) {}

    /**
     * Get GeoJSON FeatureCollection for neighborhood ETB/m² asking price heatmaps.
     * Gated by subscription entitlement: property.advanced_gis
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Subscription Entitlement Gate
        if (! $user) {
            return response()->json([
                'status' => 'unauthenticated',
                'error' => 'auth_required',
                'message' => 'Please sign in to access advanced neighborhood geospatial heatmaps.',
                'upgrade_url' => route('login'),
            ], 401);
        }

        try {
            $this->entitlementService->validateAdvancedGisAccess($user);
        } catch (DomainException $e) {
            return response()->json([
                'status' => 'forbidden',
                'error' => 'upgrade_required',
                'message' => $e->getMessage(),
                'upgrade_url' => route('subscriptions.index'),
            ], 403);
        }

        $transactionMode = strtoupper($request->query('transaction_mode', 'BUY'));
        if (! in_array($transactionMode, ['BUY', 'RENT', 'LEASE'], true)) {
            $transactionMode = 'BUY';
        }

        if ($request->boolean('recalculate')) {
            $this->heatmapService->aggregate($transactionMode);
        }

        $geojson = $this->heatmapService->toGeoJson($transactionMode);

        return response()->json($geojson, 200);
    }
}

