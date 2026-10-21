<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 40 & 194: Host Model & Payout Schedule Preference
        Schema::create('hosts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            // individual_owner, company, property_manager, hotel, guesthouse, developer, professional_host
            $table->string('host_type', 32)->default('individual_owner')->index();
            $table->string('display_name', 140);
            $table->text('bio_en')->nullable();
            $table->text('bio_am')->nullable();
            $table->text('bio_om')->nullable();
            $table->unsignedTinyInteger('response_rate_pct')->default(100);
            $table->unsignedSmallInteger('avg_response_minutes')->default(15);
            $table->decimal('average_rating', 3, 2)->default(0.00)->index();
            $table->unsignedInteger('reviews_count')->default(0);
            // SRS Section 194: immediately, after_checkin, after_checkout, weekly, monthly
            $table->string('payout_schedule', 24)->default('after_checkin');
            $table->boolean('is_superhost')->default(false)->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestampsTz();
        });

        // SRS Sections 41, 42, 47, 52, 53: Short-Stay Listing Configuration
        Schema::create('stay_listing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained('listings')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('hosts')->cascadeOnDelete();

            // entire_place (single villa/apartment) vs hotel_rooms (SRS Section 104)
            $table->string('accommodation_model', 24)->default('entire_place')->index();

            // SRS Section 41: Nightly, Weekend, Weekly, Monthly Rates & Fees in ETB
            $table->decimal('base_nightly_rate_etb', 14, 2);
            $table->decimal('weekend_nightly_rate_etb', 14, 2)->nullable();
            $table->decimal('weekly_rate_etb', 14, 2)->nullable();
            $table->decimal('monthly_rate_etb', 14, 2)->nullable();
            $table->decimal('weekly_discount_pct', 5, 2)->default(0.00);
            $table->decimal('monthly_discount_pct', 5, 2)->default(0.00);
            $table->decimal('cleaning_fee_etb', 14, 2)->default(0.00);
            $table->decimal('security_deposit_etb', 14, 2)->default(0.00);

            // Configurable Platform Service Fee & Tax Rate (SRS Section 195 — never hard-coded)
            $table->decimal('guest_service_fee_pct', 5, 2)->default(6.00);
            $table->decimal('host_commission_pct', 5, 2)->default(3.00);
            $table->decimal('tourism_tax_pct', 5, 2)->default(0.00);

            // SRS Section 41 & 52: Minimum / Maximum Stay & Check-in/out Windows
            $table->unsignedSmallInteger('min_stay_nights')->default(1);
            $table->unsignedSmallInteger('max_stay_nights')->default(90);
            $table->string('check_in_after_time', 8)->default('14:00');
            $table->string('check_out_before_time', 8)->default('11:00');

            // SRS Section 42: Guest Capacity
            $table->unsignedSmallInteger('max_guests')->default(2)->index();
            $table->unsignedSmallInteger('max_adults')->default(2);
            $table->unsignedSmallInteger('max_children')->default(2);
            $table->unsignedSmallInteger('max_infants')->default(2);
            $table->boolean('allows_pets')->default(false);

            // SRS Section 47, 48 & 53: Booking Mode & Cancellation Policy
            $table->boolean('instant_book_enabled')->default(true)->index();
            // flexible, moderate, strict, custom (SRS Section 53)
            $table->string('cancellation_policy', 24)->default('flexible');
            $table->unsignedSmallInteger('hold_duration_minutes')->default(15); // SRS Section 193

            $table->timestampsTz();
        });

        // SRS Section 104: Hotel / Multi-Unit Accommodation Model (Hotel -> Room Types -> Rooms)
        Schema::create('accommodation_unit_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_listing_profile_id')->constrained('stay_listing_profiles')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('name_en', 140); // e.g. "Entire Villa", "Deluxe King Room", "Standard Studio"
            $table->string('name_am', 140)->nullable();
            $table->string('name_om', 140)->nullable();
            $table->unsignedSmallInteger('max_guests')->default(2);
            $table->decimal('base_nightly_rate_etb', 14, 2);
            $table->unsignedSmallInteger('total_rooms_count')->default(1);
            $table->timestampsTz();
        });

        Schema::create('accommodation_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_type_id')->constrained('accommodation_unit_types')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('unit_number_or_code', 48); // e.g. "VILLA-MAIN", "RM-101", "RM-102"
            $table->string('status', 24)->default('ready')->index(); // ready, occupied, cleaning, maintenance, blocked
            $table->timestampsTz();

            $table->unique(['property_id', 'unit_number_or_code'], 'uq_accommodation_unit_code');
        });

        // SRS Section 43: Bed Configuration (Bedroom 1: King, Bedroom 2: Queen, Living Room: Sofa Bed)
        Schema::create('bed_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_listing_profile_id')->constrained('stay_listing_profiles')->cascadeOnDelete();
            $table->string('room_label_en', 80); // e.g. "Bedroom 1", "Bedroom 2", "Living Room"
            $table->string('room_label_am', 80)->nullable();
            $table->string('bed_type', 32); // king, queen, double, single, sofa_bed, bunk_bed, baby_crib
            $table->unsignedTinyInteger('bed_count')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        // SRS Section 60: House Rules
        Schema::create('house_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_listing_profile_id')->unique()->constrained('stay_listing_profiles')->cascadeOnDelete();
            $table->boolean('smoking_allowed')->default(false);
            $table->boolean('pets_allowed')->default(false);
            $table->boolean('parties_allowed')->default(false);
            $table->boolean('visitors_allowed')->default(true);
            $table->boolean('children_allowed')->default(true);
            $table->boolean('events_allowed')->default(false);
            $table->string('quiet_hours_start', 8)->nullable()->default('22:00');
            $table->string('quiet_hours_end', 8)->nullable()->default('07:00');
            $table->text('additional_rules_en')->nullable();
            $table->text('additional_rules_am')->nullable();
            $table->timestampsTz();
        });

        // SRS Section 58 & 61: Digital House Manual (Unlocked only for Confirmed Bookings)
        Schema::create('house_manuals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_listing_profile_id')->unique()->constrained('stay_listing_profiles')->cascadeOnDelete();
            $table->string('wifi_ssid', 120)->nullable();
            $table->text('wifi_password_encrypted')->nullable();
            $table->string('check_in_method', 32)->default('host_greets'); // host_greets, smart_lock, lockbox, reception
            $table->text('access_instructions_en')->nullable();
            $table->text('access_instructions_am')->nullable();
            $table->text('appliance_and_ac_guide_en')->nullable();
            $table->text('parking_instructions_en')->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->timestampsTz();
        });

        // SRS Section 62: Host Local Guide (Restaurants, Hospitals, Cultural Sites, Transport)
        Schema::create('local_guides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_listing_profile_id')->constrained('stay_listing_profiles')->cascadeOnDelete();
            // restaurant, shop, hospital, transportation, tourist_attraction, cultural_site
            $table->string('category', 32)->index();
            $table->string('place_name', 160);
            $table->string('distance_note', 64)->nullable(); // e.g. "400m walk", "10 min drive"
            $table->text('host_tip_en')->nullable();
            $table->text('host_tip_am')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_guides');
        Schema::dropIfExists('house_manuals');
        Schema::dropIfExists('house_rules');
        Schema::dropIfExists('bed_configurations');
        Schema::dropIfExists('accommodation_units');
        Schema::dropIfExists('accommodation_unit_types');
        Schema::dropIfExists('stay_listing_profiles');
        Schema::dropIfExists('hosts');
    }
};
