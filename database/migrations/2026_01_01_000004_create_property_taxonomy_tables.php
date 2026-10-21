<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->unique(); // RES, LND, COM, IND, HSP, SPC
            $table->string('slug', 80)->unique();
            $table->string('name_en', 120);
            $table->string('name_am', 120)->nullable();
            $table->string('name_om', 120)->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('property_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('property_categories')->cascadeOnDelete();
            $table->string('code', 24)->unique(); // VILLA, APT, CONDO, RES_LAND, OFFICE, WAREHOUSE...
            $table->string('slug', 100)->unique();
            $table->string('name_en', 120);
            $table->string('name_am', 120)->nullable();
            $table->string('name_om', 120)->nullable();
            // SRS Section 36: Different property types use appropriate area calculations for Price/m²
            $table->string('area_basis', 24)->default('building_area'); // building_area, land_area
            $table->boolean('requires_bedrooms')->default(true);
            $table->boolean('requires_floors')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('property_amenities', function (Blueprint $table): void {
            $table->id();
            // internet, kitchen, outdoor, building, utilities, security, family (SRS Section 44)
            $table->string('group_key', 40)->index();
            $table->string('slug', 80)->unique();
            $table->string('name_en', 120);
            $table->string('name_am', 120)->nullable();
            $table->string('name_om', 120)->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('property_features', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique(); // e.g. zoning_type, road_access_width_m, power_phase_kva
            $table->string('name_en', 120);
            $table->string('name_am', 120)->nullable();
            $table->string('name_om', 120)->nullable();
            $table->string('input_type', 24)->default('text'); // number, select, boolean, text
            $table->json('options_json')->nullable();
            $table->string('unit', 24)->nullable(); // m, kVA, years
            $table->json('applicable_category_codes'); // e.g. ["LND"] or ["COM", "IND"]
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('listing_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique(); // FOR_SALE, FOR_RENT, FOR_LEASE, SHORT_STAY, NEW_DEV...
            $table->string('slug', 80)->unique();
            // SRS Section 8: BUY, RENT, LEASE, STAY, NEW_PROJECTS
            $table->string('transaction_mode', 24)->index();
            $table->string('name_en', 120);
            $table->string('name_am', 120)->nullable();
            $table->string('name_om', 120)->nullable();
            $table->string('default_price_period', 24)->default('total'); // total, per_month, per_year, per_night
            $table->boolean('is_phase1_enabled')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_types');
        Schema::dropIfExists('property_features');
        Schema::dropIfExists('property_amenities');
        Schema::dropIfExists('property_types');
        Schema::dropIfExists('property_categories');
    }
};
