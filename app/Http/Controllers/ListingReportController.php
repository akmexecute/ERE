<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Listing\Models\Listing;
use App\Domains\Moderation\Actions\ReportPropertyAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ListingReportController extends Controller
{
    public function store(
        Listing $listing,
        Request $request,
        ReportPropertyAction $action
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'in:' . implode(',', ReportPropertyAction::ALLOWED_REASONS)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = $action->execute(
            listing: $listing,
            property: $listing->property,
            reasonCode: $validated['reason_code'],
            description: $validated['description'] ?? null,
            reporter: $request->user(),
            ipAddress: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Thank you for your report. Our trust and safety team will investigate.',
                'report_uuid' => $report->uuid,
            ], 201);
        }

        return back()->with('success', 'Thank you for your report. Our trust and safety team will investigate.');
    }
}
