<?php

declare(strict_types=1);

namespace App\Domains\Property\Actions;

use Illuminate\Support\Facades\DB;

final readonly class GeneratePropertyCodeAction
{
    public function execute(
        string $countryCode = 'ETH',
        string $cityCode = 'AA',
        string $areaCode = 'GEN',
        string $categoryCode = 'RES'
    ): string {
        $cleanPrefix = strtoupper(sprintf(
            '%s-%s-%s-%s',
            preg_replace('/[^A-Z0-9]/', '', strtoupper($countryCode)) ?: 'ETH',
            preg_replace('/[^A-Z0-9]/', '', strtoupper($cityCode)) ?: 'AA',
            preg_replace('/[^A-Z0-9]/', '', strtoupper($areaCode)) ?: 'GEN',
            preg_replace('/[^A-Z0-9]/', '', strtoupper($categoryCode)) ?: 'RES',
        ));

        $nextNumber = DB::transaction(function () use ($cleanPrefix): int {
            $exists = DB::table('property_code_sequences')
                ->where('prefix_key', $cleanPrefix)
                ->lockForUpdate()
                ->first();

            if (! $exists) {
                DB::table('property_code_sequences')->insert([
                    'prefix_key' => $cleanPrefix,
                    'last_value' => 1,
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = ((int) $exists->last_value) + 1;
            DB::table('property_code_sequences')
                ->where('prefix_key', $cleanPrefix)
                ->update([
                    'last_value' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        });

        return sprintf('%s-%08d', $cleanPrefix, $nextNumber);
    }
}
