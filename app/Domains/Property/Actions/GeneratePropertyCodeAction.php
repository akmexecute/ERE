<?php

declare(strict_types=1);

namespace App\Domains\Property\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class GeneratePropertyCodeAction
{
    /**
     * Generates a concurrency-safe, canonical human-readable property code.
     * E.g. ETH-AA-BOLE-RES-00001245 (SRS Section 9)
     *
     * Corrects the first-insert race condition by executing an atomic database upsert with RETURNING.
     */
    public function execute(
        string $countryCode = 'ETH',
        string $cityCode = 'AA',
        string $areaCode = 'GEN',
        string $categoryCode = 'RES'
    ): string {
        $cleanCountry = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($countryCode))) ?: 'ETH';
        $cleanCity = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($cityCode))) ?: 'AA';
        $cleanArea = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($areaCode))) ?: 'GEN';
        $cleanCategory = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($categoryCode))) ?: 'RES';

        if (strlen($cleanCountry) > 6 || strlen($cleanCity) > 12 || strlen($cleanArea) > 16 || strlen($cleanCategory) > 8) {
            throw new InvalidArgumentException('Prefix components exceed allowable length.');
        }

        $cleanPrefix = sprintf('%s-%s-%s-%s', $cleanCountry, $cleanCity, $cleanArea, $cleanCategory);

        $driver = DB::getDriverName();

        if ($driver === 'pgsql' || $driver === 'sqlite') {
            $timestampSql = $driver === 'pgsql' ? 'NOW()' : 'CURRENT_TIMESTAMP';
            $sql = "INSERT INTO property_code_sequences (prefix_key, last_value, updated_at)
                    VALUES (?, 1, {$timestampSql})
                    ON CONFLICT (prefix_key)
                    DO UPDATE SET last_value = property_code_sequences.last_value + 1, updated_at = {$timestampSql}
                    RETURNING last_value";

            $row = DB::selectOne($sql, [$cleanPrefix]);
            $nextNumber = (int) ($row->last_value ?? 1);
        } else {
            // General fallback: handle concurrent first-insert race condition safely
            $nextNumber = (int) DB::transaction(function () use ($cleanPrefix): int {
                $affected = DB::table('property_code_sequences')
                    ->where('prefix_key', $cleanPrefix)
                    ->increment('last_value');

                if ($affected === 0) {
                    try {
                        DB::table('property_code_sequences')->insert([
                            'prefix_key' => $cleanPrefix,
                            'last_value' => 1,
                            'updated_at' => now(),
                        ]);

                        return 1;
                    } catch (\Throwable) {
                        // Another concurrent transaction inserted the initial record simultaneously
                        DB::table('property_code_sequences')
                            ->where('prefix_key', $cleanPrefix)
                            ->increment('last_value');
                    }
                }

                return (int) DB::table('property_code_sequences')
                    ->where('prefix_key', $cleanPrefix)
                    ->value('last_value');
            });
        }

        return sprintf('%s-%08d', $cleanPrefix, $nextNumber);
    }
}
