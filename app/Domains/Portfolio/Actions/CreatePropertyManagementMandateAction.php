<?php

declare(strict_types=1);

namespace App\Domains\Portfolio\Actions;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyBranch;
use App\Domains\Portfolio\Enums\PropertyManagementMandateStatus;
use App\Domains\Portfolio\Models\PropertyManagementMandate;
use App\Domains\Property\Models\Property;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreatePropertyManagementMandateAction
{
    public function __construct(
        private GenerateMandateCodeAction $codeGenerator
    ) {}

    /**
     * @param array{
     *     property_id: int,
     *     owner_user_id?: int|null,
     *     company_id: int,
     *     branch_id?: int|null,
     *     assigned_manager_user_id?: int|null,
     *     management_fee_pct: float|int|string,
     *     start_date: string|\DateTimeInterface,
     *     end_date?: string|\DateTimeInterface|null,
     *     terms?: string|null,
     * } $data
     */
    public function execute(User $actor, array $data): PropertyManagementMandate
    {
        $property = Property::query()->findOrFail($data['property_id']);
        $company = Company::query()->findOrFail($data['company_id']);

        $ownerId = $data['owner_user_id'] ?? $property->owner_id ?? $property->owner_user_id;

        // 1. Fee percentage validation (0.00% - 100.00%)
        $feePct = (float) $data['management_fee_pct'];
        if ($feePct < 0.00 || $feePct > 100.00) {
            throw ValidationException::withMessages([
                'management_fee_pct' => ['Management fee percentage must be between 0.00% and 100.00%.'],
            ]);
        }

        // 2. Date range validation
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = ! empty($data['end_date']) ? Carbon::parse($data['end_date'])->startOfDay() : null;

        if ($endDate && $endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => ['Mandate end date cannot be earlier than start date.'],
            ]);
        }

        // 3. Branch validation (must belong to company)
        if (! empty($data['branch_id'])) {
            $branchExists = CompanyBranch::query()
                ->where('id', $data['branch_id'])
                ->where('company_id', $company->id)
                ->exists();

            if (! $branchExists) {
                throw ValidationException::withMessages([
                    'branch_id' => ['The selected branch does not belong to the management company.'],
                ]);
            }
        }

        return DB::transaction(function () use ($property, $company, $ownerId, $feePct, $startDate, $endDate, $data) {
            // 4. Overlap protection: Property cannot have an existing active mandate
            $hasActiveMandate = PropertyManagementMandate::query()
                ->where('property_id', $property->id)
                ->where('status', PropertyManagementMandateStatus::Active->value)
                ->exists();

            if ($hasActiveMandate) {
                throw ValidationException::withMessages([
                    'property_id' => ["Property {$property->property_code} already has an active management mandate."],
                ]);
            }

            $mandateCode = $this->codeGenerator->execute();

            $mandate = PropertyManagementMandate::query()->create([
                'uuid' => (string) Str::uuid(),
                'mandate_code' => $mandateCode,
                'property_id' => $property->id,
                'owner_user_id' => $ownerId,
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'assigned_manager_user_id' => $data['assigned_manager_user_id'] ?? null,
                'management_fee_pct' => $feePct,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'status' => PropertyManagementMandateStatus::Active,
                'terms' => $data['terms'] ?? null,
            ]);

            // Link property to management company
            $property->update(['company_id' => $company->id]);

            return $mandate;
        });
    }
}
