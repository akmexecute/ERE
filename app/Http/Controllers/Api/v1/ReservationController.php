<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\v1;

use App\Domains\Developer\Actions\ConvertReservationToContractAction;
use App\Domains\Developer\Actions\ReserveProjectUnitAction;
use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Developer\Models\UnitReservation;
use App\Http\Controllers\Controller;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReservationController extends Controller
{
    public function __construct(
        protected ReserveProjectUnitAction $reserveAction,
        protected ConvertReservationToContractAction $convertAction
    ) {
    }

    /**
     * POST /api/v1/units/{unit}/reserve
     * Concurrency-safe atomic unit reservation hold.
     */
    public function reserve(Request $request, int $unitId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication is required to place a reservation hold.',
            ], 401);
        }

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:80'],
            'payment_plan_id' => ['nullable', 'integer', 'exists:developer_payment_plans,id'],
            'deposit_percentage' => ['nullable', 'numeric', 'min:5', 'max:100'],
            'hold_minutes' => ['nullable', 'integer', 'min:5', 'max:120'],
            'buyer_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $buyerUserId = $user->id;

        try {
            $reservation = $this->reserveAction->execute(
                unitId: $unitId,
                buyerUserId: $buyerUserId,
                idempotencyKey: $validated['idempotency_key'],
                paymentPlanId: $validated['payment_plan_id'] ?? null,
                depositPercentage: (float) ($validated['deposit_percentage'] ?? 10.0),
                holdMinutes: (int) ($validated['hold_minutes'] ?? 15)
            );

            return response()->json([
                'id' => $reservation->id,
                'uuid' => $reservation->uuid,
                'reservation_code' => $reservation->reservation_code,
                'project_unit_id' => $reservation->project_unit_id,
                'agreed_price_etb' => (float) $reservation->agreed_price_etb,
                'deposit_amount_etb' => (float) $reservation->deposit_amount_etb,
                'deposit_percentage' => (float) $reservation->deposit_percentage,
                'status' => $reservation->status->value,
                'hold_expires_at' => $reservation->hold_expires_at->toIso8601String(),
                'message' => 'Unit temporary reservation hold placed successfully.',
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'error' => 'Conflict / Unit Unavailable',
                'message' => $e->getMessage(),
            ], 409);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Validation Error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/reservations/{reservation}/convert
     * Converts a confirmed reservation to a developer sales contract.
     */
    public function convert(Request $request, int $reservationId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication is required to execute a contract.',
            ], 401);
        }

        $validated = $request->validate([
            'disclosure_accepted' => ['required', 'boolean'],
            'disclosure_version' => ['nullable', 'string', 'max:32'],
        ]);

        $reservation = UnitReservation::findOrFail($reservationId);
        if ((int) $reservation->buyer_user_id !== (int) $user->id && (! method_exists($user, 'hasRole') || ! $user->hasRole('super_admin'))) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Only the reserving buyer may convert this reservation to a sales contract.',
            ], 403);
        }

        if (! $validated['disclosure_accepted']) {
            return response()->json([
                'error' => 'Statutory Disclosure Required',
                'message' => 'Proclamation No. 1357/2024 requires explicit buyer consent to off-plan terms prior to contract execution.',
            ], 422);
        }

        try {
            $contract = $this->convertAction->execute(
                reservationId: $reservationId,
                disclosureAccepted: (bool) $validated['disclosure_accepted'],
                clientIp: $request->ip(),
                disclosureVersion: $validated['disclosure_version'] ?? 'v1.0-proc1357'
            );

            return response()->json([
                'id' => $contract->id,
                'contract_code' => $contract->contract_code,
                'reservation_id' => $contract->reservation_id,
                'contract_price_etb' => (float) $contract->contract_price_etb,
                'deposit_paid_etb' => (float) $contract->deposit_paid_etb,
                'balance_payable_etb' => (float) $contract->balance_payable_etb,
                'status' => $contract->status->value,
                'installments_count' => $contract->installments->count(),
                'installments' => $contract->installments,
                'message' => 'Reservation successfully converted to developer sales contract.',
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'error' => 'Conversion Conflict',
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * GET /api/v1/reservations/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication is required.',
            ], 401);
        }

        $reservation = UnitReservation::with(['unit', 'project.developer.company', 'buyer'])->findOrFail($id);
        $isBuyer = (int) $reservation->buyer_user_id === (int) $user->id;
        $isDev = (int) $reservation->project->developer->user_id === (int) $user->id || (int) $reservation->project->developer->company->owner_user_id === (int) $user->id;
        $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');

        if (! $isBuyer && ! $isDev && ! $isAdmin) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Access denied.'], 403);
        }

        return response()->json($reservation);
    }

    /**
     * POST /api/v1/reservations/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication is required to cancel a reservation.',
            ], 401);
        }

        $reservation = UnitReservation::with('project.developer.company')->findOrFail($id);
        $isBuyer = (int) $reservation->buyer_user_id === (int) $user->id;
        $isDev = (int) $reservation->project->developer->user_id === (int) $user->id || (int) $reservation->project->developer->company->owner_user_id === (int) $user->id;
        $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');

        if (! $isBuyer && ! $isDev && ! $isAdmin) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Unauthorized to cancel this reservation.'], 403);
        }

        if (! $reservation->status->isActive()) {
            return response()->json([
                'error' => 'Invalid State',
                'message' => "Reservation cannot be cancelled in state '{$reservation->status->value}'.",
            ], 422);
        }

        DB::transaction(function () use ($reservation, $request): void {
            $unit = ProjectUnit::where('id', $reservation->project_unit_id)->lockForUpdate()->first();

            // Release unit if currently reserved
            if ($unit && $unit->status === UnitInventoryStatus::Reserved) {
                $unit->update(['status' => UnitInventoryStatus::Available]);
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $request->input('reason', 'Cancelled by buyer/developer request.'),
            ]);
        });

        return response()->json([
            'id' => $reservation->id,
            'status' => $reservation->status->value,
            'message' => 'Reservation hold successfully cancelled and inventory released.',
        ]);
    }
}
