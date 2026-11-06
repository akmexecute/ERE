<?php

declare(strict_types=1);

namespace App\Domains\Lease\Actions;

use App\Domains\Lease\Enums\LeaseStatus;
use App\Domains\Lease\Models\Lease;
use App\Domains\RentalFinancial\Actions\GenerateRentScheduleAction;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SignLeaseContractAction
{
    public function __construct(
        private SynchronizeLeaseOccupancyAction $occupancySynchronizer,
        private GenerateRentScheduleAction $scheduleGenerator,
    ) {}

    public function execute(User $signer, Lease $lease, ?string $clientIp = null): Lease
    {
        return DB::transaction(function () use ($signer, $lease, $clientIp): Lease {
            /** @var Lease $lockedLease */
            $lockedLease = Lease::query()
                ->where('id', $lease->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedLease->status, [LeaseStatus::Draft, LeaseStatus::PendingSignatures], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or pending signature contracts can be signed.'],
                ]);
            }

            $isTenant = ($signer->id === $lockedLease->tenant_id);
            $isLandlord = ($signer->id === $lockedLease->landlord_id || ($lockedLease->company_id && $signer->company_id === $lockedLease->company_id));

            if (! $isTenant && ! $isLandlord && ! $signer->hasRole('super_admin')) {
                throw new AuthorizationException('You are not a designated party to this lease contract.');
            }

            $updates = [];
            if ($isTenant) {
                if ($lockedLease->tenant_signed_at !== null) {
                    throw ValidationException::withMessages([
                        'tenant_signed_at' => ['Tenant has already signed this contract.'],
                    ]);
                }
                $updates['tenant_signed_at'] = now();
                $updates['tenant_signature_ip'] = $clientIp ?? '127.0.0.1';
            }

            if ($isLandlord) {
                if ($lockedLease->landlord_signed_at !== null) {
                    throw ValidationException::withMessages([
                        'landlord_signed_at' => ['Landlord has already signed this contract.'],
                    ]);
                }
                $updates['landlord_signed_at'] = now();
                $updates['landlord_signature_ip'] = $clientIp ?? '127.0.0.1';
            }

            $lockedLease->update($updates);
            $refreshed = $lockedLease->fresh();

            // If both parties have signed, activate the lease and generate rent schedules
            if ($refreshed->isFullySigned()) {
                $refreshed->update(['status' => LeaseStatus::Active]);
                $this->occupancySynchronizer->execute($refreshed);
                $this->scheduleGenerator->execute($refreshed);
            } else {
                $refreshed->update(['status' => LeaseStatus::PendingSignatures]);
                $this->occupancySynchronizer->execute($refreshed);
            }

            return $refreshed->fresh();
        });
    }
}
