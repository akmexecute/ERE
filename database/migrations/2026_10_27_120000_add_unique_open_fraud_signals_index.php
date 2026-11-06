<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Reconcile any existing active duplicates safely without losing history or evidence
        $rows = DB::table('fraud_signals')
            ->where('status', 'open')
            ->orderBy('id', 'asc')
            ->get(['id', 'listing_id', 'signal_type', 'matched_property_id', 'severity', 'confidence_score', 'evidence_json']);

        $groups = [];
        foreach ($rows as $row) {
            $match = $row->matched_property_id ?? 0;
            $key = "{$row->listing_id}:{$row->signal_type}:{$match}";
            $groups[$key][] = $row;
        }

        foreach ($groups as $signalList) {
            if (count($signalList) <= 1) {
                continue;
            }

            $canonical = array_shift($signalList);
            $canonicalEvidence = json_decode($canonical->evidence_json ?? '[]', true) ?: [];

            foreach ($signalList as $dup) {
                $dupEvidence = json_decode($dup->evidence_json ?? '[]', true) ?: [];
                $canonicalEvidence['reconciled_duplicates'][] = [
                    'duplicate_signal_id' => $dup->id,
                    'severity' => $dup->severity,
                    'confidence_score' => $dup->confidence_score,
                    'evidence' => $dupEvidence,
                    'reconciled_at' => now()->toIso8601String(),
                ];

                DB::table('fraud_signals')->where('id', $dup->id)->update([
                    'status' => 'reconciled_duplicate',
                    'updated_at' => now(),
                ]);
            }

            DB::table('fraud_signals')->where('id', $canonical->id)->update([
                'evidence_json' => json_encode($canonicalEvidence),
                'updated_at' => now(),
            ]);
        }

        // 2. Database-backed concurrency guard: partial unique index for active ('open') signals
        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS uq_open_fraud_signal
                ON fraud_signals (listing_id, signal_type, (COALESCE(matched_property_id, 0)))
                WHERE status = 'open';
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS uq_open_fraud_signal;');
        }
    }
};
