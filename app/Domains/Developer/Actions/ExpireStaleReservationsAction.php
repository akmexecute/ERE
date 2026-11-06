<?php

declare(strict_types=1);

namespace App\Domains\Developer\Actions;

use App\Domains\Developer\Enums\ReservationStatus;
use App\Domains\Developer\Enums\UnitInventoryStatus;
use App\Domains\Developer\Models\ProjectUnit;
use App\Domains\Developer\Models\UnitReservation;
use Illuminate\Support\Facades\DB;

final readonly class ExpireStaleReservationsAction
{
    /**
     * Identifies and releases expired unit reservation holds safely.
     *
     * @return int Number of reservations expired
     */
    public function execute(): int
    {
        $expiredCount = 0;

        // Fetch candidate active reservations whose hold has passed
        $staleReservations = UnitReservation::query()
            ->whereIn('status', [
                ReservationStatus::Requested->value,
                ReservationStatus::PendingPayment->value,
                ReservationStatus::Reserved->value,
            ])
            ->where('hold_expires_at', '<=', now())
            ->get();

        foreach ($staleReservations as $candidate) {
            DB::transaction(function () use ($candidate, &$expiredCount): void {
                /** @var UnitReservation $res */
                $res = UnitReservation::where('id', $candidate->id)
                    ->lockForUpdate()
                    ->first();

                if (! $res || ! $res->status->isActive() || ! $res->isExpired()) {
                    return;
                }

                /** @var ProjectUnit $unit */
                $unit = ProjectUnit::where('id', $res->project_unit_id)
                    ->lockForUpdate()
                    ->first();

                // Check if another active reservation currently owns the unit
                $hasOtherActiveReservation = UnitReservation::where('project_unit_id', $res->project_unit_id)
                    ->where('id', '!=', $res->id)
                    ->whereIn('status', [
                        ReservationStatus::Requested->value,
                        ReservationStatus::PendingPayment->value,
                        ReservationStatus::Reserved->value,
                    ])
                    ->exists();

                // Only release unit back to available if it is currently in 'reserved' state and no newer reservation holds it!
                // Never release contracted or sold inventory!
                if ($unit && $unit->status === UnitInventoryStatus::Reserved && ! $hasOtherActiveReservation) {
                    $unit->update([
                        'status' => UnitInventoryStatus::Available,
                    ]);
                }

                $res->update([
                    'status' => ReservationStatus::Expired,
                    'expired_at' => now(),
                    'cancellation_reason' => '15-minute temporary hold window expired.',
                ]);

                $expiredCount++;
            });
        }

        return $expiredCount;
    }
}
