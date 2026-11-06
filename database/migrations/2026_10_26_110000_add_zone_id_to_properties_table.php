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
        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('zone_id')
                ->nullable()
                ->after('region_id')
                ->constrained('locations')
                ->nullOnDelete();

            $table->index(['region_id', 'zone_id', 'status'], 'idx_properties_region_zone_status');
        });

        // Safe backfill: If an existing property has a location whose ancestry
        // path contains a 'zone' location, resolve and backfill zone_id reliably without guessing.
        $properties = DB::table('properties')
            ->whereNull('zone_id')
            ->where(function ($query) {
                $query->whereNotNull('woreda_id')
                    ->orWhereNotNull('subcity_id')
                    ->orWhereNotNull('city_id');
            })
            ->select('id', 'region_id', 'city_id', 'subcity_id', 'woreda_id')
            ->get();

        foreach ($properties as $property) {
            $anchorLocationId = $property->woreda_id ?? $property->subcity_id ?? $property->city_id;
            if (! $anchorLocationId) {
                continue;
            }

            $anchor = DB::table('locations')->where('id', $anchorLocationId)->first();
            if (! $anchor || ! $anchor->ancestry_path) {
                continue;
            }

            $ancestorIds = array_filter(explode('/', trim($anchor->ancestry_path, '/')));
            if (empty($ancestorIds)) {
                continue;
            }

            $zoneAncestor = DB::table('locations')
                ->whereIn('id', $ancestorIds)
                ->where('level', 'zone')
                ->first();

            if ($zoneAncestor) {
                DB::table('properties')->where('id', $property->id)->update([
                    'zone_id' => $zoneAncestor->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropIndex('idx_properties_region_zone_status');
            $table->dropConstrainedForeignId('zone_id');
        });
    }
};
