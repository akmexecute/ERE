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
        // 1. Chart of Accounts (Asset, Liability, Equity, Revenue, Expense)
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique(); // e.g. 1010, 2010, 2020, 2030, 4010, 4020, 5010
            $table->string('name', 120);
            $table->string('type', 24)->index(); // asset, liability, equity, revenue, expense
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        // 2. Journal Posting Groups (Balanced transactions where Sum(Debits) == Sum(Credits))
        Schema::create('journal_posting_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('posting_reference', 40)->unique(); // e.g. JRN-2026-0000001
            $table->string('source_type', 120)->index(); // e.g. App\Domains\Payment\Models\Payment
            $table->unsignedBigInteger('source_id')->index();
            $table->string('event_type', 40)->index(); // payment_captured, host_payout_settled, adjustment, reversal
            $table->text('description');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('total_debit_amount_etb', 18, 2);
            $table->decimal('total_credit_amount_etb', 18, 2);
            $table->boolean('is_balanced')->default(true);

            $table->foreignId('reversed_posting_group_id')->nullable()->constrained('journal_posting_groups')->nullOnDelete();
            $table->timestampTz('posted_at')->index();
            $table->timestampsTz();

            $table->index(['source_type', 'source_id', 'event_type'], 'idx_journal_source_event');
        });

        // 3. Append-Only Ledger Entries
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('posting_group_id')->constrained('journal_posting_groups')->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();

            $table->string('entry_type', 10)->index(); // debit, credit
            $table->decimal('amount_etb', 18, 2);
            $table->string('currency', 8)->default('ETB');
            $table->string('description', 255)->nullable();
            $table->string('source_reference', 100)->nullable()->index();
            $table->timestampTz('created_at')->index();

            $table->index(['ledger_account_id', 'entry_type', 'created_at'], 'idx_ledger_account_balance');
        });

        // PostgreSQL Append-Only Immutability Trigger
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_ledger_entry_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'Ledger entries are strictly append-only and cannot be updated or deleted. Issue a reversing journal posting instead.';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_prevent_ledger_entry_update_delete
                BEFORE UPDATE OR DELETE ON ledger_entries
                FOR EACH ROW EXECUTE FUNCTION prevent_ledger_entry_mutation();
            SQL);
        }

        // 4. Host Payout Destinations (Bank, Telebirr, CBE Birr)
        Schema::create('host_payout_destinations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('host_id')->constrained('hosts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('channel', 32)->index(); // bank_account, telebirr, cbe_birr
            $table->string('institution_name', 120); // e.g. Commercial Bank of Ethiopia, Telebirr
            $table->string('account_holder_name', 160);
            $table->text('account_number_encrypted');
            $table->string('account_number_masked', 32); // e.g. "**** **** 1234"
            $table->string('branch_name', 120)->nullable();
            $table->string('swift_code', 32)->nullable();

            $table->boolean('is_verified')->default(false)->index();
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(false);

            // pending, active, rejected, suspended
            $table->string('status', 24)->default('pending')->index();
            $table->timestampsTz();
        });

        // 5. Host & Landlord Payouts (Waterfall & Scheduling)
        Schema::create('host_payouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('payout_reference', 40)->unique(); // e.g. PO-2026-0000001
            $table->foreignId('host_id')->constrained('hosts')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained('leases')->restrictOnDelete();
            $table->foreignId('payout_destination_id')->nullable()->constrained('host_payout_destinations')->nullOnDelete();

            // immediately, after_checkin, after_checkout, weekly, monthly
            $table->string('schedule_type', 24)->index();
            $table->timestampTz('scheduled_for')->index();

            // Decimal-safe Waterfall Breakdown: Gross - Deductions + Adjustments = Net Payable
            $table->decimal('gross_revenue_etb', 18, 2);
            $table->decimal('platform_deductions_etb', 18, 2)->default(0.00);
            $table->decimal('adjustments_amount_etb', 18, 2)->default(0.00);
            $table->decimal('net_payable_etb', 18, 2);
            $table->string('currency', 8)->default('ETB');

            // scheduled, eligible, awaiting_approval, processing, paid, failed, on_hold, cancelled
            $table->string('status', 28)->default('scheduled')->index();
            $table->string('hold_reason', 400)->nullable();
            $table->foreignId('held_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('paid_at')->nullable();

            $table->string('provider_channel', 32)->nullable();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->string('failure_reason', 400)->nullable();
            $table->timestampsTz();

            $table->index(['host_id', 'status', 'scheduled_for'], 'idx_host_payouts_schedule');
        });

        // 6. Payout Adjustments (SRS Section 189 & Deductions/Additions)
        Schema::create('payout_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('host_payout_id')->constrained('host_payouts')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->decimal('amount_etb', 18, 2);
            $table->string('direction', 16); // deduction, addition
            $table->text('reason');
            $table->foreignId('authorized_by_user_id')->constrained('users');

            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_prevent_ledger_entry_update_delete ON ledger_entries;');
            DB::statement('DROP FUNCTION IF EXISTS prevent_ledger_entry_mutation();');
        }

        Schema::dropIfExists('payout_adjustments');
        Schema::dropIfExists('host_payouts');
        Schema::dropIfExists('host_payout_destinations');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('journal_posting_groups');
        Schema::dropIfExists('ledger_accounts');
    }
};

