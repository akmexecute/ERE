<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Lease\Actions\CreateLeaseContractAction;
use App\Domains\Lease\Enums\LeaseStatus;
use App\Domains\Lease\Models\Lease;
use App\Domains\Tenant\Enums\RentalApplicationStatus;
use App\Domains\Tenant\Models\RentalApplication;
use App\Domains\Tenant\Models\RentalApplicationHistory;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ConvertApplicationToLeaseAction
{
    public function __construct(
        private CreateLeaseContractAction $leaseCreator,
    ) {}

    /**
     * Converts an approved rental application into a formal Lease Contract (SRS Section 147).
     *
     * @param  array<string, mixed>  $customTerms
     */
    public function execute(User $actor, RentalApplication $application, array $customTerms = []): Lease
    {
        // 1. Authorize actor (Landlord, company agent, or admin)
        $isLandlord = ($actor->id === $application->landlord_id);
        $isCompanyAuthorized = ($application->company_id && $actor->company_id === $application->company_id);
        $isStaff = $actor->hasAnyRole(['property_admin', 'platform_admin', 'super_admin']);

        if (! $isLandlord && ! $isCompanyAuthorized && ! $isStaff) {
            throw new AuthorizationException('You are not authorized to convert this rental application to a lease.');
        }

        return DB::transaction(function () use ($actor, $application, $customTerms): Lease {
            /** @var RentalApplication $lockedApp */
            $lockedApp = RentalApplication::query()
                ->where('id', $application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApp->status !== RentalApplicationStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => ['Only approved rental applications can be converted to a lease contract.'],
                ]);
            }

            if ($lockedApp->converted_lease_id !== null) {
                throw ValidationException::withMessages([
                    'converted_lease_id' => ['This application has already been converted into an active lease contract.'],
                ]);
            }

            $startDate = Carbon::parse($customTerms['start_date'] ?? $lockedApp->desired_move_in_date);
            $durationMonths = (int) ($customTerms['lease_duration_months'] ?? $lockedApp->lease_duration_months);
            $endDate = Carbon::parse($customTerms['end_date'] ?? $startDate->copy()->addMonths($durationMonths)->subDay());

            $monthlyRent = (float) ($customTerms['monthly_rent_etb'] ?? $lockedApp->proposed_monthly_rent_etb);
            $deposit = (float) ($customTerms['security_deposit_etb'] ?? $monthlyRent); // Defaults to 1 month security deposit

            $leasePayload = [
                'rental_application_id' => $lockedApp->id,
                'property_id' => $lockedApp->property_id,
                'project_unit_id' => $lockedApp->project_unit_id,
                'tenant_id' => $lockedApp->tenant_id,
                'landlord_id' => $lockedApp->landlord_id,
                'company_id' => $lockedApp->company_id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'monthly_rent_etb' => $monthlyRent,
                'security_deposit_etb' => $deposit,
                'payment_frequency' => $customTerms['payment_frequency'] ?? $lockedApp->payment_frequency,
                'due_day_of_month' => (int) ($customTerms['due_day_of_month'] ?? 1),
                'contract_terms' => $customTerms['contract_terms'] ?? 'Standard Lease Contract generated from Rental Application #' . $lockedApp->application_code,
                'status' => LeaseStatus::PendingSignatures,
            ];

            $lease = $this->leaseCreator->execute($actor, $leasePayload);

            $lockedApp->update([
                'converted_lease_id' => $lease->id,
                'status' => RentalApplicationStatus::ConvertedToLease,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            RentalApplicationHistory::query()->create([
                'rental_application_id' => $lockedApp->id,
                'from_status' => RentalApplicationStatus::Approved->value,
                'to_status' => RentalApplicationStatus::ConvertedToLease->value,
                'actor_id' => $actor->id,
                'notes' => 'Converted to lease #' . $lease->lease_code,
                'created_at' => now(),
            ]);

            return $lease;
        });
    }
}
