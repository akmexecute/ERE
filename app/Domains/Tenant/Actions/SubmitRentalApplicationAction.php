<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Listing\Models\Listing;
use App\Domains\Property\Models\Property;
use App\Domains\Tenant\Enums\RentalApplicationStatus;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\RentalApplicationHistory;
use App\Domains\Tenant\Models\TenantProfile;
use App\Domains\User\Models\User;
use App\Models\ProjectUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SubmitRentalApplicationAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $tenant, array $payload): RentalApplication
    {
        $property = Property::query()->findOrFail($payload['property_id']);

        // 1. Validate Listing hierarchy if provided
        $listingId = $payload['listing_id'] ?? null;
        if ($listingId) {
            $listing = Listing::query()->find($listingId);
            if (! $listing || (int) $listing->property_id !== (int) $property->id) {
                throw ValidationException::withMessages([
                    'listing_id' => ['The specified listing does not belong to the selected property.'],
                ]);
            }
        }

        // 2. Validate Project Unit if provided
        $unitId = $payload['project_unit_id'] ?? null;
        if ($unitId) {
            $unit = ProjectUnit::query()->find($unitId);
            if (! $unit) {
                throw ValidationException::withMessages([
                    'project_unit_id' => ['The specified project unit was not found.'],
                ]);
            }
        }

        // 3. Resolve authoritative landlord from physical property ownership — never trust client input!
        $landlordId = (int) $property->owner_id;
        $companyId = $property->company_id;

        // 4. Ensure Tenant has a profile
        /** @var TenantProfile $tenantProfile */
        $tenantProfile = TenantProfile::query()->firstOrCreate(
            ['user_id' => $tenant->id],
            [
                'uuid' => (string) Str::uuid(),
                'tenant_code' => (new GenerateTenantCodeAction())->execute(),
                'status' => 'active',
            ]
        );

        // 5. Prevent duplicate pending applications for same resource
        $duplicateQuery = RentalApplication::query()
            ->where('tenant_id', $tenant->id)
            ->where('property_id', $property->id)
            ->whereIn('status', [
                RentalApplicationStatus::Submitted->value,
                RentalApplicationStatus::DocumentReview->value,
                RentalApplicationStatus::Approved->value,
            ]);

        if ($unitId) {
            $duplicateQuery->where('project_unit_id', $unitId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'property_id' => ['You already have an active rental application for this property.'],
            ]);
        }

        // 6. Generate atomic application code
        $appCode = $this->generateApplicationCode();

        return DB::transaction(function () use (
            $property,
            $listingId,
            $unitId,
            $tenant,
            $tenantProfile,
            $landlordId,
            $companyId,
            $payload,
            $appCode
        ): RentalApplication {
            $application = RentalApplication::query()->create([
                'uuid' => (string) Str::uuid(),
                'application_code' => $appCode,
                'property_id' => $property->id,
                'listing_id' => $listingId,
                'project_unit_id' => $unitId,
                'tenant_id' => $tenant->id,
                'tenant_profile_id' => $tenantProfile->id,
                'landlord_id' => $landlordId,
                'company_id' => $companyId,
                'desired_move_in_date' => $payload['desired_move_in_date'],
                'lease_duration_months' => (int) ($payload['lease_duration_months'] ?? 12),
                'proposed_monthly_rent_etb' => round((float) $payload['proposed_monthly_rent_etb'], 2),
                'payment_frequency' => $payload['payment_frequency'] ?? 'monthly',
                'occupant_count' => (int) ($payload['occupant_count'] ?? 1),
                'applicant_message' => $payload['applicant_message'] ?? null,
                'status' => RentalApplicationStatus::Submitted,
            ]);

            RentalApplicationHistory::query()->create([
                'rental_application_id' => $application->id,
                'from_status' => 'new',
                'to_status' => RentalApplicationStatus::Submitted->value,
                'actor_id' => $tenant->id,
                'notes' => 'Application submitted by tenant.',
                'created_at' => now(),
            ]);

            return $application;
        });
    }

    private function generateApplicationCode(): string
    {
        $year = date('Y');
        $prefix = "APP-{$year}";
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

            $candidate = sprintf('APP-%s-%07d', $year, $seq);
        } while (RentalApplication::query()->where('application_code', $candidate)->exists());

        return $candidate;
    }
}
