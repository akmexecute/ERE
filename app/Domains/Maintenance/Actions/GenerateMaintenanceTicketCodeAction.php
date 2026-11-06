<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Actions;

use Illuminate\Support\Facades\DB;

final readonly class GenerateMaintenanceTicketCodeAction
{
    /**
     * Atomically generates a unique human-readable maintenance ticket code (e.g. MNT-2026-000612).
     * Prevents race conditions by using atomic counter increment with collision checking loop.
     */
    public function execute(): string
    {
        $year = date('Y');
        $prefix = "MNT-{$year}";
        $driver = DB::getDriverName();

        do {
            if ($driver === 'pgsql' || $driver === 'sqlite') {
                $timestampSql = $driver === 'pgsql' ? 'NOW()' : 'CURRENT_TIMESTAMP';
                $sql = "INSERT INTO property_code_sequences (prefix_key, last_value, updated_at)
                        VALUES (?, 1, {$timestampSql})
                        ON CONFLICT (prefix_key)
                        DO UPDATE SET last_value = property_code_sequences.last_value + 1, updated_at = {$timestampSql}
                        RETURNING last_value";

                $row = DB::selectOne($sql, [$prefix]);
                $seq = (int) ($row->last_value ?? 1);
            } else {
                $seq = (int) DB::table('property_code_sequences')
                    ->where('prefix_key', $prefix)
                    ->increment('last_value');

                if ($seq === 0) {
                    DB::table('property_code_sequences')->insert([
                        'prefix_key' => $prefix,
                        'last_value' => 1,
                        'updated_at' => now(),
                    ]);
                    $seq = 1;
                }
            }

            $candidate = sprintf('MNT-%s-%06d', $year, $seq);
        } while (DB::table('maintenance_requests')->where('ticket_code', $candidate)->exists());

        return $candidate;
    }
}
