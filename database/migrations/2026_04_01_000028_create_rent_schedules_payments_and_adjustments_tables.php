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
        // 1. Persistent Rent Schedules (SRS Section 153 & Module 27)
        Schema::create('rent_schedules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();

            $table->unsignedSmallInteger('period_sequence'); // 1, 2, 3...
            $table->string('period_label', 120); // e.g. "January 2026", "Q1 2026 (Jan - Mar 2026)"
            $table->date('period_start_date')->index();
            $table->date('period_end_date')->index();
            $table->date('due_date')->index();

            // Financial amounts in ETB
            $table->decimal('base_rent_etb', 18, 2);
            $table->decimal('adjustments_amount_etb', 18, 2)->default(0.00);
            $table->decimal('net_amount_due_etb', 18, 2);
            $table->decimal('confirmed_paid_etb', 18, 2)->default(0.00);
            $table->decimal('outstanding_balance_etb', 18, 2);

            // Schedule Status: upcoming, pending, partially_paid, paid, overdue
            $table->string('status', 24)->default('upcoming')->index();
            $table->timestampTz('paid_at')->nullable();

            $table->timestampsTz();

            $table->unique(['lease_id', 'period_sequence'], 'uq_lease_period_sequence');
            $table->index(['lease_id', 'status', 'due_date'], 'idx_rent_schedule_lease_status');
        });

        // 2. Rental Payments (SRS Section 154 & Module 27)
        Schema::create('rental_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('payment_reference', 40)->unique(); // e.g. PAY-2026-0000001
            $table->string('idempotency_key', 80)->unique();

            // Using RESTRICT on delete to protect financial audit history from parent cascading deletion
            $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
            $table->foreignId('rent_schedule_id')->constrained('rent_schedules')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->restrictOnDelete();

            $table->decimal('amount_etb', 18, 2);
            // cbe_birr, telebirr, bank_transfer, cash, chapa
            $table->string('payment_method', 32);
            $table->string('provider_reference', 120)->nullable();

            // Safe default status: pending (never default to successful/confirmed)
            $table->string('status', 24)->default('pending')->index();
            $table->timestampTz('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('proof_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('notes', 300)->nullable();

            $table->timestampsTz();

            $table->index(['lease_id', 'status'], 'idx_rental_payments_lease_status');
        });

        // 3. Rental Receipts (SRS Section 155 & Module 27)
        Schema::create('rental_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('receipt_number', 40)->unique(); // e.g. RCT-2026-0000001
            $table->foreignId('rental_payment_id')->unique()->constrained('rental_payments')->restrictOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();
            $table->foreignId('rent_schedule_id')->constrained('rent_schedules')->restrictOnDelete();

            $table->foreignId('payer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();

            $table->decimal('amount_etb', 18, 2);
            $table->string('currency', 8)->default('ETB');
            $table->string('payment_method', 32);
            $table->timestampTz('issued_at')->useCurrent();
            $table->jsonb('receipt_data_json');

            $table->timestampsTz();
        });

        // 4. Append-Only Financial Adjustments (SRS Section 189 & Module 27)
        // Principle: Never overwrite original payment amount. Record separate signed adjustment entry.
        Schema::create('rental_payment_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('rental_payment_id')->constrained('rental_payments')->restrictOnDelete();
            $table->foreignId('rent_schedule_id')->constrained('rent_schedules')->restrictOnDelete();
            $table->foreignId('lease_id')->constrained('leases')->restrictOnDelete();

            $table->decimal('adjustment_amount_etb', 18, 2); // Signed (+ or -)
            $table->string('reason', 300); // Mandatory justification
            $table->foreignId('authorized_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        // 5. PostgreSQL Database-Enforced Triggers for Append-Only Financial History
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                -- Trigger 1: Strictly prevent UPDATE or DELETE on rental_payment_adjustments
                CREATE OR REPLACE FUNCTION trg_prevent_financial_adjustment_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'Financial adjustments are strictly append-only (SRS Section 189). Modification or deletion is forbidden.';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_no_update_or_delete_adjustments
                BEFORE UPDATE OR DELETE ON rental_payment_adjustments
                FOR EACH ROW EXECUTE FUNCTION trg_prevent_financial_adjustment_mutation();

                -- Trigger 2: Strictly prevent modifying the original amount_etb on rental_payments
                CREATE OR REPLACE FUNCTION trg_prevent_rental_payment_amount_modification()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.amount_etb != NEW.amount_etb THEN
                        RAISE EXCEPTION 'Original payment amount is immutable (SRS Section 189). Use append-only financial adjustments instead.';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_no_update_payment_amount
                BEFORE UPDATE ON rental_payments
                FOR EACH ROW EXECUTE FUNCTION trg_prevent_rental_payment_amount_modification();

                -- Trigger 3: Prevent DELETE of confirmed rental_payments
                CREATE OR REPLACE FUNCTION trg_prevent_confirmed_rental_payment_deletion()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF OLD.status = 'confirmed' THEN
                        RAISE EXCEPTION 'Confirmed rental payments cannot be deleted (SRS Section 189). Record an adjustment or refund instead.';
                    END IF;
                    RETURN OLD;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_no_delete_confirmed_payments
                BEFORE DELETE ON rental_payments
                FOR EACH ROW EXECUTE FUNCTION trg_prevent_confirmed_rental_payment_deletion();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_no_delete_confirmed_payments ON rental_payments;');
            DB::statement('DROP FUNCTION IF EXISTS trg_prevent_confirmed_rental_payment_deletion();');
            DB::statement('DROP TRIGGER IF EXISTS trg_no_update_payment_amount ON rental_payments;');
            DB::statement('DROP FUNCTION IF EXISTS trg_prevent_rental_payment_amount_modification();');
            DB::statement('DROP TRIGGER IF EXISTS trg_no_update_or_delete_adjustments ON rental_payment_adjustments;');
            DB::statement('DROP FUNCTION IF EXISTS trg_prevent_financial_adjustment_mutation();');
        }

        Schema::dropIfExists('rental_payment_adjustments');
        Schema::dropIfExists('rental_receipts');
        Schema::dropIfExists('rental_payments');
        Schema::dropIfExists('rent_schedules');
    }
};
