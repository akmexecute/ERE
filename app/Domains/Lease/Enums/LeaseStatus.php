<?php

declare(strict_types=1);

namespace App\Domains\Lease\Enums;

enum LeaseStatus: string
{
    case Draft = 'draft';
    case PendingSignatures = 'pending_signatures';
    case Active = 'active';
    case MoveOutPending = 'move_out_pending';
    case Completed = 'completed';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingSignatures => 'Pending Signatures',
            self::Active => 'Active Occupancy',
            self::MoveOutPending => 'Move-Out Pending',
            self::Completed => 'Completed',
            self::Terminated => 'Terminated',
        };
    }

    public function isBlockingOccupancy(): bool
    {
        return in_array($this, [self::Active, self::PendingSignatures, self::MoveOutPending], true);
    }
}
