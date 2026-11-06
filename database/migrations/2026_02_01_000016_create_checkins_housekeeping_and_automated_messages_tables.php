<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 58: Check-In Record (Future-ready for QR check-in, smart lock & digital access code)
        Schema::create('check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            // host_manual, guest_self, qr_code, smart_lock_code
            $table->string('check_in_method', 32)->default('guest_self');
            $table->string('digital_access_code_used', 64)->nullable();
            $table->timestampTz('house_manual_unlocked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('checked_in_at')->useCurrent();
            $table->timestampsTz();
        });

        // SRS Section 59: Check-Out Record (checkout time, property condition, damage reports, completion)
        Schema::create('check_outs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            // excellent, good, minor_issue, damage_reported
            $table->string('property_condition', 32)->default('good')->index();
            $table->text('condition_notes')->nullable();
            $table->json('condition_photos_json')->nullable();
            $table->boolean('damage_claim_required')->default(false);
            $table->timestampTz('checked_out_at')->useCurrent();
            $table->timestampsTz();
        });

        // SRS Section 63: Housekeeping Staff / Cleaners
        Schema::create('cleaners', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('host_id')->constrained('hosts')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name', 140);
            $table->string('phone', 20);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        // SRS Section 63: Housekeeping Workflow (Checkout -> Cleaning Task -> Cleaner Assigned -> Cleaning -> Inspection -> Ready)
        Schema::create('cleaning_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('task_code', 36)->unique(); // e.g. CLN-2026-000412
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('accommodation_unit_id')->constrained('accommodation_units')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('hosts')->cascadeOnDelete();
            $table->foreignId('cleaner_id')->nullable()->constrained('cleaners')->nullOnDelete();

            $table->date('scheduled_date')->index();
            // pending (Cleaning Task), assigned (Cleaner Assigned), cleaning (Cleaning), inspection (Inspection), ready (Ready)
            $table->string('status', 24)->default('pending')->index();
            $table->json('checklist_json')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->foreignId('inspected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('inspected_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampsTz();

            $table->index(['host_id', 'scheduled_date', 'status'], 'idx_cleaning_tasks_host_board');
        });

        // SRS Section 112: Automated Short-Stay Lifecycle Messages Log
        Schema::create('stay_automated_message_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            // booking_confirmation, days_before_7, days_before_3, days_before_1, check_in_day, checkout_day, review_request
            $table->string('trigger_code', 36);
            $table->string('channel', 24)->default('in_app');
            $table->timestampTz('sent_at')->useCurrent();

            $table->unique(['booking_id', 'trigger_code'], 'uq_booking_automated_trigger');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_automated_message_logs');
        Schema::dropIfExists('cleaning_tasks');
        Schema::dropIfExists('cleaners');
        Schema::dropIfExists('check_outs');
        Schema::dropIfExists('check_ins');
    }
};
