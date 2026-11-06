<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Forward-compatible migration ensuring awaiting_response_from is nullable in terminal states
     * across existing migrated databases.
     */
    public function up(): void
    {
        if (Schema::hasTable('property_offers') && Schema::hasColumn('property_offers', 'awaiting_response_from')) {
            Schema::table('property_offers', function (Blueprint $table): void {
                $table->string('awaiting_response_from', 16)->nullable()->default('seller')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('property_offers') && Schema::hasColumn('property_offers', 'awaiting_response_from')) {
            Schema::table('property_offers', function (Blueprint $table): void {
                $table->string('awaiting_response_from', 16)->nullable(false)->default('seller')->change();
            });
        }
    }
};

