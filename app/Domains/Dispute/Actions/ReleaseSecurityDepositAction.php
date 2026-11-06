<?php

declare(strict_types=1);

namespace App\Domains\Dispute\Actions;

use App\Domains\Dispute\Models\SecurityDeposit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ReleaseSecurityDepositAction
{
    /**
     * Release eligible remaining deposit funds back to the guest.
     *
     * @throws InvalidArgumentException
     */
    public function execute(SecurityDeposit $deposit): SecurityDeposit
    {
        return DB::transaction(function () use ($deposit): SecurityDeposit {
            /** @var SecurityDeposit $locked */
            $locked = SecurityDeposit::query()->lockForUpdate()->findOrFail($deposit->id);

            if (in_array($locked->status, [
                SecurityDeposit::STATUS_RELEASED,
                SecurityDeposit::STATUS_PARTIALLY_DEDUCTED,
                SecurityDeposit::STATUS_FULLY_DEDUCTED,
            ], true)) {
                return $locked; // Idempotent
            }

            if ($locked->status === SecurityDeposit::STATUS_DISPUTED) {
                throw new InvalidArgumentException('Cannot automatically release deposit while formal dispute is active.');
            }

            if ($locked->damageClaims()->whereIn('status', ['submitted', 'disputed_by_guest'])->exists()) {
                throw new InvalidArgumentException('Cannot release deposit while damage claims are pending or under dispute.');
            }

            $held = (float) $locked->held_amount_etb;
            $deducted = (float) $locked->deducted_amount_etb;
            $remaining = round(max(0.0, $held - $deducted), 2);

            $now = CarbonImmutable::now();
            $newStatus = $deducted > 0
                ? ($remaining > 0 ? SecurityDeposit::STATUS_PARTIALLY_DEDUCTED : SecurityDeposit::STATUS_FULLY_DEDUCTED)
                : SecurityDeposit::STATUS_RELEASED;

            $locked->update([
                'released_to_guest_etb' => $remaining,
                'status' => $newStatus,
                'released_at' => $now,
            ]);

            return $locked;
        });
    }
}

