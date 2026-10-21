<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 33: Favorites
        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'listing_id'], 'uq_favorites_user_listing');
            $table->index(['user_id', 'created_at'], 'idx_favorites_user_timeline');
        });

        // SRS Section 34: Private Collections (e.g. "My Dream Homes", "Investment", "Weekend Stays")
        Schema::create('saved_collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_private')->default(true);
            $table->timestampsTz();
        });

        Schema::create('collection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained('saved_collections')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('personal_note', 300)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['collection_id', 'listing_id'], 'uq_collection_listing');
        });

        // SRS Sections 109 & 173: Search Alerts (e.g. Bole, Villa, 4+ Bedrooms, <30M ETB)
        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 140);
            $table->json('filters_json');
            $table->string('frequency', 24)->default('instant'); // instant, daily, weekly
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('last_notified_at')->nullable();
            $table->timestampsTz();
        });

        // SRS Section 110: Price & Availability Alerts
        Schema::create('price_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->decimal('baseline_price_etb', 18, 2);
            $table->boolean('notify_on_price_drop')->default(true);
            $table->boolean('notify_on_status_change')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('last_triggered_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'listing_id'], 'uq_price_alerts_user_listing');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('saved_collections');
        Schema::dropIfExists('favorites');
    }
};
