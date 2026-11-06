<?php

declare(strict_types=1);

namespace App\Domains\Lease\Actions;

use App\Domains\Lease\Enums\LeaseStatus;
use App\Domains\Lease\Models\Lease;
use App\Domains\Property\Models\Property;
use App\Domains\RentalFinancial\Actions\GenerateRentScheduleAction;
use App\Domains\User\Models\User;
use App\Models\ProjectUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateLeaseContractAction
{
    public function __construct(
        private GenerateLeaseCodeAction $codeGenerator,
        private SynchronizeLeaseOccupancyAction $occupancySynchronizer,
        private GenerateRentScheduleAction $scheduleGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $creator, array $payload): Lease
    {
        $startDate = Carbon::parse($payload['start_date']);
        $endDate = Carbon::parse($payload['end_date']);

        if ($startDate->greaterThanOrEqualTo($endDate)) {
            throw ValidationException::withMessages([
                'end_date' => ['The lease end date must be strictly after the start date.'],
            ]);
        }

        $property = Property::query()->findOrFail($payload['property_id']);

        $projectUnitId = $payload['project_unit_id'] ?? null;
        if ($projectUnitId) {
            $unit = ProjectUnit::query()->find($projectUnitId);
            if (! $unit) {
                throw ValidationException::withMessages([
                    'project_unit_id' => ['The selected project unit does not exist.'],
                ]);
            }
        }

        $tenant = User::query()->findOrFail($payload['tenant_id']);
        $landlordId = (int) ($payload['landlord_id'] ?? $property->owner_id);
        $companyId = $payload['company_id'] ?? $property->company_id;

        $monthlyRent = round((float) $payload['monthly_rent_etb'], 2);
        if ($monthlyRent <= 0) {
            throw ValidationException::withMessages([
                'monthly_rent_etb' => ['Monthly rent must be greater than zero.'],
            ]);
        }

        $securityDeposit = round((float) ($payload['security_deposit_etb'] ?? 0.00), 2);
        $dueDay = (int) ($payload['due_day_of_month'] ?? 1);
        if ($dueDay < 1 || $dueDay > 28) {
            throw ValidationException::withMessages([
                'due_day_of_month' => ['Due day of month must be between 1 and 28.'],
            ]);
        }

        $status = isset($payload['status'])
            ? (is_string($payload['status']) ? LeaseStatus::from($payload['status']) : $payload['status'])
            : LeaseStatus::Draft;

        $leaseCode = $this->codeGenerator->execute();

        return DB::transaction(function () use (
            $payload,
            $leaseCode,
            $property,
            $projectUnitId,
            $tenant,
            $landlordId,
            $companyId,
            $startDate,
            $endDate,
            $monthlyRent,
            $securityDeposit,
            $dueDay,
            $status
        ): Lease {
            $lease = Lease::query()->create([
                'uuid' => (string) Str::uuid(),
                'lease_code' => $leaseCode,
                'rental_application_id' => $payload['rental_application_id'] ?? null,
                'property_id' => $property->id,
                'project_unit_id' => $projectUnitId,
                'tenant_id' => $tenant->id,
                'landlord_id' => $landlordId,
                'company_id' => $companyId,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'monthly_rent_etb' => $monthlyRent,
                'security_deposit_etb' => $securityDeposit,
                'payment_frequency' => $payload['payment_frequency'] ?? 'monthly',
                'due_day_of_month' => $dueDay,
                'contract_terms' => $payload['contract_terms'] ?? 'Standard Ethiopian Real Estate Lease Contract terms.',
                'status' => $status,
                'signed_contract_document_id' => $payload['signed_contract_document_id'] ?? null,
            ]);

            // Synchronize and enforce PostgreSQL GiST exclusion constraint & whole-property conflict trigger
            $this->occupancySynchronizer->execute($lease);

            // If created directly as active, generate rent schedules
            if ($lease->status === LeaseStatus::Active) {
                $this->scheduleGenerator->execute($lease);
            }

            return $lease->fresh();
        });
    }
}
