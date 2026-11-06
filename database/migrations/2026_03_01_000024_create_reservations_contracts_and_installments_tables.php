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
        // SRS Section 94 & 156: Concurrency-Safe Unit Reservation Engine
        Schema::create('unit_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reservation_code', 36)->unique(); // e.g. RES-2026-000412
            $table->string('idempotency_key', 80)->unique();

            $table->foreignId('project_unit_id')->constrained('project_units')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users');
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('payment_plan_id')->nullable()->constrained('developer_payment_plans')->nullOnDelete();

            $table->decimal('agreed_price_etb', 18, 2);
            $table->decimal('deposit_amount_etb', 18, 2);
            $table->decimal('deposit_percentage', 5, 2)->default(10.00);

            // requested, pending_payment, reserved, expired, cancelled, converted (SRS Section 94)
            $table->string('status', 24)->default('requested')->index();
            $table->timestampTz('hold_expires_at')->index();

            $table->jsonb('payment_plan_snapshot_json')->nullable();

            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('reserved_at')->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->string('cancellation_reason', 400)->nullable();
            $table->timestampsTz();

            $table->index(['project_unit_id', 'status'], 'idx_unit_reservations_status');
        });

        // PostgreSQL Partial Unique Index: Exactly one active hold per unit (SRS Section 156 & 190)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX uq_active_unit_reservation 
                ON unit_reservations (project_unit_id) 
                WHERE (status IN ('requested', 'pending_payment', 'reserved'));
            SQL);
        }

        // SRS Section 95 & 177: Developer Sales Contracts & Proclamation No. 1357/2024 Compliance
        Schema::create('developer_sales_contracts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('contract_code', 36)->unique(); // e.g. CNT-2026-000108

            $table->foreignId('reservation_id')->unique()->constrained('unit_reservations');
            $table->foreignId('project_unit_id')->constrained('project_units');
            $table->foreignId('project_id')->constrained('development_projects');
            $table->foreignId('buyer_user_id')->constrained('users');
            $table->foreignId('developer_company_id')->constrained('companies');
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('contract_price_etb', 18, 2);
            $table->decimal('deposit_paid_etb', 18, 2);
            $table->decimal('balance_payable_etb', 18, 2);

            $table->jsonb('payment_plan_snapshot_json');

            // draft, awaiting_signature, signed, active, completed, cancelled, terminated
            $table->string('status', 24)->default('draft')->index();

            // Proclamation No. 1357/2024 Legal Disclosures Verification
            $table->string('disclosure_version', 32)->default('v1.0-proc1357');
            $table->boolean('disclosure_accepted_by_buyer')->default(false);
            $table->timestampTz('disclosure_accepted_at')->nullable();
            $table->string('disclosure_client_ip', 45)->nullable();

            $table->timestampTz('signed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Generated Installment Schedule per Contract
        Schema::create('contract_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('developer_sales_contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence_number');
            $table->string('milestone_label_en', 140);
            $table->string('milestone_label_am', 140)->nullable();
            $table->decimal('percentage', 5, 2);
            $table->decimal('amount_etb', 18, 2);
            $table->date('due_date')->nullable()->index();

            $table->decimal('paid_amount_etb', 18, 2)->default(0.00);
            $table->decimal('outstanding_balance_etb', 18, 2);

            // pending, due, partially_paid, paid, overdue
            $table->string('status', 24)->default('pending')->index();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->unique(['contract_id', 'sequence_number'], 'uq_contract_installment_sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_installments');
        Schema::dropIfExists('developer_sales_contracts');
        Schema::dropIfExists('unit_reservations');
    }
};
