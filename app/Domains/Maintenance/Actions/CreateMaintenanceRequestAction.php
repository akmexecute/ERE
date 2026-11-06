<?php

declare(strict_types=1);

namespace App\Domains\Maintenance\Actions;

use App\Domains\Maintenance\Enums\MaintenanceCategory;
use App\Domains\Maintenance\Enums\MaintenancePriority;
use App\Domains\Maintenance\Enums\MaintenanceStatus;
use App\Domains\Maintenance\Models\MaintenanceRequest;
use App\Domains\Maintenance\Models\MaintenanceRequestHistory;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateMaintenanceRequestAction
{
    public function __construct(
        private GenerateMaintenanceTicketCodeAction $codeGenerator
    ) {}

    /**
     * @param array{
     *     property_id: int,
     *     project_unit_id?: int|null,
     *     accommodation_unit_id?: int|null,
     *     lease_id?: int|null,
     *     booking_id?: int|null,
     *     company_id?: int|null,
     *     branch_id?: int|null,
     *     category: string|MaintenanceCategory,
     *     priority?: string|MaintenancePriority|null,
     *     title: string,
     *     description: string,
     *     estimated_cost_etb?: float|int|string|null,
     *     billable_party?: string|null,
     * } $data
     */
    public function execute(User $reporter, array $data): MaintenanceRequest
    {
        $property = Property::query()->findOrFail($data['property_id']);

        $category = is_string($data['category'])
            ? MaintenanceCategory::from($data['category'])
            : $data['category'];

        $priority = isset($data['priority'])
            ? (is_string($data['priority']) ? MaintenancePriority::from($data['priority']) : $data['priority'])
            : MaintenancePriority::Normal;

        $estimatedCost = isset($data['estimated_cost_etb'])
            ? max(0.00, (float) $data['estimated_cost_etb'])
            : 0.00;

        return DB::transaction(function () use ($reporter, $property, $data, $category, $priority, $estimatedCost) {
            $ticketCode = $this->codeGenerator->execute();

            $request = MaintenanceRequest::query()->create([
                'uuid' => (string) Str::uuid(),
                'ticket_code' => $ticketCode,
                'property_id' => $property->id,
                'project_unit_id' => $data['project_unit_id'] ?? null,
                'accommodation_unit_id' => $data['accommodation_unit_id'] ?? null,
                'lease_id' => $data['lease_id'] ?? null,
                'booking_id' => $data['booking_id'] ?? null,
                'company_id' => $data['company_id'] ?? $property->company_id,
                'branch_id' => $data['branch_id'] ?? null,
                'reported_by_user_id' => $reporter->id,
                'category' => $category,
                'priority' => $priority,
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'estimated_cost_etb' => $estimatedCost,
                'actual_cost_etb' => 0.00,
                'billable_party' => $data['billable_party'] ?? 'owner',
                'status' => MaintenanceStatus::Open,
            ]);

            MaintenanceRequestHistory::query()->create([
                'maintenance_request_id' => $request->id,
                'actor_user_id' => $reporter->id,
                'from_status' => 'created',
                'to_status' => MaintenanceStatus::Open->value,
                'notes' => 'Ticket opened by '.$reporter->name,
                'cost_snapshot_etb' => $estimatedCost,
                'created_at' => now(),
            ]);

            return $request;
        });
    }
}
