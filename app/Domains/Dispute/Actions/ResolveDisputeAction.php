<?php

declare(strict_types=1);

namespace App\Domains\Dispute\Actions;

use App\Domains\Dispute\Models\Dispute;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ResolveDisputeAction
{
    /**
     * Staff adjudication and financial settlement of an open dispute.
     *
     * @throws AuthorizationException
     * @throws InvalidArgumentException
     */
    public function execute(
        Dispute $dispute,
        User $staffUser,
        float $refundToGuestEtb,
        float $payoutToHostEtb,
        string $resolutionSummary
    ): Dispute {
        if (! $staffUser->isStaff()) {
            throw new AuthorizationException('Only authorized staff may adjudicate disputes.');
        }

        if ($refundToGuestEtb < 0 || $payoutToHostEtb < 0) {
            throw new InvalidArgumentException('Refund and payout amounts must be non-negative.');
        }

        return DB::transaction(function () use (
            $dispute,
            $staffUser,
            $refundToGuestEtb,
            $payoutToHostEtb,
            $resolutionSummary
        ): Dispute {
            /** @var Dispute $locked */
            $locked = Dispute::query()->lockForUpdate()->findOrFail($dispute->id);

            $now = CarbonImmutable::now();

            $locked->update([
                'status' => 'closed',
                'resolution_summary' => $resolutionSummary,
                'refund_to_guest_etb' => round($refundToGuestEtb, 2),
                'payout_to_host_etb' => round($payoutToHostEtb, 2),
                'resolved_by_staff_id' => $staffUser->id,
                'resolved_at' => $now,
            ]);

            // If linked to a damage claim / security deposit, settle the held deposit
            if ($locked->damageClaim && $locked->damageClaim->securityDeposit) {
                /** @var SecurityDeposit $deposit */
                $deposit = SecurityDeposit::query()->lockForUpdate()->find($locked->damageClaim->security_deposit_id);
                if ($deposit) {
                    $deposit->update([
                        'deducted_amount_etb' => round($payoutToHostEtb, 2),
                        'released_to_guest_etb' => round($refundToGuestEtb, 2),
                        'status' => $payoutToHostEtb > 0 ? 'partially_deducted' : 'released',
                        'released_at' => $now,
                    ]);
                }
            }

            return $locked;
        });
    }
}

