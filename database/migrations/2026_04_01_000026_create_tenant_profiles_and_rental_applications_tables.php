<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 145 & Module 25: Tenant Profiles with Protected Identity & Employment Data
        Schema::create('tenant_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_code', 36)->unique(); // e.g. TNT-2026-0000001
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Employment & Income (SRS Section 145)
            $table->string('occupation', 120)->nullable();
            $table->string('employer_name', 160)->nullable();
            $table->decimal('monthly_income_etb', 18, 2)->nullable();

            // Emergency Contact Information
            $table->string('emergency_contact_name', 160)->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->string('emergency_contact_relationship', 60)->nullable();

            // Verified Identity & Income Documents (Private Vault references)
            $table->foreignId('identity_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('income_proof_document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->boolean('is_identity_verified')->default(false)->index();
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            // Lifecycle status: active, suspended, archived
            $table->string('status', 24)->default('active')->index();

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Structured Rental History (Distinguishing verified platform leases vs tenant-reported)
        Schema::create('tenant_rental_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_profile_id')->constrained('tenant_profiles')->cascadeOnDelete();
            $table->string('property_name', 200);
            $table->string('landlord_or_agency_name', 160);
            $table->string('contact_phone', 32)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_rent_etb', 18, 2)->nullable();
            $table->boolean('is_verified_platform_record')->default(false)->index();
            $table->unsignedBigInteger('verified_lease_id')->nullable()->index();
            $table->timestampsTz();
        });

        // Rental Applications (SRS Section 146 & Module 25)
        Schema::create('rental_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('application_code', 36)->unique(); // e.g. APP-2026-0000001
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->foreignId('project_unit_id')->nullable()->constrained('project_units')->nullOnDelete();

            $table->foreignId('tenant_id')->constrained('users');
            $table->foreignId('tenant_profile_id')->constrained('tenant_profiles');
            $table->foreignId('landlord_id')->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // Desired Lease Parameters
            $table->date('desired_move_in_date');
            $table->unsignedSmallInteger('lease_duration_months')->default(12);
            $table->decimal('proposed_monthly_rent_etb', 18, 2);
            $table->string('payment_frequency', 24)->default('monthly'); // monthly, quarterly, semi_annual, annual
            $table->unsignedSmallInteger('occupant_count')->default(1);
            $table->text('applicant_message')->nullable();

            // Application Lifecycle (SRS Section 147)
            // submitted, document_review, approved, rejected, withdrawn, converted_to_lease
            $table->string('status', 28)->default('submitted')->index();

            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('rejection_reason', 300)->nullable();
            $table->unsignedBigInteger('converted_lease_id')->nullable()->index();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['property_id', 'status'], 'idx_rent_app_prop_status');
            $table->index(['tenant_id', 'status'], 'idx_rent_app_tenant_status');
            $table->index(['landlord_id', 'status'], 'idx_rent_app_landlord_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_applications');
        Schema::dropIfExists('tenant_rental_histories');
        Schema::dropIfExists('tenant_profiles');
    }
};
