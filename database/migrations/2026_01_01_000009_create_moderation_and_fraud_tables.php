<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_signals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

            // spatial_duplicate, image_hash_match, price_anomaly, text_duplicate, suspicious_account (SRS Section 31 & 32)
            $table->string('signal_type', 40)->index();
            $table->string('severity', 16)->default('medium')->index(); // low, medium, high, critical
            $table->foreignId('matched_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('matched_listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->unsignedTinyInteger('confidence_score')->default(50); // 0..100
            $table->json('evidence_json')->nullable();

            // open, dismissed, confirmed_fraud (SRS Section 134: AI/automated flags remain review signals)
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('moderation_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('moderator_id')->constrained('users');
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->string('action_type', 32); // approve, reject, suspend, request_correction, merge_duplicate
            $table->string('reason_code', 48)->nullable();
            $table->text('feedback_to_seller')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });

        // Public Trust & Safety Property Reporting (SRS Sections 117 & 118)
        Schema::create('property_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // scam, fake_property, duplicate, wrong_price, unavailable, misleading, suspicious_documents, inappropriate_content
            $table->string('reason_code', 40)->index();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('open')->index(); // open, investigating, resolved, dismissed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_reports');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('fraud_signals');
    }
};
