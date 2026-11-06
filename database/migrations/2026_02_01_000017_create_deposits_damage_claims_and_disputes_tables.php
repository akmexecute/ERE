<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 66: Security Deposit (Held, Released, Partially Deducted, Disputed)
        Schema::create('security_deposits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('hosts');
            $table->foreignId('guest_user_id')->constrained('users');

            $table->decimal('held_amount_etb', 18, 2);
            $table->decimal('deducted_amount_etb', 18, 2)->default(0.00);
            $table->decimal('released_to_guest_etb', 18, 2)->default(0.00);

            // held, released, partially_deducted, disputed (SRS Section 66)
            $table->string('status', 28)->default('held')->index();
            // SRS Section 66: Must use an appropriately authorized payment arrangement for actual custody of funds
            $table->string('custody_provider', 48)->default('licensed_escrow_partner');
            $table->string('custody_transaction_ref', 80)->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
        });

        // SRS Section 67: Damage Claim (Host submits description, amount, photos, evidence; Guest accepts or disputes)
        Schema::create('damage_claims', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('claim_code', 36)->unique(); // e.g. CLM-2026-000042
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('security_deposit_id')->nullable()->constrained('security_deposits')->nullOnDelete();
            $table->foreignId('host_id')->constrained('hosts');
            $table->foreignId('guest_user_id')->constrained('users');

            $table->decimal('claimed_amount_etb', 18, 2);
            $table->decimal('approved_amount_etb', 18, 2)->nullable();
            $table->text('description');
            $table->json('evidence_photos_json')->nullable();

            // submitted, accepted_by_guest, disputed_by_guest, resolved
            $table->string('status', 28)->default('submitted')->index();
            $table->text('guest_response_notes')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();
        });

        // SRS Section 68: Dispute Management (Dispute -> Evidence -> Review -> Resolution -> Closed)
        Schema::create('disputes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('dispute_code', 36)->unique(); // e.g. DSP-2026-000019
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('damage_claim_id')->nullable()->constrained('damage_claims')->nullOnDelete();
            $table->foreignId('opened_by_user_id')->constrained('users');
            $table->foreignId('against_user_id')->constrained('users');

            // cancellation, refund, property_condition, damage, inaccurate_listing, host_behavior, guest_behavior, payment (SRS Section 68)
            $table->string('category', 36)->index();

            // dispute, evidence, review, resolution, closed (SRS Section 68)
            $table->string('status', 24)->default('dispute')->index();
            $table->text('issue_description');
            $table->text('resolution_summary')->nullable();
            $table->decimal('refund_to_guest_etb', 18, 2)->default(0.00);
            $table->decimal('payout_to_host_etb', 18, 2)->default(0.00);
            $table->foreignId('resolved_by_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('dispute_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users');
            $table->string('evidence_type', 32)->default('photo'); // photo, document, receipt, chat_log
            $table->text('description')->nullable();
            $table->string('storage_path', 512);
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_evidences');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('damage_claims');
        Schema::dropIfExists('security_deposits');
    }
};
