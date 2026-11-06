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
        // 1. Property Virtual Tours (Module 34.1)
        Schema::create('property_virtual_tours', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('tour_type', 32)->default('equirectangular_panorama'); // equirectangular_panorama, interactive_walkthrough, external_link
            $table->string('external_tour_url', 512)->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->json('metadata_json')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['property_id', 'is_published'], 'idx_property_virtual_tours_public');
        });

        // 2. Virtual Tour Scenes / Rooms (Module 34.1 & 34.2)
        Schema::create('property_tour_scenes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained('property_virtual_tours')->cascadeOnDelete();
            $table->string('room_name', 100); // Living Room, Master Bedroom, Kitchen, Balcony, etc.
            $table->string('panorama_image_path', 512); // Equirectangular 360 photo
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('initial_pitch', 6, 2)->default(0.00); // Vertical angle
            $table->decimal('initial_yaw', 6, 2)->default(0.00); // Horizontal angle
            $table->decimal('initial_zoom', 6, 2)->default(100.00);
            $table->boolean('is_default_entry')->default(false);
            $table->timestampsTz();

            $table->index(['tour_id', 'sort_order'], 'idx_tour_scenes_order');
        });

        // 3. Room-to-Room Navigation Hotspots (Module 34.2)
        Schema::create('property_tour_hotspots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scene_id')->constrained('property_tour_scenes')->cascadeOnDelete();
            $table->foreignId('target_scene_id')->constrained('property_tour_scenes')->cascadeOnDelete();
            $table->string('title', 100); // e.g. "To Master Bedroom"
            $table->decimal('pitch', 6, 2); // -90 to +90
            $table->decimal('yaw', 6, 2); // -180 to +180
            $table->string('icon', 40)->default('arrow_forward');
            $table->timestampsTz();

            $table->index(['scene_id'], 'idx_hotspots_scene');
        });

        // 4. Neighborhood ETB/m² Heatmaps (Module 34.5)
        Schema::create('gis_neighborhood_heatmaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete(); // Neighborhood, Woreda, or Subcity
            $table->string('transaction_mode', 24)->index(); // BUY, RENT, LEASE
            $table->unsignedInteger('sample_size')->default(0);
            $table->decimal('avg_price_per_sqm_etb', 14, 2)->default(0.00)->index();
            $table->decimal('median_price_per_sqm_etb', 14, 2)->default(0.00);
            $table->decimal('min_price_per_sqm_etb', 14, 2)->default(0.00);
            $table->decimal('max_price_per_sqm_etb', 14, 2)->default(0.00);
            $table->unsignedTinyInteger('price_tier')->default(1)->index(); // 1 to 5
            $table->date('snapshot_date')->index();
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->json('boundary_geojson')->nullable(); // GeoJSON polygon coordinates
            $table->boolean('platform_asking_price_indicator')->default(true); // Mandatory disclosure
            $table->timestampsTz();

            $table->unique(['location_id', 'transaction_mode', 'snapshot_date'], 'idx_heatmaps_loc_mode_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_neighborhood_heatmaps');
        Schema::dropIfExists('property_tour_hotspots');
        Schema::dropIfExists('property_tour_scenes');
        Schema::dropIfExists('property_virtual_tours');
    }
};

