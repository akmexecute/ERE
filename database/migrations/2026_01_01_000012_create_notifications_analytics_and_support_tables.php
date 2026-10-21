<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 64)->unique(); // inquiry.received, listing.approved, price.dropped...
            $table->json('channels_json'); // ["in_app", "sms", "email", "telegram"]
            $table->string('subject_en', 180);
            $table->string('subject_am', 180)->nullable();
            $table->string('subject_om', 180)->nullable();
            $table->text('body_en');
            $table->text('body_am')->nullable();
            $table->text('body_om')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // SRS Section 172: User Notification Preferences
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->boolean('channel_in_app')->default(true);
            $table->boolean('channel_email')->default(true);
            $table->boolean('channel_sms')->default(true);
            $table->boolean('channel_push')->default(true);
            $table->boolean('channel_telegram')->default(false);
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestampsTz();
        });

        // SRS Sections 37, 126, 127, 214, 215: National & City Market Intelligence Snapshots
        Schema::create('market_analytics_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->foreignId('region_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('subcity_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('property_categories')->nullOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->nullOnDelete();
            $table->string('transaction_mode', 24)->default('BUY')->index();

            $table->unsignedInteger('active_listings_count')->default(0);
            $table->unsignedInteger('new_listings_count')->default(0);
            $table->unsignedInteger('inquiries_count')->default(0);
            $table->decimal('avg_asking_price_etb', 18, 2)->nullable();
            $table->decimal('median_asking_price_etb', 18, 2)->nullable();
            $table->decimal('avg_price_per_sqm_etb', 18, 2)->nullable();

            // SRS Sections 37 & 127: Must clearly identify whether metrics are platform asking-price indicators or verified transaction data
            $table->string('data_basis', 48)->default('platform_asking_price_indicator');
            $table->timestampsTz();

            $table->index(['snapshot_date', 'city_id', 'transaction_mode'], 'idx_market_analytics_city_date');
        });

        // SRS Section 116: Customer Support Ticket System
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ticket_code', 32)->unique(); // TKT-2026-000105
            $table->foreignId('user_id')->constrained('users');
            // account, property, booking, payment, verification, fraud, technical, subscription
            $table->string('category', 32)->index();
            $table->string('priority', 16)->default('normal')->index(); // low, normal, high, urgent
            $table->string('subject', 200);
            $table->text('description');
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('open')->index(); // open, in_progress, waiting_user, resolved, closed
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('market_analytics_snapshots');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
    }
};
