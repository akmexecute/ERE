<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('viewing_appointments')) {
            Schema::table('viewing_appointments', function (Blueprint $table): void {
                if (! Schema::hasColumn('viewing_appointments', 'slot_start_time')) {
                    $table->string('slot_start_time', 8)->nullable()->index()->after('scheduled_time_slot');
                }
                if (! Schema::hasColumn('viewing_appointments', 'slot_end_time')) {
                    $table->string('slot_end_time', 8)->nullable()->index()->after('slot_start_time');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('viewing_appointments')) {
            Schema::table('viewing_appointments', function (Blueprint $table): void {
                if (Schema::hasColumn('viewing_appointments', 'slot_end_time')) {
                    $table->dropColumn('slot_end_time');
                }
                if (Schema::hasColumn('viewing_appointments', 'slot_start_time')) {
                    $table->dropColumn('slot_start_time');
                }
            });
        }
    }
};

