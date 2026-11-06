<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Marketplace\Actions\ComparePropertiesMatrixAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PropertyComparisonController extends Controller
{
    public function __invoke(Request $request, ComparePropertiesMatrixAction $action): View|JsonResponse
    {
        $rawIds = $request->input('ids');
        if (is_string($rawIds)) {
            $ids = explode(',', $rawIds);
        } elseif (is_array($rawIds)) {
            $ids = $rawIds;
        } else {
            $ids = [];
        }

        $matrix = $action->executeFromDatabase($ids);

        if ($request->wantsJson()) {
            return response()->json($matrix);
        }

        return view('marketplace.compare', [
            'matrix' => $matrix,
            'items' => $matrix['items'],
            'highlights' => $matrix['highlights'],
        ]);
    }
}
