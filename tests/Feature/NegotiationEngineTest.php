<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NegotiationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_enforces_an_immutable_negotiation_ladder_sequence(): void
    {
        $listing = Listing::factory()->create(['price_etb' => 25000000]);

        // Round 1: Buyer offers 22M
        $response1 = $this->postJson("/api/v1/listings/{$listing->id}/negotiate", [
            'offered_amount' => 22000000,
        ]);
        $response1->assertStatus(201)->assertJson(['round_number' => 1]);

        // Round 2: Attempting to patch/mutate Round 1 directly must fail
        $offerId = $response1->json('id');
        $this->putJson("/api/v1/offers/{$offerId}", ['offered_amount' => 23000000])
            ->assertStatus(405); // Method Not Allowed / Immutable
    }

    public function test_advances_negotiation_ladder_turns_sequentially(): void
    {
        $listing = Listing::factory()->create(['price_etb' => 25000000]);

        // Round 1: Buyer 22M
        $res1 = $this->postJson("/api/v1/listings/{$listing->id}/negotiate", ['offered_amount' => 22000000]);
        $res1->assertStatus(201)->assertJson(['round_number' => 1, 'offered_by' => 'buyer']);

        // Round 2: Seller 24M
        $res2 = $this->postJson("/api/v1/listings/{$listing->id}/negotiate", ['offered_amount' => 24000000]);
        $res2->assertStatus(201)->assertJson(['round_number' => 2, 'offered_by' => 'seller']);

        // Check index
        $index = $this->getJson("/api/v1/listings/{$listing->id}/offers");
        $index->assertStatus(200)->assertJsonCount(2, 'ladder');
    }
}
