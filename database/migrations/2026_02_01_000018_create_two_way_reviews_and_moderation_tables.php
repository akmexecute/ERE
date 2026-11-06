<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Sections 69 & 70: Two-Way Review Ecosystem (Guest -> Host & Host -> Guest)
        Schema::create('stay_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('hosts')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users');
            $table->foreignId('reviewee_user_id')->constrained('users');

            // guest_to_host vs host_to_guest (SRS Section 69)
            $table->string('direction', 24)->index();

            // Shared Criteria (1..5)
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('communication_rating');

            // Guest -> Host Criteria (SRS Section 70: cleanliness, accuracy, communication, location, value, overall)
            $table->unsignedTinyInteger('cleanliness_rating')->nullable();
            $table->unsignedTinyInteger('accuracy_rating')->nullable();
            $table->unsignedTinyInteger('location_rating')->nullable();
            $table->unsignedTinyInteger('value_rating')->nullable();

            // Host -> Guest Criteria (SRS Section 70: communication, property care, rule compliance)
            $table->unsignedTinyInteger('property_care_rating')->nullable();
            $table->unsignedTinyInteger('rule_compliance_rating')->nullable();

            $table->text('public_comment');
            $table->text('private_feedback_note')->nullable();

            // Double-blind publication & SRS Section 119 Review Moderation
            $table->boolean('is_published')->default(false)->index();
            // approved, flagged_spam, under_investigation, hidden, removed
            $table->string('moderation_status', 28)->default('approved')->index();
            $table->string('moderation_flag_reason', 255)->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->unique(['booking_id', 'direction'], 'uq_stay_reviews_booking_direction');
        });

        // SRS Section 119: Review Moderation Audit Log (hide, investigate, restore, remove)
        Schema::create('review_moderation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_review_id')->constrained('stay_reviews')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users');
            $table->string('action', 24); // hide, investigate, restore, remove
            $table->string('reason', 400);
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_moderation_logs');
        Schema::dropIfExists('stay_reviews');
    }
};
