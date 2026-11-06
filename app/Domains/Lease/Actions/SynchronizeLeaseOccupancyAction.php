<?php

declare(strict_types=1);

namespace App\Domains\Lease\Actions;

use App\Domains\Lease\Models\Lease;
use App\Models\ProjectUnit;
use Illuminate\Support\Facades\DB;

final readonly class SynchronizeLeaseOccupancyAction
{
    /**
     * Synchronizes concurrency-safe lease resource occupancies in PostgreSQL (Master Prompt Section 7).
     * Enforces that:
     * 1. Two leases for the same entire property cannot overlap.
     * 2. Two leases for the same individual unit cannot overlap.
     * 3. A whole-property lease cannot overlap any unit-level lease on that property.
     */
    public function execute(Lease $lease): void
    {
        $driver = DB::getDriverName();

        // If the lease status is not active or reserving inventory, remove existing occupancy locks
        if (! $lease->status->isBlockingOccupancy()) {
            DB::table('lease_resource_occupancies')
                ->where('lease_id', $lease->id)
                ->delete();

            return;
        }

        // Clean prior occupancies for this lease before re-inserting new state
        $startDate = $lease->start_date->toDateString();
        $endDate = $lease->end_date->toDateString();
        $statusStr = $lease->status->value;

        // Check for conflicting overlapping leases across whole-property and unit-level scopes
        $conflictingOccupancy = DB::table('lease_resource_occupancies')
            ->join('leases', 'leases.id', '=', 'lease_resource_occupancies.lease_id')
            ->where('lease_resource_occupancies.property_id', $lease->property_id)
            ->where('lease_resource_occupancies.lease_id', '!=', $lease->id)
            ->whereIn('leases.status', [
                \App\Domains\Lease\Enums\LeaseStatus::Active->value,
                \App\Domains\Lease\Enums\LeaseStatus::PendingSignatures->value,
                \App\Domains\Lease\Enums\LeaseStatus::MoveOutPending->value,
            ])
            ->where(function ($q) use ($lease): void {
                if ($lease->project_unit_id) {
                    // Unit lease conflicts with whole-property (0) or the same unit
                    $q->where('lease_resource_occupancies.resource_id', 0)
                      ->orWhere('lease_resource_occupancies.resource_id', (int) $lease->project_unit_id);
                }
                // Whole-property lease conflicts with all resources (0 or any unit) on this property
            })
            ->where(function ($dateQ) use ($driver, $startDate, $endDate): void {
                if ($driver === 'sqlite') {
                    $dateQ->whereRaw('date(leases.start_date) < date(?)', [$endDate])
                          ->whereRaw('date(leases.end_date) > date(?)', [$startDate]);
                } else {
                    $dateQ->where('leases.start_date', '<', $endDate)
                          ->where('leases.end_date', '>', $startDate);
                }
            })
            ->exists();

        if ($conflictingOccupancy) {
            throw new \InvalidArgumentException(
                "Lease period ({$startDate} to {$endDate}) overlaps an existing active or pending lease for this resource scope."
            );
        }

        DB::table('lease_resource_occupancies')
            ->where('lease_id', $lease->id)
            ->delete();

        if ($lease->project_unit_id) {
            // Unit-level lease
            $unitId = (int) $lease->project_unit_id;

            if ($driver === 'pgsql') {
                DB::statement(
                    'INSERT INTO lease_resource_occupancies (lease_id, property_id, resource_id, lease_daterange, status, created_at)
                     VALUES (?, ?, ?, daterange(?, ?, \'[)\'), ?, NOW())',
                    [$lease->id, $lease->property_id, $unitId, $startDate, $endDate, $statusStr]
                );
            } else {
                DB::table('lease_resource_occupancies')->insert([
                    'lease_id' => $lease->id,
                    'property_id' => $lease->property_id,
                    'resource_id' => $unitId,
                    'status' => $statusStr,
                    'created_at' => now(),
                ]);
            }
        } else {
            // Whole-property lease
            // 1. Lock the whole property asset (resource_id = 0)
            if ($driver === 'pgsql') {
                DB::statement(
                    'INSERT INTO lease_resource_occupancies (lease_id, property_id, resource_id, lease_daterange, status, created_at)
                     VALUES (?, ?, 0, daterange(?, ?, \'[)\'), ?, NOW())',
                    [$lease->id, $lease->property_id, $startDate, $endDate, $statusStr]
                );
            } else {
                DB::table('lease_resource_occupancies')->insert([
                    'lease_id' => $lease->id,
                    'property_id' => $lease->property_id,
                    'resource_id' => 0,
                    'status' => $statusStr,
                    'created_at' => now(),
                ]);
            }

            // 2. Also lock all individual project units that belong to this property/project
            $unitIds = ProjectUnit::query()
                ->where('project_id', $lease->property_id)
                ->pluck('id')
                ->all();

            foreach ($unitIds as $uId) {
                if ($driver === 'pgsql') {
                    DB::statement(
                        'INSERT INTO lease_resource_occupancies (lease_id, property_id, resource_id, lease_daterange, status, created_at)
                         VALUES (?, ?, ?, daterange(?, ?, \'[)\'), ?, NOW())',
                        [$lease->id, $lease->property_id, (int) $uId, $startDate, $endDate, $statusStr]
                    );
                } else {
                    DB::table('lease_resource_occupancies')->insert([
                        'lease_id' => $lease->id,
                        'property_id' => $lease->property_id,
                        'resource_id' => (int) $uId,
                        'status' => $statusStr,
                        'created_at' => now(),
                    ]);
                }
            }
        }
    }
}
