<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Polymorphic target: User (Seller/Agent), Company (Agency/Developer), or Property
            $table->string('verifiable_type', 140);
            $table->unsignedBigInteger('verifiable_id');
            $table->foreignId('requested_by_user_id')->constrained('users');

            // SRS Section 22: Levels 0..5
            $table->unsignedTinyInteger('requested_level')->default(1);
            $table->unsignedTinyInteger('approved_level')->default(0)->index();

            // SRS Section 23: verified_seller, verified_company, verified_developer, documents_submitted, professionally_reviewed
            $table->string('badge_code', 48)->nullable()->index();

            // pending, under_review, approved, rejected, more_info_required
            $table->string('status', 32)->default('pending')->index();

            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->text('internal_officer_notes')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['verifiable_type', 'verifiable_id', 'status'], 'idx_verifications_target_status');
        });

        Schema::create('verification_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verification_id')->constrained('verifications')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->boolean('is_verified')->default(false);
            $table->string('officer_remark', 300)->nullable();
            $table->timestampsTz();

            $table->unique(['verification_id', 'document_version_id'], 'uq_verification_doc_ver');
        });

        // Immutable Verification Review History (SRS Section 24 & 142)
        Schema::create('verification_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verification_id')->constrained('verifications')->cascadeOnDelete();
            $table->foreignId('officer_id')->constrained('users');
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->unsignedTinyInteger('granted_level')->default(0);
            $table->string('decision_reason', 500);
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_reviews');
        Schema::dropIfExists('verification_documents');
        Schema::dropIfExists('verifications');
    }
};
