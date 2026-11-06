<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add site plan metadata to development_projects if not present
        Schema::table('development_projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('development_projects', 'site_plan_url')) {
                $table->string('site_plan_url', 255)->nullable()->after('facilities_json');
            }
            if (! Schema::hasColumn('development_projects', 'site_plan_document_id')) {
                $table->foreignId('site_plan_document_id')->nullable()->after('site_plan_url')->constrained('documents')->nullOnDelete();
            }
            if (! Schema::hasColumn('development_projects', 'site_plan_description')) {
                $table->text('site_plan_description')->nullable()->after('site_plan_document_id');
            }
        });

        // 2. Developer-entered manual payment accounts (Multiple accounts per company / developer / project)
        Schema::create('developer_payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('developer_id')->constrained('developer_profiles')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('development_projects')->nullOnDelete();

            $table->string('account_label', 120); // e.g. "CBE Primary Escrow", "Awash Bank Diaspora Transfer"
            $table->string('institution_name', 120); // e.g. "Commercial Bank of Ethiopia", "Awash Bank"
            $table->string('account_holder_name', 160);
            $table->string('account_number', 64)->index();
            $table->string('branch_name', 120)->nullable();
            $table->string('swift_code', 32)->nullable();
            $table->string('currency', 8)->default('ETB');
            $table->text('transfer_instructions')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestampsTz();
        });

        // 3. Contract Payment Transactions (Provider Webhook & Manual Transfer Verification Workflow)
        Schema::create('contract_payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('transaction_reference', 64)->unique(); // Internal tracking code

            $table->foreignId('contract_id')->constrained('developer_sales_contracts')->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('contract_installments')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('unit_reservations')->nullOnDelete();
            $table->foreignId('payer_user_id')->constrained('users');

            // 'provider' (Chapa, Telebirr, CBE Birr) or 'manual_bank_transfer'
            $table->string('payment_method', 32)->default('manual_bank_transfer')->index();
            $table->string('provider_name', 32)->nullable();
            $table->foreignId('payment_account_id')->nullable()->constrained('developer_payment_accounts')->nullOnDelete();

            $table->decimal('amount_etb', 18, 2);
            $table->string('transfer_reference', 100)->nullable()->index(); // Bank transaction reference / slip number
            $table->string('receipt_url', 255)->nullable();
            $table->foreignId('receipt_document_id')->nullable()->constrained('documents')->nullOnDelete();

            // 'pending_verification', 'verified', 'rejected', 'failed'
            $table->string('status', 32)->default('pending_verification')->index();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->jsonb('metadata_json')->nullable();

            $table->timestampsTz();
        });

        // 4. Contract Amendments & Historical Payment Schedule Tracking
        Schema::create('contract_amendments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('amendment_code', 36)->unique();

            $table->foreignId('contract_id')->constrained('developer_sales_contracts')->cascadeOnDelete();
            $table->decimal('previous_price_etb', 18, 2);
            $table->decimal('revised_price_etb', 18, 2);
            $table->jsonb('previous_schedule_snapshot_json');
            $table->jsonb('revised_schedule_snapshot_json');
            $table->text('reason');

            $table->foreignId('authorized_by_user_id')->constrained('users');
            $table->boolean('accepted_by_buyer')->default(false);
            $table->timestampTz('accepted_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('contract_payment_transactions');
        Schema::dropIfExists('developer_payment_accounts');

        Schema::table('development_projects', function (Blueprint $table): void {
            if (Schema::hasColumn('development_projects', 'site_plan_description')) {
                $table->dropColumn('site_plan_description');
            }
            if (Schema::hasColumn('development_projects', 'site_plan_document_id')) {
                $table->dropConstrainedForeignId('site_plan_document_id');
            }
            if (Schema::hasColumn('development_projects', 'site_plan_url')) {
                $table->dropColumn('site_plan_url');
            }
        });
    }
};

