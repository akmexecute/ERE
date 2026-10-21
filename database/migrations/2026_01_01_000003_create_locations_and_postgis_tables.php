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
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
        }

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            // country, region, zone, city, subcity, woreda, kebele, neighborhood
            $table->string('level', 24)->index();
            $table->string('code', 24)->index(); // e.g. ETH, AA, BOLE, HARAR, JIJIGA
            $table->string('slug', 140)->unique();
            $table->string('ancestry_path', 255)->nullable()->index(); // e.g. /1/2/5/18/
            $table->string('name_en', 140)->index();
            $table->string('name_am', 140)->nullable()->index();
            $table->string('name_om', 140)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['parent_id', 'is_active', 'sort_order'], 'idx_locations_hierarchy');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE locations ADD COLUMN center_point GEOGRAPHY(Point, 4326);');
            DB::statement('ALTER TABLE locations ADD COLUMN boundary GEOGRAPHY(MultiPolygon, 4326);');
            DB::statement('CREATE INDEX idx_locations_center_point_gist ON locations USING GIST (center_point);');
            DB::statement('CREATE INDEX idx_locations_boundary_gist ON locations USING GIST (boundary);');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
