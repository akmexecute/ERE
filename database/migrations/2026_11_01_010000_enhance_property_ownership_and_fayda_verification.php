<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance verifications with method, reference, and evidence tracking
        Schema::table('verifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('verifications', 'verification_method')) {
                $table->string('verification_method', 32)->default('manual_review')->after('badge_code');
            }
            if (! Schema::hasColumn('verifications', 'verification_reference')) {
                $table->string('verification_reference', 64)->nullable()->index()->after('verification_method');
            }
            if (! Schema::hasColumn('verifications', 'provider_reference')) {
                $table->string('provider_reference', 128)->nullable()->after('verification_reference');
            }
            if (! Schema::hasColumn('verifications', 'requires_reverification')) {
                $table->boolean('requires_reverification')->default(false)->after('provider_reference');
            }
            if (! Schema::hasColumn('verifications', 'reverification_reason')) {
                $table->string('reverification_reason', 500)->nullable()->after('requires_reverification');
            }
            if (! Schema::hasColumn('verifications', 'evidence_type')) {
                $table->string('evidence_type', 64)->nullable()->after('reverification_reason');
            }
            if (! Schema::hasColumn('verifications', 'authority_scope')) {
                $table->string('authority_scope', 48)->nullable()->after('evidence_type');
            }
        });

        // 2. Enhance properties with ownership and selling authority verification
        Schema::table('properties', function (Blueprint $table): void {
            if (! Schema::hasColumn('properties', 'ownership_status')) {
                $table->string('ownership_status', 32)->default('unverified')->index()->after('verification_level');
            }
            if (! Schema::hasColumn('properties', 'ownership_evidence_type')) {
                $table->string('ownership_evidence_type', 64)->nullable()->after('ownership_status');
            }
            if (! Schema::hasColumn('properties', 'ownership_verified_at')) {
                $table->timestampTz('ownership_verified_at')->nullable()->after('ownership_evidence_type');
            }
            if (! Schema::hasColumn('properties', 'ownership_verified_by_user_id')) {
                $table->foreignId('ownership_verified_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('ownership_verified_at');
            }
            if (! Schema::hasColumn('properties', 'authorized_seller_user_id')) {
                $table->foreignId('authorized_seller_user_id')->nullable()->constrained('users')->nullOnDelete()->after('ownership_verified_by_user_id');
            }
            if (! Schema::hasColumn('properties', 'authority_valid_until')) {
                $table->date('authority_valid_until')->nullable()->after('authorized_seller_user_id');
            }
            if (! Schema::hasColumn('properties', 'ownership_legal_disclaimer')) {
                $table->string('ownership_legal_disclaimer', 255)->nullable()->after('authority_valid_until');
            }
        });

        // 3. Add seller identity snapshot to property_offers (for ordinary property sales)
        Schema::table('property_offers', function (Blueprint $table): void {
            if (! Schema::hasColumn('property_offers', 'seller_identity_snapshot_json')) {
                $table->json('seller_identity_snapshot_json')->nullable()->after('rejected_at');
            }
        });

        // 4. Link support_tickets to property_offers as well as contracts
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_tickets', 'offer_id')) {
                $table->foreignId('offer_id')->nullable()->constrained('property_offers')->nullOnDelete()->after('contract_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('support_tickets', 'offer_id')) {
                $table->dropConstrainedForeignId('offer_id');
            }
        });

        Schema::table('property_offers', function (Blueprint $table): void {
            if (Schema::hasColumn('property_offers', 'seller_identity_snapshot_json')) {
                $table->dropColumn('seller_identity_snapshot_json');
            }
        });

        Schema::table('properties', function (Blueprint $table): void {
            if (Schema::hasColumn('properties', 'authorized_seller_user_id')) {
                $table->dropConstrainedForeignId('authorized_seller_user_id');
            }
            if (Schema::hasColumn('properties', 'ownership_verified_by_user_id')) {
                $table->dropConstrainedForeignId('ownership_verified_by_user_id');
            }
            $table->dropColumn([
                'ownership_status',
                'ownership_evidence_type',
                'ownership_verified_at',
                'authority_valid_until',
                'ownership_legal_disclaimer',
            ]);
        });

        Schema::table('verifications', function (Blueprint $table): void {
            $table->dropColumn([
                'verification_method',
                'verification_reference',
                'provider_reference',
                'requires_reverification',
                'reverification_reason',
                'evidence_type',
                'authority_scope',
            ]);
        });
    }
};

