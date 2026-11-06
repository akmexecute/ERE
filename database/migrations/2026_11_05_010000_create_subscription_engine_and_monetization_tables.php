<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dynamic Administrator-Managed Subscription Features (Module 33.1)
        Schema::create('subscription_features', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique(); // Stable internal key, e.g. property.max_active_listings
            $table->string('name_en', 140);
            $table->string('name_am', 140)->nullable();
            $table->string('name_om', 140)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_am')->nullable();
            $table->text('description_om')->nullable();
            $table->string('category', 40)->default('listings'); // listings, media, marketing, analytics, tools
            $table->string('value_type', 24)->default('boolean'); // boolean, integer, unlimited, text, select
            $table->string('default_value', 255)->nullable();
            $table->json('options_json')->nullable(); // Allowed select values or validation rules
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_public')->default(true)->index(); // Visible on public comparison page
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // 2. Subscription Plans (Module 33.2)
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 40)->unique(); // free, agent_pro, agency, developer, host, enterprise
            $table->string('name_en', 140);
            $table->string('name_am', 140)->nullable();
            $table->string('name_om', 140)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_am')->nullable();
            $table->text('description_om')->nullable();
            $table->string('subscriber_type', 32)->default('all'); // all, seller, agent, company, developer, host, property_manager
            $table->decimal('price_etb', 14, 2)->default(0.00)->index();
            $table->string('currency', 8)->default('ETB');
            $table->unsignedInteger('duration_days')->default(30);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_public')->default(true)->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata_json')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // 3. Subscription Plan Feature Values (Module 33.1.1)
        Schema::create('subscription_plan_feature_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('subscription_features')->cascadeOnDelete();
            $table->string('value', 255); // 'true', 'false', '25', 'unlimited', etc.
            $table->timestampsTz();

            $table->unique(['plan_id', 'feature_id'], 'idx_plan_feature_unique');
        });

        // 4. User Subscriptions (Module 33.4)
        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 24)->default('pending_payment')->index(); // pending_payment, active, past_due, expired, cancelled
            $table->decimal('price_etb', 14, 2)->default(0.00);
            $table->string('currency', 8)->default('ETB');
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('cancelled_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->json('features_snapshot_json'); // Snapshot of plan entitlements at time of purchase to protect customer terms
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('metadata_json')->nullable(); // Stores purchased add-on packages (promotions, ads)
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'status', 'expires_at'], 'idx_user_subscriptions_lookup');
        });

        // 5. Promotion Tier Packages (Module 33.5)
        Schema::create('promotion_tier_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('tier', 24)->unique(); // standard, featured, premium, homepage, urgent
            $table->string('name_en', 120);
            $table->string('name_am', 120)->nullable();
            $table->string('name_om', 120)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_am')->nullable();
            $table->text('description_om')->nullable();
            $table->decimal('price_etb', 12, 2)->default(0.00);
            $table->unsignedInteger('duration_days')->default(14);
            $table->unsignedInteger('ranking_weight')->default(10); // Database-configured weight used in search ranking
            $table->string('badge_label', 40)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_public')->default(true)->index();
            $table->json('eligible_plan_codes')->nullable(); // Plans eligible to purchase; standard plan excluded
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // 6. Listing Promotions (Module 33.5)
        Schema::create('listing_promotions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('promotion_tier_packages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tier', 24)->index();
            $table->unsignedInteger('ranking_weight')->default(10);
            $table->decimal('price_etb', 12, 2)->default(0.00);
            $table->timestampTz('starts_at');
            $table->timestampTz('expires_at')->index();
            $table->string('status', 24)->default('active')->index(); // active, expired, cancelled
            $table->foreignId('subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['listing_id', 'status', 'expires_at'], 'idx_listing_promotions_active');
        });

        // 7. Multi-Placement Advertising Placements (Module 33.6)
        Schema::create('ad_placements', function (Blueprint $table): void {
            $table->id();
            $table->string('placement', 40)->unique(); // homepage, search, city_page, category_page, property_page, mobile, newsletter
            $table->string('name_en', 120);
            $table->string('name_am', 120)->nullable();
            $table->string('name_om', 120)->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('price_etb', 12, 2)->default(0.00);
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedInteger('impression_limit')->nullable();
            $table->unsignedInteger('click_limit')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_public')->default(true)->index();
            $table->json('eligible_plan_codes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // 8. Advertising Campaigns (Module 33.6)
        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('placement_id')->constrained('ad_placements')->restrictOnDelete();
            $table->string('placement', 40)->index();
            $table->string('title', 180);
            $table->string('target_url', 512);
            $table->string('banner_image_path', 512)->nullable();
            $table->decimal('price_etb', 12, 2)->default(0.00);
            $table->decimal('budget_etb', 12, 2)->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->string('status', 24)->default('draft')->index(); // draft, pending_approval, active, paused, completed, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('rejection_reason', 300)->nullable();
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->foreignId('subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('metadata_json')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['placement', 'status', 'starts_at', 'expires_at'], 'idx_ad_campaigns_display');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('ad_placements');
        Schema::dropIfExists('listing_promotions');
        Schema::dropIfExists('promotion_tier_packages');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('subscription_plan_feature_values');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('subscription_features');
    }
};

