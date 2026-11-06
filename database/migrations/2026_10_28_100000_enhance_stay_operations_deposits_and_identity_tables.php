<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'national_id_number')) {
                $table->string('national_id_number', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'national_id_type')) {
                $table->string('national_id_type', 32)->default('fayda')->nullable();
            }
            if (! Schema::hasColumn('users', 'id_front_document_id')) {
                $table->foreignId('id_front_document_id')->nullable()->constrained('documents')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'id_back_document_id')) {
                $table->foreignId('id_back_document_id')->nullable()->constrained('documents')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'identity_verified_at')) {
                $table->timestampTz('identity_verified_at')->nullable();
            }
        });

        Schema::table('hosts', function (Blueprint $table): void {
            if (! Schema::hasColumn('hosts', 'kpi_card_preferences')) {
                $table->json('kpi_card_preferences')->nullable();
            }
        });

        Schema::table('security_deposits', function (Blueprint $table): void {
            if (! Schema::hasColumn('security_deposits', 'payment_method')) {
                $table->string('payment_method', 32)->default('system_managed');
            }
            if (! Schema::hasColumn('security_deposits', 'payment_status')) {
                $table->string('payment_status', 32)->default('held');
            }
            if (! Schema::hasColumn('security_deposits', 'payment_reference')) {
                $table->string('payment_reference', 120)->nullable();
            }
            if (! Schema::hasColumn('security_deposits', 'verified_by_user_id')) {
                $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('security_deposits', 'verified_at')) {
                $table->timestampTz('verified_at')->nullable();
            }
        });

        Schema::table('check_outs', function (Blueprint $table): void {
            if (! Schema::hasColumn('check_outs', 'is_missed_checkout_fallback')) {
                $table->boolean('is_missed_checkout_fallback')->default(false);
            }
        });

        Schema::table('damage_claims', function (Blueprint $table): void {
            if (! Schema::hasColumn('damage_claims', 'claim_deadline_at')) {
                $table->timestampTz('claim_deadline_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('damage_claims', function (Blueprint $table): void {
            if (Schema::hasColumn('damage_claims', 'claim_deadline_at')) {
                $table->dropColumn('claim_deadline_at');
            }
        });

        Schema::table('check_outs', function (Blueprint $table): void {
            if (Schema::hasColumn('check_outs', 'is_missed_checkout_fallback')) {
                $table->dropColumn('is_missed_checkout_fallback');
            }
        });

        Schema::table('security_deposits', function (Blueprint $table): void {
            $cols = ['payment_method', 'payment_status', 'payment_reference', 'verified_by_user_id', 'verified_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('security_deposits', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('hosts', function (Blueprint $table): void {
            if (Schema::hasColumn('hosts', 'kpi_card_preferences')) {
                $table->dropColumn('kpi_card_preferences');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $cols = ['national_id_number', 'national_id_type', 'id_front_document_id', 'id_back_document_id', 'identity_verified_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

