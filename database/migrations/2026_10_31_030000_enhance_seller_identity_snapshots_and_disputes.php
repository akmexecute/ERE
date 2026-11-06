<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add immutable seller identity snapshot to developer sales contracts
        Schema::table('developer_sales_contracts', function (Blueprint $table): void {
            if (! Schema::hasColumn('developer_sales_contracts', 'seller_identity_snapshot_json')) {
                $table->json('seller_identity_snapshot_json')->nullable()->after('payment_plan_snapshot_json');
            }
        });

        // 2. Enhance support_tickets with transaction and dispute linkage
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'contract_id')) {
                $table->foreignId('contract_id')->nullable()->constrained('developer_sales_contracts')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'against_user_id')) {
                $table->foreignId('against_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'property_id')) {
                $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            }
            if (! Schema::hasColumn('support_tickets', 'transaction_reference')) {
                $table->string('transaction_reference', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('support_tickets', 'seller_snapshot_json')) {
                $table->json('seller_snapshot_json')->nullable();
            }
            if (! Schema::hasColumn('support_tickets', 'evidence_json')) {
                $table->json('evidence_json')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('support_tickets', 'evidence_json')) {
                $table->dropColumn(['evidence_json', 'seller_snapshot_json', 'transaction_reference']);
            }
            if (Schema::hasColumn('support_tickets', 'property_id')) {
                $table->dropConstrainedForeignId('property_id');
            }
            if (Schema::hasColumn('support_tickets', 'against_user_id')) {
                $table->dropConstrainedForeignId('against_user_id');
            }
            if (Schema::hasColumn('support_tickets', 'contract_id')) {
                $table->dropConstrainedForeignId('contract_id');
            }
        });

        Schema::table('developer_sales_contracts', function (Blueprint $table): void {
            if (Schema::hasColumn('developer_sales_contracts', 'seller_identity_snapshot_json')) {
                $table->dropColumn('seller_identity_snapshot_json');
            }
        });
    }
};

