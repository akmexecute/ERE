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
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 64)->index();
            $table->string('key', 120)->unique();
            $table->json('value_json');
            $table->string('type', 32)->default('string'); // string, int, bool, json
            $table->boolean('is_public')->default(false)->index();
            $table->string('description')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('action', 80)->index(); // e.g. property.price_changed, verification.approved
            $table->string('auditable_type', 160);
            $table->string('auditable_id', 64);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason', 500)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('device_fingerprint', 128)->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();

            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_logs_auditable');
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user_timeline');
        });

        // Enforce immutability of audit_logs at the PostgreSQL engine level (SRS Section 142 & 189)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_audit_log_mutation()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'Audit logs are immutable and cannot be updated or deleted.';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_audit_logs_immutable
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_logs_immutable ON audit_logs;');
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_audit_log_mutation();');
        }

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
    }
};
