<?php

declare(strict_types=1);

namespace App\Domains\Portfolio\Actions;

use Illuminate\Support\Facades\DB;

final readonly class GenerateMandateCodeAction
{
    /**
     * Atomically generates a unique human-readable mandate code (e.g. MND-2026-0000001).
     */
    public function execute(): string
    {
        $year = date('Y');
        $prefix = "MND-{$year}";
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

            $candidate = sprintf('MND-%s-%07d', $year, $seq);
        } while (DB::table('property_management_mandates')->where('mandate_code', $candidate)->exists());

        return $candidate;
    }
}
