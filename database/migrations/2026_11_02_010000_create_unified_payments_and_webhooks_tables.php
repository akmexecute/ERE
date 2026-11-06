<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Unified Payments Table (SRS Section 80 & Module 31)
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('transaction_id', 40)->unique(); // e.g. TXN-2026-0000001
            $table->string('reference_code', 40)->unique(); // e.g. PAY-2026-0000001
            $table->string('idempotency_key', 80)->unique();

            $table->foreignId('customer_user_id')->constrained('users')->restrictOnDelete();

            // Polymorphic payable relation (Booking, UnitReservation, PropertyOffer, DeveloperSalesContract, ContractInstallment, RentSchedule, Invoice, Listing)
            $table->string('payable_type', 120)->index();
            $table->unsignedBigInteger('payable_id')->index();

            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();

            // 11 Payment Types: booking, reservation, listing, premium_listing, subscription, rent, lease, developer_installment, service, commission, property_purchase
            $table->string('payment_type', 32)->index();

            // Decimal-safe ETB financial breakdown
            $table->decimal('principal_amount_etb', 18, 2);
            $table->decimal('fee_amount_etb', 18, 2)->default(0.00);
            $table->decimal('tax_amount_etb', 18, 2)->default(0.00);
            $table->decimal('total_amount_etb', 18, 2);
            $table->decimal('refunded_amount_etb', 18, 2)->default(0.00);
            $table->string('currency', 8)->default('ETB');

            // Payment method & provider integration
            // Method: telebirr, cbe_birr, bank_transfer, card, chapa
            $table->string('payment_method', 32)->index();
            $table->string('provider', 32)->nullable()->index();
            $table->string('provider_transaction_reference', 120)->nullable()->index();

            // Lifecycle status: pending, processing, successful, failed, cancelled, reversed, refunded, partially_refunded
            $table->string('status', 28)->default('pending')->index();
            $table->string('failure_reason', 400)->nullable();

            $table->jsonb('metadata_json')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->index(['payable_type', 'payable_id', 'status'], 'idx_payments_payable_status');
            $table->index(['customer_user_id', 'status', 'created_at'], 'idx_payments_customer_status');
        });

        // 2. Payment Initiation Idempotency Records
        Schema::create('payment_idempotencies', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 80)->unique();
            $table->string('request_hash', 64)->index();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            // pending, completed, conflict, failed
            $table->string('status', 24)->default('pending')->index();
            $table->jsonb('response_payload_json')->nullable();
            $table->timestampsTz();
        });

        // 3. Authenticated & Idempotent Webhook Events (SRS Sections 191 & 192)
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 32)->index(); // telebirr, cbe_birr, chapa
            $table->string('provider_event_id', 120)->index();
            $table->string('event_type', 64)->index();
            $table->string('transaction_reference', 120)->nullable()->index();
            $table->string('signature', 255)->nullable();
            // Sanitized payload (PCI/GDPR compliant: no card numbers, CVVs, or plain credentials)
            $table->jsonb('payload_json');
            // received, processed, failed, ignored
            $table->string('status', 24)->default('received')->index();
            $table->text('failure_reason')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_event_id'], 'uq_webhook_provider_event');
        });

        // 4. Bank Transfer Verification & Slips Workflow
        Schema::create('bank_transfer_proofs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('transfer_reference', 100)->index();
            $table->string('bank_name', 120);
            $table->string('account_number', 64)->nullable();
            $table->string('account_holder_name', 160)->nullable();
            $table->decimal('deposited_amount_etb', 18, 2);
            $table->date('deposited_at')->nullable();
            $table->string('proof_file_path', 255);
            $table->text('notes')->nullable();

            // submitted, under_review, verified, rejected, requires_information
            $table->string('verification_status', 32)->default('submitted')->index();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->jsonb('audit_trail_json')->nullable();
            $table->timestampsTz();
        });

        // 5. Enhance property_offers with metadata_json for purchase balance and payment tracking
        if (Schema::hasTable('property_offers') && ! Schema::hasColumn('property_offers', 'metadata_json')) {
            Schema::table('property_offers', function (Blueprint $table): void {
                $table->jsonb('metadata_json')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('property_offers') && Schema::hasColumn('property_offers', 'metadata_json')) {
            Schema::table('property_offers', function (Blueprint $table): void {
                $table->dropColumn('metadata_json');
            });
        }

        Schema::dropIfExists('bank_transfer_proofs');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payment_idempotencies');
        Schema::dropIfExists('payments');
    }
};
