<?php

declare(strict_types=1);

namespace App\Domains\Offer\Actions;

use App\Domains\Offer\Models\OfferNegotiationEvent;
use App\Domains\Offer\Models\PropertyOffer;
use App\Models\NegotiationOffer;
use App\Domains\User\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RecordNegotiationTurnAction
{
    /**
     * Pure in-memory negotiation simulation (retained for test and client projection compatibility).
     *
     * @param  array<int, array{round: int, party: string, action: string, amount_etb: float, message?: string}>  $existingHistory
     * @return array{
     *   status: string,
     *   latest_amount_etb: float,
     *   awaiting_response_from: ?string,
     *   discount_from_asking_etb: float,
     *   discount_from_asking_pct: float,
     *   history: array<int, array{round: int, party: string, action: string, amount_etb: float, message?: string}>
     * }
     */
    public function execute(
        float $askingPriceEtb,
        array $existingHistory,
        string $actorParty, // 'buyer' or 'seller'
        string $action, // 'offer', 'counteroffer', 'accept', 'reject'
        ?float $proposedAmountEtb = null,
        string $message = ''
    ): array {
        if (! in_array($actorParty, ['buyer', 'seller'], true)) {
            throw new InvalidArgumentException("Actor party must be 'buyer' or 'seller'.");
        }

        $round = count($existingHistory) + 1;

        if ($round === 1) {
            if ($actorParty !== 'buyer' || $action !== 'offer' || $proposedAmountEtb === null || $proposedAmountEtb <= 0) {
                throw new InvalidArgumentException('Round 1 must be a positive initial offer submitted by the buyer.');
            }

            $existingHistory[] = [
                'round' => 1,
                'party' => 'buyer',
                'action' => 'offer',
                'amount_etb' => round($proposedAmountEtb, 2),
                'message' => $message,
            ];

            return $this->buildSummary($askingPriceEtb, 'submitted', $proposedAmountEtb, 'seller', $existingHistory);
        }

        $lastEvent = $existingHistory[count($existingHistory) - 1];
        if ($lastEvent['party'] === $actorParty) {
            throw new InvalidArgumentException("It is currently the counterparty's turn to respond.");
        }

        $currentAmount = (float) $lastEvent['amount_etb'];

        if ($action === 'accept') {
            $existingHistory[] = [
                'round' => $round,
                'party' => $actorParty,
                'action' => 'accept',
                'amount_etb' => $currentAmount,
                'message' => $message,
            ];

            return $this->buildSummary($askingPriceEtb, 'accepted', $currentAmount, null, $existingHistory);
        }

        if ($action === 'reject') {
            $existingHistory[] = [
                'round' => $round,
                'party' => $actorParty,
                'action' => 'reject',
                'amount_etb' => $currentAmount,
                'message' => $message,
            ];

            return $this->buildSummary($askingPriceEtb, 'rejected', $currentAmount, null, $existingHistory);
        }

        if ($action !== 'counteroffer' || $proposedAmountEtb === null || $proposedAmountEtb <= 0) {
            throw new InvalidArgumentException('Counteroffer requires a valid positive ETB amount.');
        }

        $existingHistory[] = [
            'round' => $round,
            'party' => $actorParty,
            'action' => 'counteroffer',
            'amount_etb' => round($proposedAmountEtb, 2),
            'message' => $message,
        ];

        $nextParty = $actorParty === 'seller' ? 'buyer' : 'seller';

        return $this->buildSummary($askingPriceEtb, 'countered', $proposedAmountEtb, $nextParty, $existingHistory);
    }

    /**
     * Durable, database-backed multi-round negotiation turn execution.
     * Enforces authenticated actor derivation, turn alternation, row-level locking, and terminal guards.
     */
    public function executeDatabaseTurn(
        User $actor,
        PropertyOffer $offer,
        string $action, // 'offer', 'counteroffer', 'accept', 'reject', 'withdraw'
        ?float $proposedAmountEtb = null,
        string $message = ''
    ): PropertyOffer {
        // 1. Authenticate actual participant and derive actorParty
        $actorParty = null;
        $isBuyer = (int) $actor->id === (int) $offer->buyer_user_id;
        $isSeller = (int) $actor->id === (int) $offer->seller_user_id
            || ($offer->agent_user_id && (int) $actor->id === (int) $offer->agent_user_id)
            || ($offer->listing && (int) $actor->id === (int) $offer->listing->published_by_user_id);

        if ($isBuyer) {
            $actorParty = 'buyer';
        } elseif ($isSeller) {
            $actorParty = 'seller';
        } elseif ($actor->hasRole('super_admin') || $actor->hasRole('platform_admin')) {
            $actorParty = $offer->awaiting_response_from ?? 'buyer';
        } else {
            throw new AuthorizationException('Unauthorized: You are not a recognized participant in this offer negotiation.');
        }

        // 2. Terminal state check
        if (in_array($offer->status, ['accepted', 'rejected', 'withdrawn', 'expired'], true)) {
            throw new DomainException("Negotiation is closed: Offer is already in terminal state '{$offer->status}'.");
        }

        // 3. Expiration check
        if ($offer->expires_at && now()->isAfter($offer->expires_at)) {
            $offer->update(['status' => 'expired']);
            throw new DomainException('Cannot submit turn: The offer has expired.');
        }

        // 4. Action-specific turn alternation and parameter validation
        if ($action === 'withdraw') {
            // Withdrawal can only be performed by the party who placed the last pending offer
            $expectedWithdrawer = $offer->awaiting_response_from === 'seller' ? 'buyer' : 'seller';
            if ($actorParty !== $expectedWithdrawer && ! $actor->hasRole('super_admin')) {
                throw new InvalidArgumentException('Only the party who submitted the latest pending offer may withdraw it.');
            }
        } elseif ($action === 'counteroffer' || $action === 'accept' || $action === 'reject') {
            if ($offer->awaiting_response_from !== null && $offer->awaiting_response_from !== $actorParty && ! $actor->hasRole('super_admin')) {
                throw new InvalidArgumentException("It is currently the counterparty's ({$offer->awaiting_response_from}) turn to respond.");
            }
        }

        if (in_array($action, ['offer', 'counteroffer'], true)) {
            if ($proposedAmountEtb === null || $proposedAmountEtb <= 0) {
                throw new InvalidArgumentException('Proposed offer amount must be a positive number.');
            }
        }

        // 5. Database transaction with row-level locking
        return DB::transaction(function () use (
            $actor,
            $actorParty,
            $offer,
            $action,
            $proposedAmountEtb,
            $message
        ): PropertyOffer {
            /** @var PropertyOffer $lockedOffer */
            $lockedOffer = PropertyOffer::query()->where('id', $offer->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedOffer->status, ['accepted', 'rejected', 'withdrawn', 'expired'], true)) {
                throw new DomainException("Concurrently finalized: Offer is already in terminal state '{$lockedOffer->status}'.");
            }

            $lastRound = (int) (OfferNegotiationEvent::query()
                ->where('property_offer_id', $lockedOffer->id)
                ->lockForUpdate()
                ->max('round_number') ?? 0);

            $roundNumber = $lastRound + 1;
            $now = now();
            $effectiveAmount = $proposedAmountEtb ? round($proposedAmountEtb, 2) : (float) $lockedOffer->latest_amount_etb;

            $updateData = [];

            if ($action === 'accept') {
                $eventType = 'accepted';
                $updateData['status'] = 'accepted';
                $updateData['accepted_at'] = $now;
                $updateData['awaiting_response_from'] = null;

                // Seller Accountability & Selling Authority Enforcement (SRS Module 21 / Post-Phase 4)
                $seller = $lockedOffer->seller;
                $property = $lockedOffer->property;
                $buyer = $lockedOffer->buyer;
                $agent = $lockedOffer->agent;

                if ($seller && $property && $buyer) {
                    $snapshotService = app(\App\Domains\Identity\Services\SellerIdentitySnapshotService::class);
                    $snapshotService->enforceSellerSellingAuthority($seller, $property, $agent);
                    $updateData['seller_identity_snapshot_json'] = $snapshotService->buildSnapshot(
                        seller: $seller,
                        propertyOrProject: $property,
                        buyer: $buyer,
                        agent: $agent,
                        company: $property->company_id ? \App\Models\Company::find($property->company_id) : null,
                        transactionRef: $lockedOffer->offer_code,
                        agreedPrice: (float) $lockedOffer->latest_amount_etb
                    );
                }
            } elseif ($action === 'reject') {
                $eventType = 'rejected';
                $updateData['status'] = 'rejected';
                $updateData['rejected_at'] = $now;
                $updateData['awaiting_response_from'] = null;
            } elseif ($action === 'withdraw') {
                $eventType = 'withdrawn';
                $updateData['status'] = 'withdrawn';
                $updateData['awaiting_response_from'] = null;
            } elseif ($action === 'counteroffer') {
                $eventType = 'counteroffer';
                $updateData['status'] = 'countered';
                $updateData['latest_amount_etb'] = $effectiveAmount;
                $updateData['awaiting_response_from'] = $actorParty === 'seller' ? 'buyer' : 'seller';
            } else { // 'offer'
                $eventType = 'initial_offer';
                $updateData['status'] = 'submitted';
                $updateData['latest_amount_etb'] = $effectiveAmount;
                $updateData['awaiting_response_from'] = 'seller';
            }

            // Persist the durable event in offer_negotiation_events
            OfferNegotiationEvent::query()->create([
                'property_offer_id' => $lockedOffer->id,
                'round_number' => $roundNumber,
                'actor_party' => $actorParty,
                'actor_user_id' => $actor->id,
                'event_type' => $eventType,
                'proposed_amount_etb' => $effectiveAmount,
                'message' => $message,
                'created_at' => $now,
            ]);

            $lockedOffer->update($updateData);

            if ($action === 'accept') {
                $lockedOffer->formalizePurchaseAgreement();
            }

            // Also keep negotiation_offers table in sync for backwards compatibility
            NegotiationOffer::query()->updateOrCreate(
                [
                    'listing_id' => $lockedOffer->listing_id,
                    'buyer_id' => $lockedOffer->buyer_user_id,
                    'round_number' => $roundNumber,
                ],
                [
                    'seller_id' => $lockedOffer->seller_user_id,
                    'agent_id' => $lockedOffer->agent_user_id,
                    'offered_amount' => $effectiveAmount,
                    'offered_by' => $actorParty,
                    'status' => $updateData['status'] ?? 'pending',
                    'notes' => $message,
                ]
            );

            return $lockedOffer->refresh();
        });
    }

    /**
     * Compute difference and discount percentage from asking price.
     *
     * @return array{
     *   difference_etb: float,
     *   discount_pct: float,
     *   is_discount: bool
     * }
     */
    public static function calculatePriceComparison(float $askingPriceEtb, float $acceptedPriceEtb): array
    {
        $diff = round($askingPriceEtb - $acceptedPriceEtb, 2);
        $pct = $askingPriceEtb > 0 ? round(($diff / $askingPriceEtb) * 100, 2) : 0.0;

        return [
            'difference_etb' => abs($diff),
            'discount_pct' => abs($pct),
            'is_discount' => $diff >= 0,
        ];
    }

    private function buildSummary(
        float $askingPriceEtb,
        string $status,
        float $latestAmountEtb,
        ?string $awaitingFrom,
        array $history
    ): array {
        $discountEtb = round($askingPriceEtb - $latestAmountEtb, 2);
        $discountPct = $askingPriceEtb > 0 ? round(($discountEtb / $askingPriceEtb) * 100, 2) : 0.0;

        return [
            'status' => $status,
            'latest_amount_etb' => round($latestAmountEtb, 2),
            'awaiting_response_from' => $awaitingFrom,
            'discount_from_asking_etb' => $discountEtb,
            'discount_from_asking_pct' => $discountPct,
            'history' => $history,
        ];
    }
}
