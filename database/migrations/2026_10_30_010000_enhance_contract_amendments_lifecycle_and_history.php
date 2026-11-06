<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_amendments', function (Blueprint $table): void {
            if (! Schema::hasColumn('contract_amendments', 'status')) {
                $table->string('status', 24)->default('pending')->after('amendment_code')->index();
            }
            if (! Schema::hasColumn('contract_amendments', 'decided_by_user_id')) {
                $table->foreignId('decided_by_user_id')->nullable()->after('authorized_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('contract_amendments', 'rejected_by_user_id')) {
                $table->foreignId('rejected_by_user_id')->nullable()->after('decided_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('contract_amendments', 'rejected_at')) {
                $table->timestampTz('rejected_at')->nullable()->after('accepted_at');
            }
            if (! Schema::hasColumn('contract_amendments', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contract_amendments', function (Blueprint $table): void {
            if (Schema::hasColumn('contract_amendments', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('contract_amendments', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('contract_amendments', 'rejected_by_user_id')) {
                $table->dropConstrainedForeignId('rejected_by_user_id');
            }
            if (Schema::hasColumn('contract_amendments', 'decided_by_user_id')) {
                $table->dropConstrainedForeignId('decided_by_user_id');
            }
            if (Schema::hasColumn('contract_amendments', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

