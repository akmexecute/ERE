<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS Section 100: Company Management (Head Office, Addis Branch, Harar Branch, Jijiga Branch, Dire Dawa Branch)
        Schema::create('company_branches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('branch_code', 32); // e.g. HQ-AA, BR-HARAR, BR-JIJIGA, BR-DIREDAWA
            $table->string('name_en', 140);
            $table->string('name_am', 140)->nullable();
            $table->string('name_om', 140)->nullable();
            $table->boolean('is_head_office')->default(false)->index();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('subcity_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_code'], 'uq_company_branch_code');
        });

        // SRS Section 101: Agent Public Profile (name, company, photo, verification, service locations, languages, properties, reviews, response rate)
        Schema::create('agent_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('agent_code', 36)->unique(); // e.g. AGT-ETH-000184
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->nullOnDelete();

            // Proclamation No. 1357/2024 professional licensing & registration metadata (SRS Section 177)
            $table->string('license_number', 64)->nullable()->index();
            $table->string('specialization', 64)->default('residential_and_commercial');
            $table->unsignedTinyInteger('years_experience')->default(1);
            $table->jsonb('languages_json')->default('["en", "am"]'); // e.g. ["en", "am", "om", "so"]
            $table->text('bio_en')->nullable();
            $table->text('bio_am')->nullable();
            $table->text('bio_om')->nullable();

            // Performance & Trust Metrics (SRS Section 101)
            $table->unsignedTinyInteger('response_rate_pct')->default(100);
            $table->unsignedSmallInteger('avg_response_minutes')->default(15);
            $table->decimal('average_rating', 3, 2)->default(0.00)->index();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('active_listings_count')->default(0);
            $table->unsignedInteger('closed_deals_count')->default(0);
            $table->boolean('is_verified_agent')->default(false)->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestampsTz();
        });

        // SRS Section 101: Agent Service Locations
        Schema::create('agent_service_locations', function (Blueprint $table): void {
            $table->foreignId('agent_profile_id')->constrained('agent_profiles')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->primary(['agent_profile_id', 'location_id']);
        });

        // SRS Section 99: Commission Engine (Configurable by company, property type, agent, transaction, date, and price brackets)
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 140)->default('Default Commission Rule');
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->nullOnDelete();
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();

            // sale, rental, lease, developer_unit (SRS Section 99)
            $table->string('transaction_type', 28)->default('sale')->index();

            // Price tier brackets
            $table->decimal('min_property_value', 18, 2)->nullable()->default(0.00);
            $table->decimal('max_property_value', 18, 2)->nullable()->default(999999999999.99);

            // Percentage e.g. 2.00 (%) -> on 20,000,000 ETB = 400,000 ETB (SRS Section 99)
            $table->decimal('commission_rate_pct', 6, 3)->default(2.000);
            $table->decimal('base_commission_percentage', 6, 3)->default(2.000);
            $table->decimal('flat_bonus_etb', 18, 2)->default(0.00);

            // Split between Real Estate Company/Branch and Individual Agent
            $table->decimal('agent_split_pct', 5, 2)->default(50.00);
            $table->decimal('agent_split_percentage', 5, 2)->default(50.00);
            $table->decimal('company_split_pct', 5, 2)->default(50.00);
            $table->decimal('company_split_percentage', 5, 2)->default(50.00);

            $table->date('effective_from')->nullable()->index();
            $table->date('effective_to')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        // Auditable Commission Ledger per Closed Transaction
        Schema::create('commission_calculations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->nullOnDelete();
            $table->foreignId('agent_user_id')->constrained('users');
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();

            $table->string('transaction_type', 28)->default('sale');
            $table->string('transaction_reference', 80)->nullable()->index();
            $table->decimal('transaction_value_etb', 18, 2);
            $table->decimal('applied_rate_pct', 6, 3);
            $table->decimal('gross_commission_etb', 18, 2);
            $table->decimal('agent_payout_etb', 18, 2);
            $table->decimal('company_share_etb', 18, 2);
            $table->string('status', 24)->default('pending_settlement')->index(); // pending_settlement, approved, paid
            $table->timestampsTz();
        });

        // Commission Payouts Ledger (Total Commission = Sale Price * Commission Percentage; company_earnings & agent_earnings)
        Schema::create('commission_payouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->nullOnDelete();
            $table->foreignId('agent_user_id')->constrained('users');
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();

            $table->decimal('sale_price', 18, 2);
            $table->decimal('commission_percentage', 6, 3);
            $table->decimal('total_commission', 18, 2);
            $table->decimal('company_earnings', 18, 2);
            $table->decimal('agent_earnings', 18, 2);
            $table->string('status', 24)->default('finalized')->index(); // pending, finalized, paid
            $table->timestampTz('payout_date')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('commission_calculations');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('agent_service_locations');
        Schema::dropIfExists('agent_profiles');
        Schema::dropIfExists('company_branches');
    }
};
