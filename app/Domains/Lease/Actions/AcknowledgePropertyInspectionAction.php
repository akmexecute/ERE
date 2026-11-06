<?php

declare(strict_types=1);

namespace App\Domains\Lease\Actions;

use App\Domains\Lease\Models\PropertyInspection;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final readonly class AcknowledgePropertyInspectionAction
{
    public function execute(User $actor, PropertyInspection $inspection, ?string $clientIp = null): PropertyInspection
    {
        $lease = $inspection->lease;
        $isTenant = ($actor->id === $lease->tenant_id);
        $isLandlord = ($actor->id === $lease->landlord_id || ($lease->company_id && $actor->company_id === $lease->company_id));

        if (! $isTenant && ! $isLandlord && ! $actor->hasRole('super_admin')) {
            throw new AuthorizationException('You are not a designated party to this lease inspection.');
        }

        $ip = $clientIp ?? '127.0.0.1';
        $updates = [];

        if ($isTenant) {
            if ($inspection->tenant_acknowledged_at !== null) {
                throw ValidationException::withMessages([
                    'tenant_acknowledged_at' => ['Tenant has already acknowledged this inspection.'],
                ]);
            }
            $updates['tenant_acknowledged_at'] = now();
            $updates['tenant_signature_ip'] = $ip;
        }

        if ($isLandlord) {
            if ($inspection->landlord_acknowledged_at !== null) {
                throw ValidationException::withMessages([
                    'landlord_acknowledged_at' => ['Landlord has already acknowledged this inspection.'],
                ]);
            }
            $updates['landlord_acknowledged_at'] = now();
            $updates['landlord_signature_ip'] = $ip;
        }

        $inspection->update($updates);

        return $inspection->fresh();
    }
}
