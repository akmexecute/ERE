<?php

declare(strict_types=1);

namespace App\Domains\Financial\Actions;

use App\Domains\Financial\Enums\LedgerAccountCode;
use App\Domains\Financial\Enums\PayoutStatus;
use App\Domains\Financial\Models\HostPayout;
use App\Domains\Financial\Services\DoubleEntryPostingService;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ProcessHostPayoutAction
{
    public function __construct(
        private readonly DoubleEntryPostingService $postingService
    ) {}

    /**
     * Settle an eligible host payout, record transfer reference, and post financial ledger entries.
     */
    public function execute(
        HostPayout $payout,
        User $officer,
        ?string $providerReference = null
    ): HostPayout {
        return DB::transaction(function () use ($payout, $officer, $providerReference) {
            $lockedPayout = HostPayout::query()->where('id', $payout->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayout->status === PayoutStatus::Paid) {
                // Idempotent return: already settled, do not duplicate payout
                return $lockedPayout;
            }

            if ($lockedPayout->status === PayoutStatus::OnHold) {
                throw new DomainException("Cannot process payout [{$lockedPayout->payout_reference}]: Payout is on hold ({$lockedPayout->hold_reason}).");
            }

            if ($lockedPayout->status === PayoutStatus::Cancelled) {
                throw new DomainException("Cannot process payout [{$lockedPayout->payout_reference}]: Payout has been cancelled.");
            }

            // Verify payout destination
            $destination = $lockedPayout->destination;
            if (! $destination || ! $destination->is_verified) {
                throw new DomainException("Cannot process payout [{$lockedPayout->payout_reference}]: No verified payout destination account.");
            }

            // Verify booking cancellation state
            if ($lockedPayout->booking && $lockedPayout->booking->status === 'cancelled') {
                $lockedPayout->status = PayoutStatus::Cancelled;
                $lockedPayout->hold_reason = 'Booking was cancelled before payout execution.';
                $lockedPayout->save();
                throw new DomainException("Payout cancelled: Related booking [{$lockedPayout->booking->booking_code}] is cancelled.");
            }

            $reference = $providerReference ?? 'PO-TXN-' . date('Ymd') . '-' . bin2hex(random_bytes(6));

            $lockedPayout->status = PayoutStatus::Paid;
            $lockedPayout->approved_by_user_id = $officer->id;
            $lockedPayout->approved_at = now();
            $lockedPayout->paid_at = now();
            $lockedPayout->provider_channel = $destination->channel;
            $lockedPayout->provider_reference = $reference;
            $lockedPayout->save();

            // Double-entry accounting entry:
            // Dr: 2010 Host Payable (settle the liability)
            // Cr: 1010 Cash & Clearing (cash leaves clearing account)
            $amount = number_format((float) $lockedPayout->net_payable_etb, 2, '.', '');

            $entries = [
                [
                    'account_code' => LedgerAccountCode::HostPayable,
                    'entry_type' => 'debit',
                    'amount_etb' => $amount,
                    'description' => "Settled host payout [{$lockedPayout->payout_reference}] for host #{$lockedPayout->host_id}",
                    'source_reference' => $lockedPayout->payout_reference,
                ],
                [
                    'account_code' => LedgerAccountCode::CashAndClearing,
                    'entry_type' => 'credit',
                    'amount_etb' => $amount,
                    'description' => "Funds disbursed via {$destination->channel} [{$reference}]",
                    'source_reference' => $reference,
                ],
            ];

            $this->postingService->post(
                $lockedPayout,
                'host_payout_settled',
                "Host payout disbursement of ETB {$amount} to [{$destination->account_holder_name}]",
                $entries,
                $officer
            );

            return $lockedPayout;
        });
    }
}

