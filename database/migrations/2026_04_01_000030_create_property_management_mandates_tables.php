<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 196 & Module 29: Property Management Mandates
        Schema::create('property_management_mandates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('mandate_code', 36)->unique(); // e.g. MND-2026-0000001

            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('owner_user_id')->constrained('users');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->nullOnDelete();
            $table->foreignId('assigned_manager_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Management fee percentage (0.00% to 100.00%)
            $table->decimal('management_fee_pct', 5, 2)->default(10.00);

            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();

            // Status: active, suspended, expired, terminated
            $table->string('status', 24)->default('active')->index();

            $table->text('terms')->nullable();
            $table->string('termination_reason', 300)->nullable();
            $table->timestampTz('terminated_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'status'], 'idx_mandates_company_status');
            $table->index(['property_id', 'status'], 'idx_mandates_property_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_management_mandates');
    }
};
