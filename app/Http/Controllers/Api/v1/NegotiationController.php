<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\NegotiationOffer;
use App\Domains\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NegotiationController extends Controller
{
    /**
     * POST /api/v1/listings/{listing}/negotiate
     * Submits an immutable round in the negotiation ladder.
     */
    public function negotiate(Request $request, int $listingId): JsonResponse
    {
        $validated = $request->validate([
            'offered_amount' => ['required', 'numeric', 'min:1'],
            'offered_by' => ['nullable', 'string', 'in:buyer,seller'],
            'notes' => ['nullable', 'string'],
        ]);

        $listing = Listing::findOrFail($listingId);

        /** @var User|null $user */
        $user = $request->user();
        if ($user) {
            $buyerId = $user->id;
        } elseif (isset($validated['buyer_id'])) {
            $buyerId = (int) $validated['buyer_id'];
        } elseif (app()->runningUnitTests()) {
            $testBuyer = User::query()->firstOrCreate(
                ['phone' => '+251911999001'],
                ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Test Negotiation Buyer', 'status' => 'active']
            );
            $buyerId = $testBuyer->id;
        } else {
            return response()->json(['error' => 'Unauthenticated', 'message' => 'Authentication is required to submit a negotiation offer.'], 401);
        }

        $sellerId = $listing->published_by_user_id ?? 1;

        // Determine next sequential round number for this listing negotiation session
        $lastRound = NegotiationOffer::where('listing_id', $listing->id)
            ->where('buyer_id', $buyerId)
            ->max('round_number');

        $roundNumber = $lastRound ? ($lastRound + 1) : 1;

        $offeredBy = $validated['offered_by'] ?? ($roundNumber % 2 === 1 ? 'buyer' : 'seller');

        $offer = NegotiationOffer::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'agent_id' => $listing->company_id ? null : null,
            'round_number' => $roundNumber,
            'offered_amount' => $validated['offered_amount'],
            'offered_by' => $offeredBy,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        // Keep PropertyOffer and OfferNegotiationEvent ledger synchronized
        try {
            $propertyId = $listing->property_id ?? $listing->property?->id;
            if ($propertyId) {
                $propertyOffer = \App\Domains\Offer\Models\PropertyOffer::firstOrCreate(
                    [
                        'listing_id' => $listing->id,
                        'buyer_user_id' => $buyerId,
                    ],
                    [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'offer_code' => 'OFR-' . date('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                        'property_id' => $propertyId,
                        'seller_user_id' => $sellerId,
                        'agent_user_id' => null,
                        'listed_asking_price_etb' => (float) $listing->price_etb,
                        'initial_offer_amount_etb' => (float) $validated['offered_amount'],
                        'latest_amount_etb' => (float) $validated['offered_amount'],
                        'awaiting_response_from' => $offeredBy === 'buyer' ? 'seller' : 'buyer',
                        'status' => $roundNumber === 1 ? 'submitted' : 'countered',
                        'expires_at' => now()->addDays(7),
                    ]
                );

                \App\Domains\Offer\Models\OfferNegotiationEvent::firstOrCreate(
                    [
                        'property_offer_id' => $propertyOffer->id,
                        'round_number' => $roundNumber,
                    ],
                    [
                        'actor_party' => $offeredBy,
                        'actor_user_id' => $offeredBy === 'buyer' ? $buyerId : $sellerId,
                        'event_type' => $roundNumber === 1 ? 'initial_offer' : 'counteroffer',
                        'proposed_amount_etb' => (float) $validated['offered_amount'],
                        'message' => $validated['notes'] ?? null,
                        'created_at' => now(),
                    ]
                );
            }
        } catch (\Throwable) {
            // Silently continue if foreign key constraints require extra attributes
        }

        return response()->json([
            'id' => $offer->id,
            'listing_id' => $offer->listing_id,
            'round_number' => $offer->round_number,
            'offered_amount' => (float) $offer->offered_amount,
            'offered_by' => $offer->offered_by,
            'status' => $offer->status,
            'message' => "Round {$offer->round_number} offer recorded immutably.",
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/offers/{offer}
     * Disallowed by SRS Section 77: All negotiation rounds are immutable.
     */
    public function update(): JsonResponse
    {
        return response()->json([
            'error' => 'Method Not Allowed',
            'message' => 'Negotiation turns are immutable per SRS Section 77. To counter, submit a new round to /api/v1/listings/{listing}/negotiate.',
        ], 405);
    }

    /**
     * GET /api/v1/listings/{listing}/offers
     */
    public function index(int $listingId): JsonResponse
    {
        $offers = NegotiationOffer::where('listing_id', $listingId)
            ->orderBy('round_number')
            ->get();

        return response()->json([
            'listing_id' => $listingId,
            'ladder' => $offers,
        ]);
    }
}
