<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rental Application Documents (Module 25: Tracking applicant uploaded verification documents)
        Schema::create('rental_application_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('rental_application_id')->constrained('rental_applications')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();

            // document_type: national_id, income_proof, bank_statement, employment_letter, reference_letter, other
            $table->string('document_type', 40)->default('other')->index();

            // review_status: pending, approved, rejected
            $table->string('review_status', 24)->default('pending')->index();
            $table->string('rejection_reason', 300)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();

            $table->timestampsTz();

            $table->unique(['rental_application_id', 'document_id'], 'uq_rent_app_doc');
        });

        // 2. Rental Application Transition History (Module 25: Immutable audit trail for application lifecycle)
        Schema::create('rental_application_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_application_id')->constrained('rental_applications')->cascadeOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['rental_application_id', 'created_at'], 'idx_rent_app_hist_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_application_histories');
        Schema::dropIfExists('rental_application_documents');
    }
};

