<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Sections 64-65 & Module 28: Maintenance Requests & Work Orders
        Schema::create('maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ticket_code', 36)->unique(); // e.g. MNT-2026-000612

            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('project_unit_id')->nullable()->constrained('project_units')->nullOnDelete();
            $table->foreignId('accommodation_unit_id')->nullable()->constrained('accommodation_units')->nullOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained('leases')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->nullOnDelete();
            $table->foreignId('reported_by_user_id')->constrained('users');

            // 7 Standard Categories (SRS Section 64)
            // plumbing, electricity, internet, appliance, structural, cleaning, other
            $table->string('category', 32)->index();

            // 4 Priorities
            // low, normal, high, emergency
            $table->string('priority', 24)->default('normal')->index();

            $table->string('title', 180);
            $table->text('description');

            // Staff Technician or External Contractor Assignment
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contractor_name', 140)->nullable();
            $table->string('contractor_phone', 32)->nullable();

            // Financial & Costs in ETB (Decimal-Safe)
            $table->decimal('estimated_cost_etb', 18, 2)->default(0.00);
            $table->decimal('actual_cost_etb', 18, 2)->default(0.00);
            // billable_party: owner, tenant, company, warranty
            $table->string('billable_party', 32)->default('owner');

            // 6 Lifecycle Statuses (SRS Section 65)
            // open, assigned, in_progress, waiting, completed, closed
            $table->string('status', 24)->default('open')->index();

            $table->text('resolution_notes')->nullable();

            // Milestone Timestamps
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['property_id', 'status'], 'idx_maintenance_property_status');
            $table->index(['company_id', 'branch_id', 'status'], 'idx_maintenance_company_branch_status');
        });

        // Operational Audit Trail & Status History (SRS Section 65)
        Schema::create('maintenance_request_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->text('notes')->nullable();
            $table->decimal('cost_snapshot_etb', 18, 2)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // Maintenance Photographs & Attachments
        Schema::create('maintenance_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->string('storage_disk', 32)->default('public');
            $table->string('file_path', 512);
            $table->string('file_name', 255);
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('caption', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_attachments');
        Schema::dropIfExists('maintenance_request_histories');
        Schema::dropIfExists('maintenance_requests');
    }
};
