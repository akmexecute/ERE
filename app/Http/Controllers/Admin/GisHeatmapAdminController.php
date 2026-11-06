<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\GIS\Models\GisNeighborhoodHeatmap;
use App\Domains\GIS\Services\NeighborhoodHeatmapAggregationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class GisHeatmapAdminController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super_admin') && ! $user->hasPermission('gis.view_advanced'))) {
            abort(403, 'Unauthorized access to GIS spatial heatmaps management.');
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $selectedMode = strtoupper($request->query('transaction_mode', 'BUY'));

        $heatmaps = GisNeighborhoodHeatmap::query()
            ->with('location')
            ->where('transaction_mode', $selectedMode)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('avg_price_per_sqm_etb')
            ->paginate(25);

        $totalLocationsCovered = GisNeighborhoodHeatmap::query()
            ->where('transaction_mode', $selectedMode)
            ->distinct('location_id')
            ->count('location_id');

        return view('admin.gis.heatmaps', [
            'heatmaps' => $heatmaps,
            'selectedMode' => $selectedMode,
            'totalLocationsCovered' => $totalLocationsCovered,
        ]);
    }

    public function recalculate(Request $request, NeighborhoodHeatmapAggregationService $service): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $mode = strtoupper($request->input('transaction_mode', 'BUY'));
        $aggregated = $service->aggregate($mode);

        return redirect()->route('admin.gis.heatmaps.index', ['transaction_mode' => $mode])
            ->with('success', "Recalculated asking-price heatmaps for [{$mode}]: {$aggregated->count()} neighborhood records updated.");
    }
}

