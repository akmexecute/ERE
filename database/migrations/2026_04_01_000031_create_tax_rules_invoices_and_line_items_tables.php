<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 195 & Module 30: Database-Driven Tax Rules
        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tax_code', 32)->index(); // VAT, TOT, WITHHOLDING, TOURISM_TAX
            $table->string('name_en', 140);
            $table->string('name_am', 140)->nullable();
            $table->string('name_om', 140)->nullable();

            // Applicable service type: rent, lease, short_stay, commission, maintenance, all
            $table->string('service_type', 32)->default('all')->index();

            // Geographic scope: null = National fallback, or specific location_id (region/city)
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

            // Calculation rate in percentage (e.g. 15.000 for 15% VAT, 2.000 for 2% Withholding)
            $table->decimal('rate_pct', 6, 3);

            // Calculation semantics
            $table->boolean('is_compound')->default(false); // Applied on (subtotal + earlier taxes)
            $table->boolean('is_inclusive')->default(false); // Price includes tax
            $table->boolean('is_withholding')->default(false); // Withholding deduction
            $table->unsignedSmallInteger('calculation_order')->default(10); // Evaluation priority

            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestampsTz();

            $table->index(['tax_code', 'service_type', 'is_active', 'effective_from'], 'idx_tax_rules_lookup');
        });

        // Module 30: Persisted Invoices (SRS Section 155, 195)
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number', 40)->unique(); // e.g. INV-2026-0000941

            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->nullOnDelete();
            $table->foreignId('customer_user_id')->constrained('users');

            $table->string('customer_name_snapshot', 180);
            $table->string('customer_tin_snapshot', 40)->nullable();

            // rent, lease, maintenance, commission, short_stay
            $table->string('service_type', 32)->index();

            // Optional polymorphic source reference (Lease, RentalPayment, Booking, MaintenanceRequest)
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('currency', 8)->default('ETB');

            // Decimal-Safe Financial Amounts in ETB
            $table->decimal('subtotal_amount_etb', 18, 2);
            $table->decimal('tax_amount_etb', 18, 2)->default(0.00);
            $table->decimal('total_amount_etb', 18, 2);
            $table->decimal('paid_amount_etb', 18, 2)->default(0.00);
            $table->decimal('outstanding_balance_etb', 18, 2);

            $table->date('issue_date')->index();
            $table->date('due_date')->nullable()->index();

            // Lifecycle status: unpaid, partially_paid, paid, voided, refunded
            $table->string('payment_status', 24)->default('unpaid')->index();

            // Immutable snapshot of resolved tax rules and calculations at invoice issuance
            $table->jsonb('tax_snapshots_json')->nullable();

            $table->text('notes')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'payment_status', 'issue_date'], 'idx_invoices_company_status');
            $table->index(['customer_user_id', 'payment_status'], 'idx_invoices_customer_status');
            $table->index(['reference_type', 'reference_id'], 'idx_invoices_reference');
        });

        // Module 30: Itemized Line Items
        Schema::create('invoice_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price_etb', 18, 2);
            $table->decimal('line_subtotal_etb', 18, 2);

            // Snapshot of applied tax rule
            $table->string('tax_code', 32)->nullable();
            $table->string('tax_name_snapshot', 140)->nullable();
            $table->decimal('tax_rate_pct', 6, 3)->nullable();
            $table->decimal('tax_amount_etb', 18, 2)->default(0.00);
            $table->decimal('line_total_etb', 18, 2);

            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('tax_rules');
    }
};
