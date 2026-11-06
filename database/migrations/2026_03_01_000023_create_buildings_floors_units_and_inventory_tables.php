<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 92: Buildings Hierarchy within Development Projects
        Schema::create('project_buildings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->string('building_code', 32); // e.g. TOWER-A, BLOCK-1
            $table->string('name_en', 140);
            $table->string('name_am', 140)->nullable();
            $table->unsignedSmallInteger('floors_count')->default(1);
            $table->unsignedSmallInteger('basement_floors_count')->default(0);
            $table->jsonb('media_json')->nullable();
            $table->timestampsTz();

            $table->unique(['project_id', 'building_code'], 'uq_project_building_code');
        });

        // SRS Section 92: Floors Hierarchy
        Schema::create('building_floors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained('project_buildings')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->smallInteger('floor_number'); // e.g. -2, -1, 0, 1, 2, ...
            $table->string('floor_label', 64); // e.g. Ground Floor, Floor 1, Penthouse Level
            $table->unsignedSmallInteger('units_count')->default(0);
            $table->foreignId('floor_plan_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['building_id', 'floor_number'], 'uq_building_floor_number');
        });

        // SRS Section 93: Project Units & Authoritative Inventory (available, reserved, contracted, sold, blocked)
        Schema::create('project_units', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->foreignId('building_id')->constrained('project_buildings')->cascadeOnDelete();
            $table->foreignId('floor_id')->constrained('building_floors')->cascadeOnDelete();

            $table->string('unit_number', 32); // e.g. U-101, A-402, PH-1
            $table->smallInteger('floor_number');

            // studio, 1br, 2br, 3br, 4br, penthouse, commercial (SRS Section 93)
            $table->string('unit_type', 32)->default('2br')->index();
            $table->decimal('area_sqm', 10, 2);
            $table->unsignedTinyInteger('bedrooms')->default(2);
            $table->unsignedTinyInteger('bathrooms')->default(2);

            // Financial & Pricing
            $table->decimal('price_etb', 18, 2)->index();
            $table->decimal('price_per_sqm_etb', 18, 2)->index();

            // Specifications
            $table->string('orientation', 32)->nullable(); // north, south, east, west, north_east...
            $table->unsignedTinyInteger('parking_allocation')->default(1);
            $table->foreignId('floor_plan_document_id')->nullable()->constrained('documents')->nullOnDelete();

            // Authoritative Inventory Status (SRS Section 94)
            // available, reserved, contracted, sold, blocked
            $table->string('status', 24)->default('available')->index();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['building_id', 'unit_number'], 'uq_building_unit_number');
            $table->index(['project_id', 'status'], 'idx_project_units_inventory');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_units');
        Schema::dropIfExists('building_floors');
        Schema::dropIfExists('project_buildings');
    }
};
