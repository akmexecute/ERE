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
        // Atomic sequence counter per City + Area + Category for ETH-AA-BOLE-RES-00001245 (SRS Section 9)
        Schema::create('property_code_sequences', function (Blueprint $table): void {
            $table->string('prefix_key', 48)->primary(); // e.g. ETH-AA-BOLE-RES
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // SRS Section 9: e.g. ETH-AA-BOLE-RES-00001245
            $table->string('property_code', 48)->unique();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('ownership_relationship', 32)->default('direct_owner');

            // Taxonomy
            $table->foreignId('category_id')->constrained('property_categories');
            $table->foreignId('property_type_id')->constrained('property_types');

            // Administrative Location Hierarchy (SRS Section 11)
            $table->foreignId('region_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('subcity_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('woreda_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('kebele_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('neighborhood_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('address_line', 255)->nullable();
            $table->string('landmark_notes', 255)->nullable();

            // Multilingual Titles & Descriptions
            $table->string('title_en', 200);
            $table->string('title_am', 200)->nullable();
            $table->string('title_om', 200)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_am')->nullable();
            $table->text('description_om')->nullable();

            // Physical Information (SRS Section 10)
            $table->decimal('land_area_sqm', 12, 2)->nullable()->index();
            $table->decimal('building_area_sqm', 12, 2)->nullable()->index();
            $table->decimal('compound_size_sqm', 12, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable()->index();
            $table->unsignedSmallInteger('bathrooms')->nullable()->index();
            $table->unsignedSmallInteger('total_rooms')->nullable();
            $table->unsignedSmallInteger('floors')->nullable();
            $table->smallInteger('floor_level')->nullable();
            $table->unsignedSmallInteger('parking_spaces')->default(0);
            $table->unsignedSmallInteger('balconies')->default(0);

            // Construction Information (SRS Section 10)
            $table->unsignedSmallInteger('construction_year')->nullable();
            $table->string('condition', 32)->default('good'); // brand_new, excellent, good, needs_renovation, under_construction
            $table->string('construction_status', 32)->default('completed'); // completed, under_construction, off_plan
            $table->string('construction_material', 80)->nullable();
            $table->unsignedTinyInteger('completion_percentage')->default(100);

            // Utilities (SRS Section 10)
            $table->string('electricity_type', 40)->default('grid_single_phase'); // grid_single_phase, grid_three_phase, none
            $table->string('water_supply', 40)->default('municipal'); // municipal, borehole, tanker, none
            $table->string('sewer_type', 40)->default('municipal'); // municipal, septic_tank
            $table->string('internet_type', 40)->default('fiber_ready');
            $table->boolean('has_generator')->default(false);
            $table->boolean('has_solar')->default(false);
            $table->boolean('has_security')->default(false);

            // GIS Coordinates & Privacy
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_exact_location_public')->default(true);

            // Lifecycle & Verification (SRS Section 22 & 154)
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedTinyInteger('verification_level')->default(0)->index();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['category_id', 'property_type_id', 'status'], 'idx_properties_cat_type_status');
            $table->index(['city_id', 'subcity_id', 'status'], 'idx_properties_location_status');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE properties ADD COLUMN coordinates GEOGRAPHY(Point, 4326);');
            DB::statement('ALTER TABLE properties ADD COLUMN parcel_polygon GEOGRAPHY(Polygon, 4326);');
            DB::statement('CREATE INDEX idx_properties_coordinates_gist ON properties USING GIST (coordinates);');
            DB::statement('CREATE INDEX idx_properties_parcel_polygon_gist ON properties USING GIST (parcel_polygon);');
        }

        Schema::create('property_amenity_pivot', function (Blueprint $table): void {
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained('property_amenities')->cascadeOnDelete();
            $table->primary(['property_id', 'amenity_id']);
        });

        Schema::create('property_feature_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('property_features')->cascadeOnDelete();
            $table->string('value_text', 255);
            $table->timestampsTz();
            $table->unique(['property_id', 'feature_id']);
        });

        // Auditable Property Status History (SRS Section 211 & 213)
        Schema::create('property_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 300)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_status_histories');
        Schema::dropIfExists('property_feature_values');
        Schema::dropIfExists('property_amenity_pivot');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('property_code_sequences');
    }
};
