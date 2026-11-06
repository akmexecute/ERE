<?php

declare(strict_types=1);

namespace App\Domains\Financial\Actions;

use App\Domains\Financial\Enums\PayoutStatus;
use App\Domains\Financial\Models\HostPayout;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class HoldOrReleasePayoutAction
{
    /**
     * Place an unsettled payout on hold (e.g. pending damage dispute or guest complaint).
     */
    public function hold(HostPayout $payout, string $reason, User $officer): HostPayout
    {
        if (trim($reason) === '') {
            throw new DomainException('A non-empty reason is required to place a host payout on hold.');
        }

        return DB::transaction(function () use ($payout, $reason, $officer) {
            $locked = HostPayout::query()->where('id', $payout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PayoutStatus::Paid) {
                throw new DomainException('Cannot place an already paid payout on hold.');
            }

            $locked->status = PayoutStatus::OnHold;
            $locked->hold_reason = $reason;
            $locked->held_by_user_id = $officer->id;
            $locked->save();

            return $locked;
        });
    }

    /**
     * Release a payout from hold back to scheduled or eligible status.
     */
    public function release(HostPayout $payout, User $officer): HostPayout
    {
        return DB::transaction(function () use ($payout, $officer) {
            $locked = HostPayout::query()->where('id', $payout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PayoutStatus::OnHold) {
                throw new DomainException('Payout is not currently on hold.');
            }

            $locked->status = PayoutStatus::Eligible;
            $locked->hold_reason = null;
            $locked->held_by_user_id = null;
            $locked->save();

            return $locked;
        });
    }
}

