<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 45 & 56: Daily Availability & Custom Host Rate/Min-Stay Overrides
        Schema::create('availability_calendars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accommodation_unit_id')->constrained('accommodation_units')->cascadeOnDelete();
            $table->date('calendar_date')->index();
            // available, booked, held_pending_payment, blocked_by_host, external_ical_blocked
            $table->string('status', 28)->default('available')->index();
            $table->decimal('custom_nightly_rate_etb', 14, 2)->nullable();
            $table->unsignedSmallInteger('min_stay_override_nights')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->string('block_reason', 160)->nullable();
            $table->timestampsTz();

            $table->unique(['accommodation_unit_id', 'calendar_date'], 'uq_unit_calendar_date');
        });

        // SRS Sections 51 & 52: Dynamic Pricing (Seasonal, Ethiopian Holidays, Events) & Holiday Min Stay
        Schema::create('seasonal_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_listing_profile_id')->constrained('stay_listing_profiles')->cascadeOnDelete();
            $table->string('name', 120); // e.g. "Meskel & Timket Holiday Peak", "Rainy Season Discount"
            // weekday, weekend, seasonal, holiday, event, early_booking, last_minute (SRS Section 51)
            $table->string('rule_type', 28)->default('holiday')->index();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('nightly_rate_etb', 14, 2)->nullable();
            $table->decimal('rate_multiplier', 5, 2)->default(1.00); // e.g. 1.25 = +25%
            // SRS Section 52: e.g. Normal: 2 nights, Holiday: 5 nights
            $table->unsignedSmallInteger('min_stay_nights')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // SRS Section 57: External iCal Synchronization
        Schema::create('external_ical_feeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accommodation_unit_id')->constrained('accommodation_units')->cascadeOnDelete();
            $table->string('channel_name', 64); // e.g. Airbnb, Booking.com, Google Calendar
            $table->string('import_ical_url', 512)->nullable();
            $table->string('export_token', 64)->unique();
            $table->timestampTz('last_synced_at')->nullable();
            $table->string('last_sync_status', 32)->default('never');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_ical_feeds');
        Schema::dropIfExists('seasonal_prices');
        Schema::dropIfExists('availability_calendars');
    }
};
