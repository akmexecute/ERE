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
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist;');
        }

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('booking_code', 36)->unique(); // e.g. BK-2026-0001045
            // SRS Section 191: Transaction Idempotency Key
            $table->string('idempotency_key', 80)->unique();

            $table->foreignId('listing_id')->constrained('listings');
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('stay_listing_profile_id')->constrained('stay_listing_profiles');
            $table->foreignId('accommodation_unit_id')->constrained('accommodation_units');
            $table->foreignId('host_id')->constrained('hosts');
            $table->foreignId('guest_user_id')->constrained('users');

            // instant_book (SRS Section 47) vs request_to_book (SRS Section 48)
            $table->string('booking_mode', 24)->default('instant_book');

            // Dates & Guest Counts
            $table->date('check_in_date')->index();
            $table->date('check_out_date')->index();
            $table->unsignedSmallInteger('nights_count');
            $table->unsignedSmallInteger('adults_count')->default(1);
            $table->unsignedSmallInteger('children_count')->default(0);
            $table->unsignedSmallInteger('infants_count')->default(0);
            $table->unsignedSmallInteger('pets_count')->default(0);

            // Itemized Financial Summary in ETB (SRS Section 50)
            $table->decimal('accommodation_raw_subtotal_etb', 18, 2);
            $table->decimal('discount_amount_etb', 18, 2)->default(0.00);
            $table->decimal('accommodation_net_subtotal_etb', 18, 2);
            $table->decimal('cleaning_fee_etb', 18, 2)->default(0.00);
            $table->decimal('platform_fee_etb', 18, 2)->default(0.00);
            $table->decimal('tax_amount_etb', 18, 2)->default(0.00);
            $table->decimal('security_deposit_etb', 18, 2)->default(0.00);
            $table->decimal('total_amount_etb', 18, 2);
            $table->string('currency', 8)->default('ETB');

            // Cancellation Policy Snapshot at Time of Booking (SRS Section 53)
            $table->string('cancellation_policy', 24)->default('flexible');

            // SRS Sections 46, 49, 155, 193: Booking Lifecycle Status
            // pending, awaiting_payment, confirmed, checked_in, active, checked_out, completed, cancelled, refunded, disputed, no_show, expired
            $table->string('status', 28)->default('awaiting_payment')->index();

            // SRS Section 49 & 193: Temporary Booking Hold Expiration
            $table->timestampTz('hold_expires_at')->nullable()->index();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('checked_out_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason', 400)->nullable();

            $table->timestampsTz();

            $table->index(['host_id', 'status', 'check_in_date'], 'idx_bookings_host_schedule');
            $table->index(['guest_user_id', 'status', 'check_in_date'], 'idx_bookings_guest_trips');
        });

        // SRS Section 190: Database-level exclusion constraint preventing overlapping active bookings/holds on the same unit
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE bookings
                ADD COLUMN stay_daterange daterange
                GENERATED ALWAYS AS (daterange(check_in_date, check_out_date, '[)')) STORED;

                ALTER TABLE bookings
                ADD CONSTRAINT ex_bookings_no_overlapping_dates
                EXCLUDE USING GIST (
                    accommodation_unit_id WITH =,
                    stay_daterange WITH &&
                )
                WHERE (status IN ('awaiting_payment', 'confirmed', 'checked_in', 'active'));
            SQL);
        }

        // SRS Section 50: Every component of the Short-Stay price calculation must be separately recorded
        Schema::create('booking_price_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            // nightly_rate, long_stay_discount, cleaning_fee, platform_fee, tax, security_deposit
            $table->string('item_type', 32)->index();
            $table->string('label_en', 160);
            $table->string('label_am', 160)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_amount_etb', 18, 2);
            $table->decimal('total_amount_etb', 18, 2); // negative for discounts
            $table->json('metadata_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_price_items');
        Schema::dropIfExists('bookings');
    }
};
