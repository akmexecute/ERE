<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commission_calculations') && ! Schema::hasColumn('commission_calculations', 'transaction_reference')) {
            Schema::table('commission_calculations', function (Blueprint $table): void {
                $table->string('transaction_reference', 80)->nullable()->index()->after('transaction_type');
                $table->unique(['transaction_type', 'transaction_reference'], 'uq_commission_tx_ref');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commission_calculations') && Schema::hasColumn('commission_calculations', 'transaction_reference')) {
            Schema::table('commission_calculations', function (Blueprint $table): void {
                $table->dropUnique('uq_commission_tx_ref');
                $table->dropColumn('transaction_reference');
            });
        }
    }
};

