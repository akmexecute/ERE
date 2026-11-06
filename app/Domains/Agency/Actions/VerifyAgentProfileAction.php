<?php

declare(strict_types=1);

namespace App\Domains\Agency\Actions;

use App\Domains\Agency\Models\AgentProfile;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class VerifyAgentProfileAction
{
    /**
     * Verify an agent's profile under Proclamation No. 1357/2024 compliance.
     * Only authorized staff officers may execute verification; agents cannot self-verify.
     */
    public function execute(User $actor, AgentProfile $profile, ?string $notes = null): AgentProfile
    {
        // 1. Staff verification authority check
        $isStaff = $actor->hasRole('super_admin')
            || $actor->hasRole('platform_admin')
            || $actor->hasRole('verification_officer');

        if (! $isStaff) {
            throw new AuthorizationException('Unauthorized: Agent verification requires platform staff authority.');
        }

        // 2. Prevent self-verification
        if ((int) $actor->id === (int) $profile->user_id) {
            throw new AuthorizationException('Security violation: Agents cannot self-assign verification status.');
        }

        $profile->update([
            'is_verified_agent' => true,
            'status' => 'verified',
        ]);

        return $profile;
    }
}

