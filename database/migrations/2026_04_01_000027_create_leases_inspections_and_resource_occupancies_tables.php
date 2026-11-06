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
        // 1. Lease Contracts (SRS Section 148 & Module 26)
        Schema::create('leases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('lease_code', 36)->unique(); // e.g. LSE-2026-0000001
            $table->foreignId('rental_application_id')->nullable()->constrained('rental_applications')->nullOnDelete();

            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('project_unit_id')->nullable()->constrained('project_units')->nullOnDelete();

            $table->foreignId('tenant_id')->constrained('users');
            $table->foreignId('landlord_id')->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // Contract Term Boundaries & Pricing (SRS Section 149)
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('monthly_rent_etb', 18, 2);
            $table->decimal('security_deposit_etb', 18, 2)->default(0.00);

            // Payment Frequency: monthly, quarterly, semi_annual, annual
            $table->string('payment_frequency', 24)->default('monthly');
            $table->unsignedTinyInteger('due_day_of_month')->default(1); // 1-28

            $table->text('contract_terms')->nullable();

            // Lifecycle Status (SRS Section 150)
            // draft, pending_signatures, active, move_out_pending, completed, terminated
            $table->string('status', 28)->default('draft')->index();

            // Signatures & Cryptographic Audit Evidence (SRS Section 151)
            $table->timestampTz('tenant_signed_at')->nullable();
            $table->string('tenant_signature_ip', 45)->nullable();
            $table->timestampTz('landlord_signed_at')->nullable();
            $table->string('landlord_signature_ip', 45)->nullable();
            $table->foreignId('signed_contract_document_id')->nullable()->constrained('documents')->nullOnDelete();

            // Move-In and Move-Out Timestamps
            $table->timestampTz('move_in_at')->nullable();
            $table->timestampTz('move_out_at')->nullable();

            // Termination details
            $table->string('termination_reason', 300)->nullable();
            $table->timestampTz('terminated_at')->nullable();
            $table->foreignId('terminated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['landlord_id', 'status'], 'idx_leases_landlord_status');
            $table->index(['tenant_id', 'status'], 'idx_leases_tenant_status');
        });

        // Add foreign key constraint from rental_applications.converted_lease_id to leases
        Schema::table('rental_applications', function (Blueprint $table): void {
            $table->foreign('converted_lease_id')->references('id')->on('leases')->nullOnDelete();
        });

        // 2. Concurrency-Safe Lease Resource Occupancies with PostgreSQL GiST Exclusion Constraint
        // Solves the Whole-Property vs. Unit-Level Lease Overlap Race Condition (Master Prompt Section 7.1 & 7.2)
        Schema::create('lease_resource_occupancies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            // 0 represents entire property asset, positive number represents project_unit_id
            $table->unsignedBigInteger('resource_id')->index();
            $table->string('status', 28)->index();
            $table->timestampTz('created_at')->useCurrent();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist;');

            // Generated daterange column matching lease start and end dates (inclusive '[]')
            DB::statement(<<<'SQL'
                ALTER TABLE lease_resource_occupancies
                ADD COLUMN lease_daterange daterange;
            SQL);

            // GiST Exclusion Constraint: No two overlapping active lease records on the same resource_id
            DB::statement(<<<'SQL'
                ALTER TABLE lease_resource_occupancies
                ADD CONSTRAINT ex_lease_resource_no_overlap
                EXCLUDE USING GIST (
                    property_id WITH =,
                    resource_id WITH =,
                    lease_daterange WITH &&
                )
                WHERE (status IN ('active', 'pending_signatures', 'move_out_pending'));
            SQL);

            // Cross-Scope Whole-Property vs. Unit-Level Conflict Trigger
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION check_lease_whole_property_conflict()
                RETURNS TRIGGER AS $$
                BEGIN
                    -- If inserting/updating a unit-level occupancy (resource_id > 0), ensure resource_id = 0 (whole-property) does not overlap
                    IF NEW.resource_id > 0 AND NEW.status IN ('active', 'pending_signatures', 'move_out_pending') THEN
                        IF EXISTS (
                            SELECT 1 FROM lease_resource_occupancies
                            WHERE property_id = NEW.property_id
                              AND resource_id = 0
                              AND status IN ('active', 'pending_signatures', 'move_out_pending')
                              AND lease_daterange && NEW.lease_daterange
                              AND lease_id != NEW.lease_id
                        ) THEN
                            RAISE EXCEPTION 'Cannot lease unit: the entire property is already leased for this overlapping period (property_id: %, unit_id: %)', NEW.property_id, NEW.resource_id;
                        END IF;
                    END IF;

                    -- If inserting/updating a whole-property occupancy (resource_id = 0), ensure NO unit occupancy (resource_id > 0) overlaps
                    IF NEW.resource_id = 0 AND NEW.status IN ('active', 'pending_signatures', 'move_out_pending') THEN
                        IF EXISTS (
                            SELECT 1 FROM lease_resource_occupancies
                            WHERE property_id = NEW.property_id
                              AND resource_id > 0
                              AND status IN ('active', 'pending_signatures', 'move_out_pending')
                              AND lease_daterange && NEW.lease_daterange
                              AND lease_id != NEW.lease_id
                        ) THEN
                            RAISE EXCEPTION 'Cannot lease entire property: one or more individual units are already leased for this overlapping period (property_id: %)', NEW.property_id;
                        END IF;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_check_lease_whole_property_conflict
                BEFORE INSERT OR UPDATE ON lease_resource_occupancies
                FOR EACH ROW EXECUTE FUNCTION check_lease_whole_property_conflict();
            SQL);
        }

        // 3. Move-In and Move-Out Inspections (SRS Section 152 & Module 26)
        Schema::create('property_inspections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties');
            $table->foreignId('project_unit_id')->nullable()->constrained('project_units')->nullOnDelete();

            // move_in, move_out
            $table->string('inspection_type', 24)->index();
            $table->date('inspection_date');
            $table->foreignId('inspector_user_id')->constrained('users');

            // Utility Meter Readings
            $table->decimal('electricity_meter_reading', 10, 2)->nullable();
            $table->decimal('water_meter_reading', 10, 2)->nullable();

            // Condition checklist & photos
            $table->jsonb('condition_checklist_json')->nullable();
            $table->jsonb('photographs_json')->nullable();
            $table->text('notes')->nullable();

            // Digital Acknowledgements & Signatures
            $table->timestampTz('tenant_acknowledged_at')->nullable();
            $table->string('tenant_signature_ip', 45)->nullable();
            $table->timestampTz('landlord_acknowledged_at')->nullable();
            $table->string('landlord_signature_ip', 45)->nullable();

            $table->timestampsTz();

            $table->unique(['lease_id', 'inspection_type'], 'uq_lease_inspection_type');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_check_lease_whole_property_conflict ON lease_resource_occupancies;');
            DB::statement('DROP FUNCTION IF EXISTS check_lease_whole_property_conflict();');
        }

        Schema::dropIfExists('property_inspections');
        Schema::dropIfExists('lease_resource_occupancies');

        Schema::table('rental_applications', function (Blueprint $table): void {
            $table->dropForeign(['converted_lease_id']);
        });

        Schema::dropIfExists('leases');
    }
};
