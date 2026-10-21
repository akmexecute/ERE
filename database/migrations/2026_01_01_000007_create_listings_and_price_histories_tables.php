<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('listing_code', 40)->unique(); // e.g. LST-2026-0001245
            $table->string('slug', 220)->unique();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('published_by_user_id')->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('listing_type_id')->constrained('listing_types');

            // BUY, RENT, LEASE, STAY, NEW_PROJECTS (SRS Section 8)
            $table->string('transaction_mode', 24)->index();

            // Financial & Price Engine (SRS Section 36)
            $table->decimal('price_etb', 18, 2)->index();
            $table->string('currency', 8)->default('ETB');
            $table->string('price_period', 24)->default('total'); // total, per_month, per_year, per_night
            $table->decimal('price_per_sqm_etb', 18, 2)->nullable()->index();
            $table->boolean('is_negotiable')->default(true);

            // Listing Quality & Completeness Assistant (SRS Sections 165 & 203)
            $table->unsignedTinyInteger('quality_score')->default(0)->index();
            $table->json('missing_quality_fields')->nullable();

            // Seller Phone Privacy Override (SRS Section 75)
            $table->string('phone_privacy_override', 24)->nullable();

            // Lifecycle Status (SRS Section 153)
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->string('promotion_tier', 24)->default('standard'); // standard, featured, premium, urgent

            // Engagement Counters
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('favorites_count')->default(0);
            $table->unsignedInteger('inquiries_count')->default(0);

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['transaction_mode', 'status', 'is_featured'], 'idx_listings_marketplace_feed');
            $table->index(['property_id', 'transaction_mode', 'status'], 'idx_listings_property_mode');
        });

        // Immutable Price History (SRS Sections 189 & 212)
        Schema::create('listing_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->decimal('old_price_etb', 18, 2);
            $table->decimal('new_price_etb', 18, 2);
            $table->decimal('change_amount_etb', 18, 2);
            $table->decimal('change_percentage', 8, 2);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_price_histories');
        Schema::dropIfExists('listings');
    }
};
