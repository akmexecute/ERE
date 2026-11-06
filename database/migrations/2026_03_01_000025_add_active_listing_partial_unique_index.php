<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Unbypassable PostgreSQL partial unique index ensuring at most one active offer per (property_id, transaction_mode)
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS idx_listings_unique_active_property_mode
                ON listings (property_id, transaction_mode)
                WHERE status IN ('pending_review', 'approved', 'published')
                  AND deleted_at IS NULL;
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_listings_unique_active_property_mode;');
        }
    }
};
