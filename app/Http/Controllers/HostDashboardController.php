<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Cleaning\Actions\TransitionCleaningTaskAction;
use App\Domains\Cleaning\Enums\CleaningTaskStatus;
use App\Domains\Cleaning\Models\CleaningTask;
use App\Domains\Dispute\Actions\SettleSecurityDepositClaimAction;
use App\Domains\Dispute\Models\DamageClaim;
use App\Domains\Dispute\Models\Dispute;
use App\Domains\Host\Models\Host;
use App\Domains\Host\Services\HostDashboardKpiCalculator;
use App\Domains\Review\Actions\EvaluateTwoWayReviewSubmissionAction;
use App\Domains\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class HostDashboardController extends Controller
{
    /**
     * Show the Host Management, Housekeeping & Revenue Dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // 1. Resolve host profile for authenticated user
        $host = null;
        if ($user) {
            $host = Host::query()->where('user_id', $user->id)->first();
        }

        // If no host profile exists yet, use the first available host or create a temporary instance for display
        if (! $host) {
            $host = Host::query()->first();
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // 2. Compute Real Database KPIs if host exists
        if ($host) {
            $kpis = HostDashboardKpiCalculator::computeFromDatabase($host, $startDate, $endDate);
            $preferences = HostDashboardKpiCalculator::getPreferences($host);

            $cleaningTasksQuery = CleaningTask::query()
                ->where('host_id', $host->id)
                ->with(['cleaner', 'accommodationUnit', 'property'])
                ->latest('scheduled_date')
                ->limit(20)
                ->get();

            $cleaningTasks = $cleaningTasksQuery->map(fn (CleaningTask $t) => [
                'id' => $t->task_code,
                'uuid' => $t->uuid,
                'unit' => $t->accommodationUnit?->unit_number_or_code ?? $t->property?->title_en ?? 'Unit #'.$t->accommodation_unit_id,
                'cleaner' => $t->cleaner?->name ?? 'Unassigned',
                'checkout_time' => '11:00 AM',
                'status' => $t->status->value,
                'checklist' => $t->checklist_json ?: ['Linens replaced', 'Towels restocked', 'Kitchen sanitised', 'Generator inspected'],
            ])->all();

            $damageClaims = DamageClaim::query()
                ->where('host_id', $host->id)
                ->with(['booking.guest', 'securityDeposit'])
                ->latest()
                ->limit(10)
                ->get();

            $disputes = Dispute::query()
                ->where('against_user_id', $host->user_id)
                ->orWhereHas('damageClaim', fn ($q) => $q->where('host_id', $host->id))
                ->latest()
                ->limit(10)
                ->get();
        } else {
            // Baseline preview fallback if database is completely empty of hosts
            $kpis = HostDashboardKpiCalculator::compute(
                propertiesCount: 12,
                upcomingBookingsCount: 8,
                currentGuestsCount: 4,
                bookedUnitNightsInPeriod: 72,
                totalAvailableUnitNightsInPeriod: 100,
                totalRevenueEtb: 850_000.0
            );
            $preferences = HostDashboardKpiCalculator::defaultPreferences();
            $cleaningTasks = [];
            $damageClaims = collect();
            $disputes = collect();
        }

        // If no tasks exist in DB, provide default illustrative tasks so view never breaks
        if (empty($cleaningTasks)) {
            $cleaningTasks = [
                [
                    'id' => 'CLN-2026-000412',
                    'unit' => 'Villa A — Bole Medhanialem',
                    'cleaner' => 'Tigist Alemu',
                    'checkout_time' => '11:00 AM',
                    'status' => 'cleaning',
                    'checklist' => ['Linens replaced', 'Towels restocked', 'Kitchen sanitised', 'Generator inspected'],
                ],
                [
                    'id' => 'CLN-2026-000413',
                    'unit' => 'Serviced Apt B2 — Kazanchis',
                    'cleaner' => 'Dawit Bekele',
                    'checkout_time' => '10:30 AM',
                    'status' => 'inspection',
                    'checklist' => ['Smart lock battery checked', 'Balcony cleaned', 'High-speed Wi-Fi tested'],
                ],
                [
                    'id' => 'CLN-2026-000414',
                    'unit' => 'Guesthouse Suite 104 — Harar',
                    'cleaner' => 'Hanan Mohammed',
                    'checkout_time' => '09:45 AM',
                    'status' => 'ready',
                    'checklist' => ['Traditional coffee set ready', 'Welcome kit placed', 'Water reservoir full'],
                ],
                [
                    'id' => 'CLN-2026-000415',
                    'unit' => 'Penthouse 12 — Sarbet',
                    'cleaner' => 'Almaz Tefera',
                    'checkout_time' => '12:00 PM',
                    'status' => 'pending',
                    'checklist' => ['Bedding change', 'AC filter dusting', 'Terrace watering'],
                ],
            ];
        }

        $availableCards = HostDashboardKpiCalculator::CARD_KEYS;

        return view('host.dashboard', compact(
            'kpis',
            'cleaningTasks',
            'damageClaims',
            'disputes',
            'preferences',
            'availableCards',
            'host'
        ));
    }

    /**
     * Update host-controlled KPI card visibility preferences (SRS Section 55 & Section 7.6).
     */
    public function updatePreferences(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $host = Host::query()->where('user_id', $user?->id)->first();

        if (! $host) {
            $host = Host::query()->first();
        }

        if (! $host) {
            return redirect()->back()->with('error', 'Host profile not found.');
        }

        if ($request->boolean('reset')) {
            $prefs = HostDashboardKpiCalculator::resetPreferences($host);
        } else {
            $cardInput = $request->input('cards', []);
            $prefs = HostDashboardKpiCalculator::savePreferences($host, (array) $cardInput);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'preferences' => $prefs,
            ]);
        }

        return redirect()->back()->with('success', 'Dashboard KPI card visibility updated successfully.');
    }

    /**
     * Backend API: Settle Security Deposit & Damage Claim (SRS §§66 & 67).
     */
    public function settleDeposit(Request $request, SettleSecurityDepositClaimAction $action): JsonResponse
    {
        $validated = $request->validate([
            'held_deposit_etb' => ['required', 'numeric', 'min:0'],
            'claimed_damage_etb' => ['required', 'numeric', 'min:1'],
            'decision' => ['required', 'string', 'in:accept,dispute'],
            'claim_id' => ['nullable', 'integer'],
            'guest_response_notes' => ['nullable', 'string'],
        ]);

        $claim = null;
        if (! empty($validated['claim_id'])) {
            $claim = DamageClaim::query()->find($validated['claim_id']);
        }

        try {
            $result = $action->execute(
                heldDepositEtb: (float) $validated['held_deposit_etb'],
                claimedDamageEtb: (float) $validated['claimed_damage_etb'],
                guestDecision: $validated['decision'],
                claim: $claim,
                guestUser: $request->user(),
                guestResponseNotes: $validated['guest_response_notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Backend API: Evaluate Two-Way Review submission and spam filtering (SRS §§69, 70, 119).
     */
    public function evaluateReview(Request $request, EvaluateTwoWayReviewSubmissionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:guest_to_host,host_to_guest'],
            'scores' => ['required', 'array'],
            'comment' => ['required', 'string'],
            'counterparty_already_submitted' => ['boolean'],
        ]);

        try {
            $result = $action->execute(
                direction: $validated['direction'],
                scores: $validated['scores'],
                comment: $validated['comment'],
                counterpartyAlreadySubmitted: (bool) ($validated['counterparty_already_submitted'] ?? false)
            );

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Backend API: Advance Housekeeping state machine (SRS §63).
     */
    public function advanceCleaning(Request $request, TransitionCleaningTaskAction $action): JsonResponse
    {
        $validated = $request->validate([
            'current_status' => ['required', 'string'],
            'task_code' => ['nullable', 'string'],
            'task_id' => ['nullable', 'integer'],
        ]);

        // If a real DB cleaning task is provided, transition the model
        $task = null;
        if (! empty($validated['task_code'])) {
            $task = CleaningTask::query()->where('task_code', $validated['task_code'])->first();
        } elseif (! empty($validated['task_id'])) {
            $task = CleaningTask::query()->find($validated['task_id']);
        }

        $current = CleaningTaskStatus::tryFrom($validated['current_status']);
        if (! $current) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 422);
        }

        $order = [
            CleaningTaskStatus::Pending,
            CleaningTaskStatus::Assigned,
            CleaningTaskStatus::Cleaning,
            CleaningTaskStatus::Inspection,
            CleaningTaskStatus::Ready,
        ];

        $idx = array_search($current, $order, true);
        if ($idx !== false && $idx < count($order) - 1) {
            $next = $order[$idx + 1];

            if ($task !== null && $request->user()) {
                try {
                    $action->execute(
                        task: $task,
                        user: $request->user(),
                        targetStatus: $next
                    );
                } catch (\Throwable $e) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
                }
            }

            if ($current->canTransitionTo($next)) {
                return response()->json([
                    'success' => true,
                    'new_status' => $next->value,
                    'can_advance' => $next !== CleaningTaskStatus::Ready,
                ]);
            }
        }

        return response()->json(['success' => false, 'message' => 'Cannot advance further'], 422);
    }
}
