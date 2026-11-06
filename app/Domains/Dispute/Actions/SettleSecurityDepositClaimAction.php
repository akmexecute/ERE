<?php

declare(strict_types=1);

namespace App\Domains\Dispute\Actions;

use App\Domains\Dispute\Models\DamageClaim;
use App\Domains\Dispute\Models\Dispute;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class SettleSecurityDepositClaimAction
{
    /**
     * @return array{
     *   deposit_status: string,
     *   claim_status: string,
     *   deducted_amount_etb: float,
     *   released_to_guest_etb: float,
     *   dispute_opened: bool,
     *   dispute_stage: ?string
     * }
     */
    public function execute(
        float $heldDepositEtb,
        float $claimedDamageEtb,
        string $guestDecision, // 'accept' or 'dispute'
        ?DamageClaim $claim = null,
        ?User $guestUser = null,
        ?string $guestResponseNotes = null
    ): array {
        if ($heldDepositEtb < 0 || $claimedDamageEtb <= 0) {
            throw new InvalidArgumentException('Held deposit and claimed damage must be positive amounts.');
        }

        if ($guestDecision !== 'accept' && $guestDecision !== 'dispute') {
            throw new InvalidArgumentException("Invalid guest decision '{$guestDecision}'. Must be 'accept' or 'dispute'.");
        }

        // If a real DB DamageClaim is provided, perform database-backed persistence within a transaction
        if ($claim !== null) {
            return DB::transaction(function () use (
                $claim,
                $heldDepositEtb,
                $claimedDamageEtb,
                $guestDecision,
                $guestResponseNotes
            ): array {
                /** @var DamageClaim $lockedClaim */
                $lockedClaim = DamageClaim::query()->lockForUpdate()->findOrFail($claim->id);

                if ($lockedClaim->status !== 'submitted') {
                    throw new InvalidArgumentException("Damage claim #{$lockedClaim->id} has already been resolved with status '{$lockedClaim->status}'.");
                }

                /** @var SecurityDeposit|null $lockedDeposit */
                $lockedDeposit = $lockedClaim->securityDeposit
                    ? SecurityDeposit::query()->lockForUpdate()->find($lockedClaim->security_deposit_id)
                    : null;

                $effectiveHeld = $lockedDeposit ? (float) $lockedDeposit->held_amount_etb : $heldDepositEtb;
                $now = CarbonImmutable::now();

                if ($guestDecision === 'dispute') {
                    $lockedClaim->update([
                        'status' => 'disputed_by_guest',
                        'guest_response_notes' => $guestResponseNotes,
                        'responded_at' => $now,
                    ]);

                    if ($lockedDeposit) {
                        $lockedDeposit->update(['status' => SecurityDeposit::STATUS_DISPUTED]);
                    }

                    // Open formal Dispute record in evidence stage
                    $hostUserId = $lockedClaim->host?->user_id ?? 1;
                    $disputeCode = 'DSP-'.$now->year.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

                    Dispute::query()->firstOrCreate(
                        ['damage_claim_id' => $lockedClaim->id],
                        [
                            'uuid' => (string) Str::uuid(),
                            'dispute_code' => $disputeCode,
                            'booking_id' => $lockedClaim->booking_id,
                            'opened_by_user_id' => $lockedClaim->guest_user_id,
                            'against_user_id' => $hostUserId,
                            'category' => 'damage',
                            'status' => 'evidence',
                            'issue_description' => "Guest contested damage claim: {$lockedClaim->description}",
                        ]
                    );

                    return [
                        'deposit_status' => SecurityDeposit::STATUS_DISPUTED,
                        'claim_status' => 'disputed_by_guest',
                        'deducted_amount_etb' => 0.0,
                        'released_to_guest_etb' => 0.0,
                        'dispute_opened' => true,
                        'dispute_stage' => 'evidence',
                    ];
                }

                // Guest Accepted
                $deducted = round(min($effectiveHeld, $claimedDamageEtb), 2);
                $releasedToGuest = round(max(0.0, $effectiveHeld - $deducted), 2);

                $lockedClaim->update([
                    'status' => 'accepted_by_guest',
                    'approved_amount_etb' => $deducted,
                    'guest_response_notes' => $guestResponseNotes,
                    'responded_at' => $now,
                ]);

                $newStatus = $releasedToGuest > 0
                    ? SecurityDeposit::STATUS_PARTIALLY_DEDUCTED
                    : SecurityDeposit::STATUS_FULLY_DEDUCTED;

                if ($lockedDeposit) {
                    $newTotalDeducted = round((float) $lockedDeposit->deducted_amount_etb + $deducted, 2);
                    $newReleased = round(max(0.0, (float) $effectiveHeld - $newTotalDeducted), 2);
                    $depositStatus = $newReleased > 0
                        ? SecurityDeposit::STATUS_PARTIALLY_DEDUCTED
                        : SecurityDeposit::STATUS_FULLY_DEDUCTED;

                    $lockedDeposit->update([
                        'deducted_amount_etb' => $newTotalDeducted,
                        'released_to_guest_etb' => $newReleased,
                        'status' => $depositStatus,
                        'released_at' => $now,
                    ]);
                }

                return [
                    'deposit_status' => $newStatus,
                    'claim_status' => 'accepted_by_guest',
                    'deducted_amount_etb' => $deducted,
                    'released_to_guest_etb' => $releasedToGuest,
                    'dispute_opened' => false,
                    'dispute_stage' => null,
                ];
            });
        }

        // Pure calculation fallback
        if ($guestDecision === 'dispute') {
            return [
                'deposit_status' => 'disputed',
                'claim_status' => 'disputed_by_guest',
                'deducted_amount_etb' => 0.0,
                'released_to_guest_etb' => 0.0,
                'dispute_opened' => true,
                'dispute_stage' => 'evidence',
            ];
        }

        $deducted = round(min($heldDepositEtb, $claimedDamageEtb), 2);
        $releasedToGuest = round(max(0.0, $heldDepositEtb - $deducted), 2);
        $retStatus = $releasedToGuest > 0
            ? SecurityDeposit::STATUS_PARTIALLY_DEDUCTED
            : SecurityDeposit::STATUS_FULLY_DEDUCTED;

        return [
            'deposit_status' => $retStatus,
            'claim_status' => 'accepted_by_guest',
            'deducted_amount_etb' => $deducted,
            'released_to_guest_etb' => $releasedToGuest,
            'dispute_opened' => false,
            'dispute_stage' => null,
        ];
    }
}
