<?php

declare(strict_types=1);

namespace App\Domains\Lease\Actions;

use App\Domains\Lease\Enums\InspectionType;
use App\Domains\Lease\Models\Lease;
use App\Domains\Lease\Models\PropertyInspection;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RecordPropertyInspectionAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $inspector, Lease $lease, InspectionType $stage, array $payload): PropertyInspection
    {
        // 1. Authorize: inspector must be landlord, agent, or authorized staff
        $isLandlord = ($inspector->id === $lease->landlord_id);
        $isCompany = ($lease->company_id && $inspector->company_id === $lease->company_id);
        $isStaff = $inspector->hasAnyRole(['property_admin', 'platform_admin', 'super_admin']);

        if (! $isLandlord && ! $isCompany && ! $isStaff) {
            throw new AuthorizationException('You are not authorized to record property inspections for this lease.');
        }

        // 2. Validate meter readings
        $electricity = isset($payload['electricity_meter_reading']) && is_numeric($payload['electricity_meter_reading'])
            ? round((float) $payload['electricity_meter_reading'], 2)
            : null;

        $water = isset($payload['water_meter_reading']) && is_numeric($payload['water_meter_reading'])
            ? round((float) $payload['water_meter_reading'], 2)
            : null;

        $inspectionDate = Carbon::parse($payload['inspection_date'] ?? now());

        return DB::transaction(function () use (
            $inspector,
            $lease,
            $stage,
            $payload,
            $electricity,
            $water,
            $inspectionDate
        ): PropertyInspection {
            // Check for existing inspection for this lease and stage
            /** @var PropertyInspection|null $existing */
            $existing = PropertyInspection::query()
                ->where('lease_id', $lease->id)
                ->where('inspection_type', $stage)
                ->first();

            if ($existing) {
                if ($existing->isFullyAcknowledged()) {
                    throw ValidationException::withMessages([
                        'inspection_type' => ['This inspection has already been finalized and acknowledged by both parties.'],
                    ]);
                }

                $existing->update([
                    'inspection_date' => $inspectionDate->toDateString(),
                    'inspector_user_id' => $inspector->id,
                    'electricity_meter_reading' => $electricity ?? $existing->electricity_meter_reading,
                    'water_meter_reading' => $water ?? $existing->water_meter_reading,
                    'condition_checklist_json' => $payload['condition_checklist'] ?? $existing->condition_checklist_json,
                    'photographs_json' => $payload['photographs'] ?? $existing->photographs_json,
                    'notes' => $payload['notes'] ?? $existing->notes,
                ]);

                $inspection = $existing->fresh();
            } else {
                $inspection = PropertyInspection::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'lease_id' => $lease->id,
                    'property_id' => $lease->property_id,
                    'project_unit_id' => $lease->project_unit_id,
                    'inspection_type' => $stage,
                    'inspection_date' => $inspectionDate->toDateString(),
                    'inspector_user_id' => $inspector->id,
                    'electricity_meter_reading' => $electricity,
                    'water_meter_reading' => $water,
                    'condition_checklist_json' => $payload['condition_checklist'] ?? [],
                    'photographs_json' => $payload['photographs'] ?? [],
                    'notes' => $payload['notes'] ?? null,
                ]);
            }

            // Update lease milestone timestamps
            if ($stage === InspectionType::MoveIn && ! $lease->move_in_at) {
                $lease->update(['move_in_at' => now()]);
            } elseif ($stage === InspectionType::MoveOut && ! $lease->move_out_at) {
                $lease->update(['move_out_at' => now()]);
            }

            return $inspection;
        });
    }
}
