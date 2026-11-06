<?php

declare(strict_types=1);

namespace App\Domains\Dispute\Actions;

use App\Domains\Booking\Models\Booking;
use App\Domains\Dispute\Models\DamageClaim;
use App\Domains\Dispute\Models\SecurityDeposit;
use App\Domains\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SubmitDamageClaimAction
{
    /**
     * @param array<int, string> $evidencePhotos
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        Booking $booking,
        User $hostUser,
        float $claimedAmountEtb,
        string $description,
        array $evidencePhotos = []
    ): DamageClaim {
        // 1. Authorize: must be the listing host or property owner
        $isHost = (int) $booking->host?->user_id === (int) $hostUser->id || (int) $booking->property?->owner_id === (int) $hostUser->id;
        $isStaff = $hostUser->isStaff();

        if (! $isHost && ! $isStaff) {
            throw new AuthorizationException('You are not authorized to submit a damage claim for this reservation.');
        }

        // 2. Validate claim window (SRS Section 67: within configured 48-hour window after checkout)
        $checkedOutAt = $booking->checkOut?->checked_out_at ?? $booking->checked_out_at;
        if (! $checkedOutAt) {
            throw ValidationException::withMessages([
                'booking' => ['A damage claim can only be submitted after checkout has been recorded.'],
            ]);
        }

        $now = CarbonImmutable::now();
        $checkoutTime = CarbonImmutable::parse($checkedOutAt);
        $deadline = $checkoutTime->addHours(48);

        if ($now->greaterThan($deadline)) {
            throw ValidationException::withMessages([
                'claim_window' => ['The 48-hour post-checkout damage claim submission window has expired.'],
            ]);
        }

        if ($claimedAmountEtb <= 0) {
            throw ValidationException::withMessages([
                'claimed_amount_etb' => ['Claimed damage amount must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $booking,
            $hostUser,
            $claimedAmountEtb,
            $description,
            $evidencePhotos,
            $deadline
        ): DamageClaim {
            /** @var SecurityDeposit|null $deposit */
            $deposit = SecurityDeposit::query()
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->first();

            $heldAmount = $deposit ? (float) $deposit->held_amount_etb : (float) $booking->security_deposit_etb;
            $deductedAmount = $deposit ? (float) $deposit->deducted_amount_etb : 0.0;
            $remainingEligible = max(0.0, $heldAmount - $deductedAmount);

            // Bounds check: claim cannot exceed remaining eligible deposit
            if ($claimedAmountEtb > $remainingEligible) {
                throw ValidationException::withMessages([
                    'claimed_amount_etb' => [
                        "Claimed amount ({$claimedAmountEtb} ETB) exceeds remaining eligible deposit ({$remainingEligible} ETB).",
                    ],
                ]);
            }

            $claimCode = 'CLM-'.$deadline->year.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

            return DamageClaim::query()->create([
                'uuid' => (string) Str::uuid(),
                'claim_code' => $claimCode,
                'booking_id' => $booking->id,
                'security_deposit_id' => $deposit?->id,
                'host_id' => $booking->host_id,
                'guest_user_id' => $booking->guest_user_id,
                'claimed_amount_etb' => $claimedAmountEtb,
                'approved_amount_etb' => null,
                'description' => $description,
                'evidence_photos_json' => $evidencePhotos,
                'status' => 'submitted',
                'claim_deadline_at' => $deadline,
            ]);
        });
    }
}

