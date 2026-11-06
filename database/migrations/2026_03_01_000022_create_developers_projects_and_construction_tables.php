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
        // SRS Section 103: Developer Management (License, Company, Track Record & Verification)
        Schema::create('developer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('developer_code', 36)->unique(); // e.g. DEV-ETH-000042
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('license_number', 64)->nullable()->index();
            $table->unsignedSmallInteger('establishment_year')->nullable();
            $table->unsignedSmallInteger('delivered_projects_count')->default(0);
            $table->unsignedInteger('delivered_units_count')->default(0);

            // submitted, under_review, verified, rejected, suspended, expired
            $table->string('verification_status', 24)->default('submitted')->index();
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->jsonb('track_record_json')->nullable();
            $table->text('bio_en')->nullable();
            $table->text('bio_am')->nullable();
            $table->text('bio_om')->nullable();
            $table->timestampsTz();
        });

        // SRS Section 78 & 89: Development Projects (Masterplan, Location, Land Area, Status, Permitting)
        Schema::create('development_projects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('project_code', 36)->unique(); // e.g. PRJ-ETH-2026-0012
            $table->string('slug', 160)->unique();
            $table->foreignId('developer_id')->constrained('developer_profiles')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // Administrative Location
            $table->foreignId('region_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('subcity_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('woreda_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('address_line', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Multilingual Titles & Descriptions
            $table->string('title_en', 180);
            $table->string('title_am', 180)->nullable();
            $table->string('title_om', 180)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_am')->nullable();
            $table->text('description_om')->nullable();

            // Classification & Lifecycle (SRS Section 89)
            // residential_complex, mixed_use, commercial_tower, villa_community
            $table->string('project_type', 36)->default('residential_complex')->index();
            // planning, pre_construction, under_construction, near_completion, completed, delivered, cancelled
            $table->string('status', 28)->default('planning')->index();

            $table->decimal('land_area_sqm', 14, 2)->nullable();
            $table->string('building_permit_number', 80)->nullable()->index();
            $table->date('expected_completion_date')->nullable()->index();
            $table->decimal('overall_progress_pct', 5, 2)->default(0.00);

            $table->jsonb('facilities_json')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE development_projects ADD COLUMN boundary GEOGRAPHY(MultiPolygon, 4326);');
            DB::statement('CREATE INDEX idx_development_projects_boundary_gist ON development_projects USING GIST (boundary);');
        }

        // SRS Section 90: Construction Milestones (Foundation, Structure, Masonry, Roofing, Finishing, Handover)
        Schema::create('construction_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->string('stage_code', 32); // foundation, structure, masonry, roofing, finishing, handover
            $table->string('name_en', 120);
            $table->string('name_am', 120)->nullable();
            $table->unsignedSmallInteger('order_index')->default(1);

            // Progress weight (e.g. 20.00 for 20%), all milestone weights in a project sum to 100.00%
            $table->decimal('progress_weight', 5, 2)->default(0.00);
            // Current stage completion (0.00 to 100.00%)
            $table->decimal('completion_percentage', 5, 2)->default(0.00);

            $table->boolean('is_verified')->default(false)->index();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('evidence_notes')->nullable();
            $table->timestampsTz();

            $table->unique(['project_id', 'stage_code'], 'uq_project_milestone_stage');
        });

        // Audit log of milestone updates
        Schema::create('construction_progress_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('milestone_id')->constrained('construction_milestones')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users');
            $table->decimal('previous_pct', 5, 2);
            $table->decimal('new_pct', 5, 2);
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // SRS Section 91: Developer Payment Plans
        Schema::create('developer_payment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->string('name_en', 140);
            $table->string('name_am', 140)->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestampsTz();
        });

        // Ordered Installments (e.g. Reservation 10%, Foundation 20%, Structure 20%, Roofing 20%, Finishing 20%, Handover 10%)
        Schema::create('payment_plan_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_plan_id')->constrained('developer_payment_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence_number');
            $table->string('milestone_label_en', 140);
            $table->string('milestone_label_am', 140)->nullable();
            $table->decimal('percentage', 5, 2); // e.g. 20.00
            $table->string('trigger_milestone_code', 32)->nullable(); // links to construction_milestones.stage_code
            $table->unsignedSmallInteger('due_days_after_trigger')->default(0);
            $table->timestampsTz();

            $table->unique(['payment_plan_id', 'sequence_number'], 'uq_payment_plan_sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
        Schema::dropIfExists('developer_payment_plans');
        Schema::dropIfExists('construction_progress_logs');
        Schema::dropIfExists('construction_milestones');
        Schema::dropIfExists('development_projects');
        Schema::dropIfExists('developer_profiles');
    }
};
