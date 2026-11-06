<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Listing\Enums\ListingStatus;
use App\Domains\Listing\Models\Listing;
use App\Domains\Moderation\Actions\ModerateListingAction;
use App\Domains\Moderation\Models\FraudSignal;
use App\Domains\Moderation\Models\PropertyReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ListingModerationController extends Controller
{
    /**
     * Display the listing moderation workbench (SRS Section 134).
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->input('status', 'pending_review');

        $listings = Listing::query()
            ->when($statusFilter === 'all', fn ($q) => $q, fn ($q) => $q->where('status', $statusFilter))
            ->with(['property.owner', 'property.category', 'property.propertyType', 'property.city', 'property.subcity'])
            ->withCount(['fraudSignals as open_signals_count' => fn ($q) => $q->where('status', 'open')])
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $openFraudSignals = FraudSignal::query()
            ->where('status', 'open')
            ->with(['listing.property', 'matchedProperty', 'matchedListing'])
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        $openReports = PropertyReport::query()
            ->where('status', 'open')
            ->with(['listing.property', 'reporter'])
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        return view('admin.moderation.index', [
            'listings' => $listings,
            'openFraudSignals' => $openFraudSignals,
            'openReports' => $openReports,
            'currentStatus' => $statusFilter,
        ]);
    }

    /**
     * Display listing detail within the moderator workbench.
     */
    public function show(Listing $listing): View
    {
        $listing->load([
            'property.owner',
            'property.images',
            'property.documents.versions',
            'property.category',
            'property.propertyType',
            'property.city',
            'property.subcity',
            'fraudSignals.matchedProperty',
            'moderationActions.moderator',
        ]);

        $reports = PropertyReport::query()
            ->where('listing_id', $listing->id)
            ->with('reporter')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.moderation.show', [
            'listing' => $listing,
            'property' => $listing->property,
            'reports' => $reports,
        ]);
    }

    public function approve(Listing $listing, Request $request, ModerateListingAction $action): JsonResponse|RedirectResponse
    {
        $bypassQuota = $request->boolean('bypass_quota');
        $overrideReason = $request->input('override_reason');

        if ($bypassQuota) {
            // 1. Enforce dedicated quota override authorization (SRS Module 33 & 141)
            if (! $action->canOverrideSubscriptionQuota($request->user())) {
                app(\App\Domains\Administration\Services\AuditService::class)->log(
                    action: 'listing_subscription_quota_override_denied',
                    auditable: $listing,
                    oldValues: [
                        'listing_id' => $listing->id,
                        'listing_code' => $listing->listing_code,
                        'status' => $listing->status instanceof \BackedEnum ? $listing->status->value : (string) $listing->status,
                        'attempted_action' => 'bypass_quota',
                    ],
                    newValues: [
                        'attempted_by_user_id' => $request->user()->id,
                        'attempted_by_roles' => $request->user()->roles->pluck('slug')->toArray(),
                        'denial_reason' => 'missing_override_permission',
                    ],
                    reason: 'Moderator lacks listings.moderate.override_subscription_quota permission',
                    userId: $request->user()->id
                );

                abort(403, 'Unauthorized. You do not possess permission to override subscription listing quotas.');
            }

            // 2. Validate mandatory non-empty administrative override reason
            if (empty($overrideReason) || mb_strlen(trim((string) $overrideReason)) < 5 || mb_strlen((string) $overrideReason) > 500) {
                app(\App\Domains\Administration\Services\AuditService::class)->log(
                    action: 'listing_subscription_quota_override_rejected',
                    auditable: $listing,
                    oldValues: [
                        'listing_id' => $listing->id,
                        'listing_code' => $listing->listing_code,
                        'attempted_action' => 'bypass_quota',
                    ],
                    newValues: [
                        'attempted_by_user_id' => $request->user()->id,
                        'denial_reason' => 'missing_or_invalid_override_reason',
                        'submitted_reason' => $overrideReason,
                    ],
                    reason: 'Administrative override reason is required (5-500 characters)',
                    userId: $request->user()->id
                );

                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'A documented administrative override reason (5-500 characters) is required to bypass listing quotas.',
                    ], 422);
                }

                return back()->with('error', 'A documented administrative override reason (5-500 characters) is required to bypass listing quotas.');
            }
        }

        try {
            $updated = $action->execute(
                moderator: $request->user(),
                listing: $listing,
                decision: 'approve',
                bypassSubscriptionQuota: $bypassQuota,
                overrideReason: $bypassQuota ? trim((string) $overrideReason) : null
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (\DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Failed to approve listing: ' . $e->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Failed to approve listing: ' . $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $bypassQuota
                    ? 'Listing approved with administrative quota override.'
                    : 'Listing approved and published successfully.',
                'listing' => $updated,
            ]);
        }

        return back()->with('success', $bypassQuota
            ? 'Listing approved with administrative quota override.'
            : 'Listing approved and published successfully.');
    }

    public function reject(Listing $listing, Request $request, ModerateListingAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:48'],
            'feedback_to_seller' => ['required', 'string', 'max:2000'],
        ]);

        $updated = $action->execute(
            moderator: $request->user(),
            listing: $listing,
            decision: 'reject',
            reasonCode: $validated['reason_code'] ?? 'listing_policy_violation',
            feedbackToSeller: $validated['feedback_to_seller']
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Listing rejected.',
                'listing' => $updated,
            ]);
        }

        return back()->with('success', 'Listing has been rejected.');
    }

    public function suspend(Listing $listing, Request $request, ModerateListingAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:48'],
            'feedback_to_seller' => ['required', 'string', 'max:2000'],
        ]);

        $updated = $action->execute(
            moderator: $request->user(),
            listing: $listing,
            decision: 'suspend',
            reasonCode: $validated['reason_code'] ?? 'fraud_investigation',
            feedbackToSeller: $validated['feedback_to_seller']
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Listing suspended pending investigation.',
                'listing' => $updated,
            ]);
        }

        return back()->with('success', 'Listing suspended pending investigation.');
    }

    public function requestCorrection(Listing $listing, Request $request, ModerateListingAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'feedback_to_seller' => ['required', 'string', 'max:2000'],
        ]);

        $updated = $action->execute(
            moderator: $request->user(),
            listing: $listing,
            decision: 'request_correction',
            reasonCode: 'correction_required',
            feedbackToSeller: $validated['feedback_to_seller']
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Correction requested from seller.',
                'listing' => $updated,
            ]);
        }

        return back()->with('success', 'Correction requested from seller.');
    }

    public function resolveSignal(FraudSignal $fraudSignal, Request $request, ModerateListingAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:dismissed,confirmed_fraud'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $action->resolveFraudSignal(
            moderator: $request->user(),
            signal: $fraudSignal,
            resolution: $validated['resolution'],
            notes: $validated['notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Fraud signal resolved successfully.',
                'signal' => $updated,
            ]);
        }

        return back()->with('success', 'Fraud signal resolved successfully.');
    }

    public function reviewReport(PropertyReport $report, Request $request, ModerateListingAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:investigating,resolved,dismissed'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $action->reviewPropertyReport(
            moderator: $request->user(),
            report: $report,
            decision: $validated['decision'],
            notes: $validated['notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Property report updated successfully.',
                'report' => $updated,
            ]);
        }

        return back()->with('success', 'Property report updated successfully.');
    }
}
