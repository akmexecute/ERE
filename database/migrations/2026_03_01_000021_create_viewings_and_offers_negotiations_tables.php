<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Sections 71 & 72: Property Viewings & Appointments
        Schema::create('viewing_appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('appointment_code', 36)->unique(); // e.g. VWG-2026-000219
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users');
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_or_agent_user_id')->constrained('users');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();

            $table->date('scheduled_date')->index();
            $table->string('scheduled_time_slot', 32); // e.g. "15:00 - 16:00"
            $table->string('slot_start_time', 8)->nullable()->index();
            $table->string('slot_end_time', 8)->nullable()->index();
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->string('viewing_type', 24)->default('in_person'); // in_person, live_video

            // SRS Section 72: requested, confirmed, rescheduled, completed, cancelled, no_show
            $table->string('status', 24)->default('requested')->index();
            $table->text('visitor_notes')->nullable();
            $table->text('agent_confirmation_notes')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['seller_or_agent_user_id', 'scheduled_date', 'status'], 'idx_viewings_agent_calendar');
        });

        // Dedicated property_viewings table for API / services
        Schema::create('property_viewings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users');
            $table->foreignId('client_id')->constrained('users');
            $table->timestampTz('scheduled_at')->index();
            // requested, confirmed, rescheduled, completed, cancelled, no_show
            $table->string('status', 24)->default('requested')->index();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        // SRS Sections 76 & 77: Offer Management & Multi-Round Negotiation
        Schema::create('property_offers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('offer_code', 36)->unique(); // e.g. OFR-2026-000089
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users');
            $table->foreignId('seller_user_id')->constrained('users');
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();

            $table->decimal('listed_asking_price_etb', 18, 2); // e.g. 25,000,000 ETB (SRS Section 76/77)
            $table->decimal('initial_offer_amount_etb', 18, 2); // e.g. 22,000,000 ETB
            $table->decimal('latest_amount_etb', 18, 2); // e.g. 23,500,000 ETB
            $table->string('awaiting_response_from', 16)->nullable()->default('seller'); // buyer, seller

            // submitted, countered, accepted, rejected, withdrawn, expired (SRS Section 76)
            $table->string('status', 24)->default('submitted')->index();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampsTz();

            $table->index(['listing_id', 'status'], 'idx_offers_listing_status');
        });

        // SRS Section 77: All negotiation events are recorded (Seller: 25M -> Buyer: 22M -> Seller: 24M -> Buyer: 23M -> Seller: 23.5M)
        Schema::create('offer_negotiation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_offer_id')->constrained('property_offers')->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->string('actor_party', 16); // buyer, seller
            $table->foreignId('actor_user_id')->constrained('users');
            // initial_offer, counteroffer, accepted, rejected, withdrawn
            $table->string('event_type', 24);
            $table->decimal('proposed_amount_etb', 18, 2);
            $table->text('message')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['property_offer_id', 'round_number'], 'uq_offer_negotiation_round');
        });

        // Immutable Negotiation Offers Ladder Ledger (Listing, Buyer, Seller, Agent, Round, Offered Amount, Offered By, Status)
        Schema::create('negotiation_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained('users');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('round_number')->default(1);
            $table->decimal('offered_amount', 18, 2);
            $table->string('offered_by', 16); // buyer, seller
            $table->string('status', 24)->default('pending')->index(); // pending, countered, accepted, rejected
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['listing_id', 'buyer_id', 'round_number'], 'uq_negotiation_ladder_turn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiation_offers');
        Schema::dropIfExists('offer_negotiation_events');
        Schema::dropIfExists('property_offers');
        Schema::dropIfExists('property_viewings');
        Schema::dropIfExists('viewing_appointments');
    }
};
