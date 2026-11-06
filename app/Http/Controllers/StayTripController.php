<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Cleaning\Actions\TransitionCleaningTaskAction;
use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\Dispute\Actions\SubmitDamageClaimAction;
use App\Domains\Review\Actions\SubmitStayReviewAction;
use App\Domains\Stay\Actions\ProcessStayCheckInAction;
use App\Domains\Stay\Actions\ProcessStayCheckOutAction;
use App\Domains\Stay\Models\HouseManual;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class StayTripController extends Controller
{
    public function show(string $uuid, Request $request): View
    {
        $user = $request->user();

        $booking = Booking::query()
            ->where('uuid', $uuid)
            ->with([
                'property',
                'listing',
                'stayProfile',
                'unit',
                'host.user',
                'guest',
                'checkIn',
                'checkOut',
                'securityDeposit.damageClaims',
                'reviews',
            ])
            ->firstOrFail();

        // 1. Authorize: guest, host, or staff
        $isGuest = (int) $booking->guest_user_id === (int) $user->id;
        $isHost = (int) $booking->host?->user_id === (int) $user->id || (int) $booking->property?->owner_id === (int) $user->id;
        $isStaff = $user->isStaff();

        if (! $isGuest && ! $isHost && ! $isStaff) {
            abort(403, 'Unauthorized access to reservation details.');
        }

        // 2. Sensitive Credential Access Policy (Wi-Fi, Smart Lock PIN, Access Codes)
        // Only accessible to guest within permitted access window (from 24h prior to check-in until checkout)
        $now = CarbonImmutable::now();
        $checkInWindowStart = CarbonImmutable::parse($booking->check_in_date)->subHours(24);
        $checkOutTime = CarbonImmutable::parse($booking->check_out_date)->addHours(12);

        $isWithinStayWindow = $now->greaterThanOrEqualTo($checkInWindowStart) && $now->lessThanOrEqualTo($checkOutTime);
        $isActiveStatus = in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Active], true);

        $canViewSensitiveCredentials = ($isHost || $isStaff || ($isGuest && $isActiveStatus && $isWithinStayWindow && ! $booking->checkOut()->exists()));

        // Resolve House Manual
        $houseManual = HouseManual::query()
            ->where('stay_listing_profile_id', $booking->stay_listing_profile_id)
            ->first();

        return view('stays.trip', [
            'booking' => $booking,
            'user' => $user,
            'isGuest' => $isGuest,
            'isHost' => $isHost,
            'isStaff' => $isStaff,
            'canViewSensitiveCredentials' => $canViewSensitiveCredentials,
            'houseManual' => $houseManual,
        ]);
    }

    public function checkIn(string $uuid, Request $request, ProcessStayCheckInAction $action): RedirectResponse|JsonResponse
    {
        $booking = Booking::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'method' => ['nullable', 'string', 'in:guest_self,host_manual,qr_code,smart_lock_code'],
            'access_code' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $checkIn = $action->execute(
            booking: $booking,
            user: $request->user(),
            method: $validated['method'] ?? 'guest_self',
            accessCodeUsed: $validated['access_code'] ?? null,
            notes: $validated['notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Check-in recorded successfully. Digital house manual unlocked.',
                'check_in' => $checkIn,
            ]);
        }

        return redirect()->back()->with('success', 'Check-in recorded successfully. Welcome to your stay!');
    }

    public function checkOut(string $uuid, Request $request, ProcessStayCheckOutAction $action): RedirectResponse|JsonResponse
    {
        $booking = Booking::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'property_condition' => ['required', 'string', 'in:excellent,good,minor_issue,damage_reported'],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
            'condition_photos' => ['nullable', 'array'],
        ]);

        $checkOut = $action->execute(
            booking: $booking,
            user: $request->user(),
            propertyCondition: $validated['property_condition'],
            conditionNotes: $validated['condition_notes'] ?? null,
            conditionPhotos: $validated['condition_photos'] ?? []
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Checkout recorded successfully. Housekeeping dispatched.',
                'check_out' => $checkOut,
            ]);
        }

        return redirect()->back()->with('success', 'Checkout recorded successfully. Housekeeping has been dispatched.');
    }

    public function submitClaim(string $uuid, Request $request, SubmitDamageClaimAction $action): RedirectResponse|JsonResponse
    {
        $booking = Booking::query()->where('uuid', $uuid)->with(['host', 'property'])->firstOrFail();

        $validated = $request->validate([
            'claimed_amount_etb' => ['required', 'numeric', 'min:1'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_photos' => ['nullable', 'array'],
        ]);

        $claim = $action->execute(
            booking: $booking,
            hostUser: $request->user(),
            claimedAmountEtb: (float) $validated['claimed_amount_etb'],
            description: $validated['description'],
            evidencePhotos: $validated['evidence_photos'] ?? []
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Damage claim submitted within the 48-hour window. Guest notified.',
                'claim' => $claim,
            ]);
        }

        return redirect()->back()->with('success', 'Damage claim submitted successfully. The guest has been notified to respond.');
    }

    public function submitReview(string $uuid, Request $request, SubmitStayReviewAction $action): RedirectResponse|JsonResponse
    {
        $booking = Booking::query()->where('uuid', $uuid)->with(['host', 'property'])->firstOrFail();

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:guest_to_host,host_to_guest'],
            'scores' => ['required', 'array'],
            'public_comment' => ['required', 'string', 'min:5', 'max:2000'],
            'private_feedback_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = $action->execute(
            booking: $booking,
            reviewer: $request->user(),
            direction: $validated['direction'],
            scores: $validated['scores'],
            publicComment: $validated['public_comment'],
            privateFeedbackNote: $validated['private_feedback_note'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $review->is_published ? 'Review published!' : 'Review submitted under double-blind protocol.',
                'review' => $review,
            ]);
        }

        return redirect()->back()->with('success', 'Review submitted successfully.');
    }

    public function transitionCleaning(string $uuid, Request $request, TransitionCleaningTaskAction $action): RedirectResponse|JsonResponse
    {
        $task = CleaningTask::query()->where('uuid', $uuid)->with(['host', 'property'])->firstOrFail();

        $validated = $request->validate([
            'target_status' => ['required', 'string'],
            'cleaner_id' => ['nullable', 'integer'],
            'checklist' => ['nullable', 'array'],
            'inspection_notes' => ['nullable', 'string'],
            'is_rejection' => ['boolean'],
        ]);

        $targetStatus = CleaningTaskStatus::from($validated['target_status']);

        $updatedTask = $action->execute(
            task: $task,
            user: $request->user(),
            targetStatus: $targetStatus,
            assignCleanerId: isset($validated['cleaner_id']) ? (int) $validated['cleaner_id'] : null,
            checklist: $validated['checklist'] ?? null,
            inspectionNotes: $validated['inspection_notes'] ?? null,
            isInspectionRejection: (bool) ($validated['is_rejection'] ?? false)
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'task' => $updatedTask,
            ]);
        }

        return redirect()->back()->with('success', 'Housekeeping task status updated.');
    }
}

