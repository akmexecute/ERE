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
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'allocation_status')) {
                $table->string('allocation_status', 32)->default('unallocated')->index();
            }
            if (! Schema::hasColumn('payments', 'allocated_amount_etb')) {
                $table->decimal('allocated_amount_etb', 18, 2)->default(0.00);
            }
            if (! Schema::hasColumn('payments', 'unapplied_amount_etb')) {
                $table->decimal('unapplied_amount_etb', 18, 2)->default(0.00);
            }
            if (! Schema::hasColumn('payments', 'reconciliation_notes')) {
                $table->text('reconciliation_notes')->nullable();
            }
            if (! Schema::hasColumn('payments', 'provider_event_timestamp')) {
                $table->timestampTz('provider_event_timestamp')->nullable();
            }
            if (! Schema::hasColumn('payments', 'reservation_expires_at')) {
                $table->timestampTz('reservation_expires_at')->nullable()->index();
            }
        });

        Schema::table('webhook_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('webhook_events', 'provider_event_timestamp')) {
                $table->timestampTz('provider_event_timestamp')->nullable();
            }
        });

        // Ensure Chart of Accounts has accounts 2040 and 2050 seeded
        if (Schema::hasTable('ledger_accounts')) {
            DB::table('ledger_accounts')->updateOrInsert(
                ['code' => '2040'],
                [
                    'name' => 'Unapplied Customer Funds & Pending Reconciliation',
                    'type' => 'liability',
                    'description' => 'Unapplied customer funds received after payment reservation expired or exceeding contractual limit, awaiting administrative reconciliation or refund.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('ledger_accounts')->updateOrInsert(
                ['code' => '2050'],
                [
                    'name' => 'Customer Refunds Payable & Disbursement Clearing',
                    'type' => 'liability',
                    'description' => 'Approved customer refunds and pending payouts awaiting physical banking or payment gateway disbursement.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn([
                'allocation_status',
                'allocated_amount_etb',
                'unapplied_amount_etb',
                'reconciliation_notes',
                'provider_event_timestamp',
                'reservation_expires_at',
            ]);
        });

        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropColumn(['provider_event_timestamp']);
        });
    }
};

